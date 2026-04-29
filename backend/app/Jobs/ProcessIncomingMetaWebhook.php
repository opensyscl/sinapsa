<?php

namespace App\Jobs;

use App\Channels\ChannelAdapterRegistry;
use App\Channels\DTO\NormalizedContact;
use App\Channels\DTO\NormalizedMessage;
use App\Channels\DTO\NormalizedStatus;
use App\Channels\DTO\NormalizedTemplateUpdate;
use App\Channels\Enums\MessageDirection;
use App\Events\MessageReceived;
use App\Events\MessageStatusUpdated;
use App\Models\Channel;
use App\Models\UsageMeter;
use App\Models\WaTemplate;
use App\Webhooks\OutboundEventDispatcher;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WebhookInboundLog;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as QueueableTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Procesa un webhook entrante de Meta ya verificado y persistido en
 * `webhook_inbound_log`. Hace:
 *  1. Carga el log + canal.
 *  2. Llama al adapter para parsear → InboundParseResult.
 *  3. Por cada mensaje: upsert contact + conversation + message.
 *  4. Por cada status: actualiza status del Message correspondiente.
 *  5. Marca log.processed_at.
 *
 * Idempotencia: external_id de Meta (wamid) es único por mensaje. Si ya existe,
 * skip insert. Esto cubre el caso de Meta reenviando el mismo payload tras un 5xx.
 */
class ProcessIncomingMetaWebhook implements ShouldQueue
{
    use QueueableTrait, Queueable;

    public int $tries = 5;
    public int $backoff = 30; // segundos, exponencial: 30, 60, 120, 240, 480

    public function __construct(
        public int $logId,
        public int $channelId,
        public string $channelType,
    ) {}

    public function handle(ChannelAdapterRegistry $adapters): void
    {
        $log = WebhookInboundLog::find($this->logId);
        if (! $log) {
            return;
        }

        $channel = Channel::withoutGlobalScopes()->find($this->channelId);
        if (! $channel) {
            $log->update(['processing_error' => 'Channel not found']);

            return;
        }

        try {
            $adapter = $adapters->for($this->channelType);
            $result = $adapter->parseInbound($log->payload);

            DB::transaction(function () use ($result, $channel) {
                foreach ($result->messages as $msg) {
                    $this->ingestInboundMessage($channel, $msg);
                }
                foreach ($result->statuses as $status) {
                    $this->ingestStatus($channel, $status);
                }
                foreach ($result->template_updates as $update) {
                    $this->ingestTemplateUpdate($channel, $update);
                }
            });

            $log->update(['processed_at' => now()]);
        } catch (Throwable $e) {
            Log::error('meta.webhook.process_failed', [
                'log_id' => $log->id,
                'error' => $e->getMessage(),
            ]);
            $log->update(['processing_error' => $e->getMessage()]);
            throw $e;
        }
    }

    protected function ingestInboundMessage(Channel $channel, NormalizedMessage $msg): void
    {
        $contact = $this->upsertContact($channel, $msg->contact);
        $conversation = $this->upsertConversation($channel, $contact, $msg);

        // Idempotencia: si el wamid ya existe, salta. Postgres particionado no
        // permite UNIQUE global, así que hacemos check + insert.
        if ($msg->external_id) {
            $exists = Message::withoutGlobalScopes()
                ->where('workspace_id', $channel->workspace_id)
                ->where('external_id', $msg->external_id)
                ->exists();
            if ($exists) {
                return;
            }
        }

        // created_at lo asigna Eloquent automáticamente a now() — la marca temporal de
        // Meta vive en raw_payload (campo `timestamp`). Si la metiéramos en created_at
        // un mensaje con fecha antigua intentaría caer en una partición inexistente.
        $message = Message::create([
            'workspace_id' => $channel->workspace_id,
            'conversation_id' => $conversation->id,
            'channel_id' => $channel->id,
            'contact_id' => $contact->id,
            'direction' => MessageDirection::Inbound->value,
            'status' => Message::STATUS_DELIVERED, // Inbound llegó a nosotros = entregado
            'type' => $msg->type->value,
            'external_id' => $msg->external_id,
            'body' => $msg->body,
            'media_url' => $msg->media?->url,
            'media_mime' => $msg->media?->mime_type,
            'raw_payload' => $msg->raw,
        ]);

        $conversation->forceFill([
            'last_message_at' => Carbon::parse($msg->timestamp),
            'unread_count' => $conversation->unread_count + 1,
            'metadata' => array_merge($conversation->metadata ?? [], [
                'last_inbound_at' => $msg->timestamp,
            ]),
        ])->save();

        // Realtime interno (Reverb) — refresca la bandeja sin reload
        MessageReceived::dispatch($message);

        // Outbound webhooks → notifica a CRMs/integradores externos suscritos
        app(OutboundEventDispatcher::class)->messageReceived($message);

        // Métrica de uso (no bloquea, solo cuenta)
        UsageMeter::bump($channel->workspace_id, UsageMeter::KIND_INBOUND, $channel->type);
    }

    protected function ingestStatus(Channel $channel, NormalizedStatus $status): void
    {
        $message = Message::withoutGlobalScopes()
            ->where('workspace_id', $channel->workspace_id)
            ->where('external_id', $status->external_id)
            ->first();

        if (! $message) {
            // El status puede llegar antes que confirmemos nuestro send (race).
            // Retry ayudará — el job se reencolará y al volver el message ya existirá.
            Log::info('meta.status.unknown_external_id', [
                'external_id' => $status->external_id,
                'status' => $status->status,
            ]);

            return;
        }

        $field = match ($status->status) {
            'sent' => 'sent_at',
            'delivered' => 'delivered_at',
            'read' => 'read_at',
            'failed' => 'failed_at',
            default => null,
        };

        $update = ['status' => $status->status];
        if ($field) {
            $update[$field] = Carbon::parse($status->timestamp);
        }
        if ($status->status === 'failed') {
            $update['error_code'] = $status->error_code;
            $update['error_message'] = $status->error_message;
        }

        $message->forceFill($update)->save();

        // Realtime interno: el frontend actualiza el badge de status del bubble outbound
        MessageStatusUpdated::dispatch($message);

        // Outbound webhooks: traduce el status Meta a evento normalizado
        $eventType = match ($status->status) {
            'sent' => 'message.sent',
            'delivered' => 'message.delivered',
            'read' => 'message.read',
            'failed' => 'message.failed',
            default => null,
        };
        if ($eventType) {
            app(OutboundEventDispatcher::class)->messageStatus($eventType, $message);
        }
    }

    /**
     * Procesa un cambio de status de plantilla notificado por Meta. Si la plantilla
     * no existe localmente (e.g. fue creada por otro lado en Business Manager),
     * la creamos en blanco — el siguiente sync llenará components y category.
     */
    protected function ingestTemplateUpdate(Channel $channel, NormalizedTemplateUpdate $update): void
    {
        $template = WaTemplate::withoutGlobalScopes()
            ->where('channel_id', $channel->id)
            ->where('name', $update->template_name)
            ->where('language', $update->language)
            ->first();

        if (! $template) {
            $template = new WaTemplate([
                'channel_id' => $channel->id,
                'name' => $update->template_name,
                'language' => $update->language,
                'status' => $update->status,
                'meta_template_id' => $update->meta_template_id,
                'rejected_reason' => $update->reason,
                'last_synced_at' => now(),
            ]);
            $template->workspace_id = $channel->workspace_id;
            $template->save();
        } else {
            $template->forceFill([
                'status' => $update->status,
                'meta_template_id' => $update->meta_template_id ?? $template->meta_template_id,
                'rejected_reason' => $update->reason,
                'last_synced_at' => now(),
            ])->save();
        }

        // Outbound webhooks: notifica a CRMs/integradores
        app(OutboundEventDispatcher::class)->templateStatusUpdated($template);
    }

    protected function upsertContact(Channel $channel, NormalizedContact $c): Contact
    {
        $contact = Contact::withoutGlobalScopes()
            ->where('workspace_id', $channel->workspace_id)
            ->where('phone', $c->phone)
            ->first();

        if (! $contact) {
            $contact = new Contact([
                'name' => $c->name,
                'phone' => $c->phone,
                'avatar_url' => $c->avatar_url,
                'identifiers' => [$channel->type => $c->external_id],
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);
            $contact->workspace_id = $channel->workspace_id;
            $contact->save();
        } else {
            $patch = [];
            if ($c->name && $contact->name !== $c->name) {
                $patch['name'] = $c->name;
            }
            $identifiers = $contact->identifiers ?? [];
            if (($identifiers[$channel->type] ?? null) !== $c->external_id) {
                $identifiers[$channel->type] = $c->external_id;
                $patch['identifiers'] = $identifiers;
            }
            $patch['last_seen_at'] = now();
            $contact->forceFill($patch)->save();
        }

        return $contact;
    }

    protected function upsertConversation(Channel $channel, Contact $contact, NormalizedMessage $msg): Conversation
    {
        $conv = Conversation::withoutGlobalScopes()
            ->where('workspace_id', $channel->workspace_id)
            ->where('channel_id', $channel->id)
            ->where('external_thread_id', $msg->external_thread_id)
            ->first();

        if ($conv) {
            return $conv;
        }

        $conv = new Conversation([
            'channel_id' => $channel->id,
            'contact_id' => $contact->id,
            'external_thread_id' => $msg->external_thread_id,
            'status' => Conversation::STATUS_OPEN,
            'last_message_at' => Carbon::parse($msg->timestamp),
            'unread_count' => 0,
            'metadata' => ['opened_via' => 'inbound', 'first_inbound_at' => $msg->timestamp],
        ]);
        $conv->workspace_id = $channel->workspace_id;
        $conv->save();

        return $conv;
    }
}
