<?php

namespace App\Webhooks;

use App\Http\Resources\ContactMiniResource;
use App\Http\Resources\MessageResource;
use App\Http\Resources\WaTemplateResource;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WaTemplate;
use App\Models\Workspace;
use Illuminate\Support\Str;

/**
 * Construye el payload normalizado que enviamos al cliente externo.
 *
 * Forma canónica (Stripe-like):
 *   {
 *     "id":          "evt_01HK...",
 *     "type":        "message.received",
 *     "workspace_id": 1,
 *     "occurred_at": "2026-04-28T10:21:43Z",
 *     "data": { ... }
 *   }
 *
 * Importante: este payload es contractual con los integradores. Cualquier
 * cambio estructural ROMPE clientes. Sumar campos OK; renombrar/quitar NO.
 */
class EventPayloadBuilder
{
    /**
     * Genera un identificador único para el evento (`evt_<ULID>`).
     */
    public static function newEventId(): string
    {
        return 'evt_' . Str::ulid();
    }

    /**
     * Payload para `message.received` / `message.sent` / `message.failed` —
     * cualquier evento centrado en un Message.
     */
    public function forMessage(string $eventType, Message $message, Workspace $workspace): array
    {
        $message->loadMissing(['conversation', 'channel', 'contact']);

        return [
            'id' => self::newEventId(),
            'type' => $eventType,
            'workspace_id' => $workspace->id,
            'occurred_at' => now()->toIso8601String(),
            'data' => [
                'channel' => $this->channel($message->channel),
                'contact' => $message->contact
                    ? (new ContactMiniResource($message->contact))->toArray(request())
                    : null,
                'conversation' => $this->conversation($message->conversation),
                'message' => (new MessageResource($message))->toArray(request()),
            ],
        ];
    }

    /**
     * Payload para `message.delivered` / `message.read` — mismo shape que
     * forMessage pero conceptualmente un status update.
     */
    public function forMessageStatus(string $eventType, Message $message, Workspace $workspace): array
    {
        return $this->forMessage($eventType, $message, $workspace);
    }

    public function forTemplate(string $eventType, WaTemplate $template, Workspace $workspace): array
    {
        $template->loadMissing('channel');

        return [
            'id' => self::newEventId(),
            'type' => $eventType,
            'workspace_id' => $workspace->id,
            'occurred_at' => now()->toIso8601String(),
            'data' => [
                'channel' => $template->channel ? [
                    'id' => $template->channel->id,
                    'type' => $template->channel->type,
                    'display_name' => $template->channel->display_name,
                ] : null,
                'template' => (new WaTemplateResource($template))->toArray(request()),
            ],
        ];
    }

    private function channel(?Channel $channel): ?array
    {
        if (! $channel) {
            return null;
        }

        return [
            'id' => $channel->id,
            'type' => $channel->type,
            'display_name' => $channel->display_name,
        ];
    }

    private function conversation(?Conversation $conversation): ?array
    {
        if (! $conversation) {
            return null;
        }

        return [
            'id' => $conversation->id,
            'external_thread_id' => $conversation->external_thread_id,
            'status' => $conversation->status,
        ];
    }
}
