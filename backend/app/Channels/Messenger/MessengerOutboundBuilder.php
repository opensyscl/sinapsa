<?php

namespace App\Channels\Messenger;

use App\Channels\DTO\NormalizedMessage;
use App\Channels\Enums\MessageType;
use InvalidArgumentException;

/**
 * Construye el body para POST /{page_id}/messages.
 *
 * Shape Messenger:
 *   {
 *     "recipient":      { "id": "PSID" },
 *     "message":        { ... },
 *     "messaging_type": "RESPONSE" | "UPDATE" | "MESSAGE_TAG"
 *   }
 *
 * `messaging_type=RESPONSE` se permite dentro de la ventana 24h tras un mensaje
 * inbound. Fuera de eso hay que usar tags como HUMAN_AGENT (ventana 7d) — eso
 * lo decidirá el caller en una iteración futura. MVP: siempre RESPONSE.
 */
class MessengerOutboundBuilder
{
    public function build(NormalizedMessage $msg): array
    {
        $base = [
            'recipient' => ['id' => $msg->contact->external_id],
            'messaging_type' => 'RESPONSE',
        ];

        return match ($msg->type) {
            MessageType::Text => $base + [
                'message' => ['text' => (string) ($msg->body ?? '')],
            ],
            MessageType::Image, MessageType::Video, MessageType::Audio, MessageType::Document => $base + [
                'message' => [
                    'attachment' => [
                        'type' => $msg->type->value,
                        'payload' => array_filter([
                            'url' => $msg->media?->url,
                            'is_reusable' => true,
                        ]),
                    ],
                ],
            ],
            default => throw new InvalidArgumentException(
                "Messenger outbound: tipo no soportado [{$msg->type->value}]."
            ),
        };
    }
}
