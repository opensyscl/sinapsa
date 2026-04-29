<?php

namespace App\Jobs;

use App\Channels\ChannelAdapterRegistry;
use App\Channels\DTO\NormalizedContact;
use App\Channels\DTO\NormalizedMessage;
use App\Channels\Enums\ChannelType;
use App\Channels\Enums\MessageDirection;
use App\Channels\Enums\MessageType;
use App\Models\Message;
use App\Models\UsageMeter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as QueueableTrait;
use Illuminate\Support\Facades\Log;

/**
 * Worker que envía un Message ya persistido (status='queued') a través del adapter.
 *
 * Flujo:
 *  1. POST /api/v1/messages → controller crea Message status='queued' y dispatchea este job.
 *  2. Job carga Message + Channel + Contact.
 *  3. Construye NormalizedMessage outbound y llama adapter->send().
 *  4. Si OK → guarda external_id, sent_at=now(), status='sent'.
 *  5. Si fail → status='failed' con error_code/error_message. Reintento controlado.
 *
 *  Los status delivered/read posteriores los actualiza el ProcessIncomingMetaWebhook
 *  cuando Meta nos manda webhook con statuses[].
 */
class SendOutboundMessage implements ShouldQueue
{
    use QueueableTrait, Queueable;

    public int $tries = 4;
    public array $backoff = [10, 30, 120, 600]; // 10s, 30s, 2m, 10m

    public function __construct(public int $messageId) {}

    public function handle(ChannelAdapterRegistry $adapters): void
    {
        $message = Message::withoutGlobalScopes()
            ->with(['channel', 'contact'])
            ->find($this->messageId);

        if (! $message || $message->direction !== MessageDirection::Outbound->value) {
            return;
        }

        // Si ya se envió (re-encolado por error), no duplicar
        if ($message->status === Message::STATUS_SENT || $message->status === Message::STATUS_DELIVERED) {
            return;
        }

        $channel = $message->channel;
        if (! $channel || ! $channel->isConnected()) {
            $message->forceFill([
                'status' => Message::STATUS_FAILED,
                'failed_at' => now(),
                'error_code' => 'channel_not_connected',
                'error_message' => 'El canal no está conectado.',
            ])->save();

            return;
        }

        $adapter = $adapters->for($channel->type);
        $normalized = $this->buildNormalized($message);

        $result = $adapter->send($channel, $normalized);

        if ($result->success) {
            $message->forceFill([
                'status' => Message::STATUS_SENT,
                'sent_at' => now(),
                'external_id' => $result->external_id,
            ])->save();

            // Notifica a CRMs/integradores externos
            app(\App\Webhooks\OutboundEventDispatcher::class)->messageSent($message);

            // Métrica de uso (solo si Meta lo aceptó)
            UsageMeter::bump($channel->workspace_id, UsageMeter::KIND_OUTBOUND, $channel->type);

            return;
        }

        Log::warning('outbound.send_failed', [
            'message_id' => $message->id,
            'error_code' => $result->error_code,
            'error_message' => $result->error_message,
        ]);

        // Si es un error que no tiene sentido reintentar (4xx que no sea 429),
        // marca failed definitivo. Para 429/5xx Laravel reintentará por nosotros.
        if ($this->isPermanentError($result->error_code)) {
            $message->forceFill([
                'status' => Message::STATUS_FAILED,
                'failed_at' => now(),
                'error_code' => $result->error_code,
                'error_message' => $result->error_message,
            ])->save();

            return;
        }

        throw new \RuntimeException(
            "Meta send failed [{$result->error_code}]: {$result->error_message}"
        );
    }

    protected function buildNormalized(Message $message): NormalizedMessage
    {
        $contact = $message->contact;
        $channelType = ChannelType::from($message->channel->type);

        return new NormalizedMessage(
            channel_type: $channelType,
            direction: MessageDirection::Outbound,
            type: MessageType::from($message->type),
            contact: new NormalizedContact(
                external_id: $this->contactExternalIdForChannel($contact, $channelType),
                name: $contact->name,
                phone: $contact->phone,
            ),
            external_thread_id: $message->conversation->external_thread_id ?? '',
            timestamp: now()->toIso8601String(),
            client_idempotency_key: $message->client_idempotency_key,
            body: $message->body,
            template_name: $message->template_name,
            template_language: data_get($message->template_payload, 'language'),
            template_components: data_get($message->template_payload, 'components'),
        );
    }

    protected function contactExternalIdForChannel($contact, ChannelType $type): string
    {
        $key = $type->value;
        $id = data_get($contact->identifiers, $key);
        if ($id) {
            return (string) $id;
        }
        // Fallback WA: el "external_id" es el wa_id que es el phone sin "+"
        if ($type === ChannelType::WhatsApp && $contact->phone) {
            return ltrim($contact->phone, '+');
        }

        return '';
    }

    /**
     * Errores Meta que NO tiene sentido reintentar.
     * Lista no exhaustiva — el resto Laravel reintenta hasta agotar tries.
     */
    protected function isPermanentError(?string $code): bool
    {
        if (! $code) {
            return false;
        }
        $permanent = [
            'channel_no_token', 'channel_not_connected',
            '100',  // Invalid parameter
            '131008', // Required parameter missing
            '131009', // Parameter value not valid
            '131026', // Message undeliverable (number not on WhatsApp)
            '131047', // Re-engagement message (24h window expired)
            '131051', // Unsupported message type
        ];

        return in_array($code, $permanent, true)
            || str_starts_with($code, '4'); // Meta usa 4xx para errores cliente
    }
}
