<?php

namespace App\Channels\Instagram;

use App\Channels\DTO\NormalizedMessage;
use App\Channels\Enums\MessageType;
use InvalidArgumentException;

/**
 * Construye el body para POST /{ig_user_id}/messages.
 *
 * Shape Instagram:
 *   {
 *     "recipient": { "id": "IGSID" },
 *     "message":   { ... según tipo ... }
 *   }
 *
 * Tipos soportados en MVP: text, image (URL), video (URL).
 * Reactions, stickers, story replies → out of MVP.
 */
class InstagramOutboundBuilder
{
    public function build(NormalizedMessage $msg): array
    {
        $base = [
            'recipient' => ['id' => $msg->contact->external_id],
        ];

        return match ($msg->type) {
            MessageType::Text => $base + [
                'message' => ['text' => (string) ($msg->body ?? '')],
            ],
            MessageType::Image => $base + [
                'message' => [
                    'attachment' => [
                        'type' => 'image',
                        'payload' => array_filter([
                            'url' => $msg->media?->url,
                            'is_reusable' => true,
                        ]),
                    ],
                ],
            ],
            MessageType::Video => $base + [
                'message' => [
                    'attachment' => [
                        'type' => 'video',
                        'payload' => array_filter([
                            'url' => $msg->media?->url,
                            'is_reusable' => true,
                        ]),
                    ],
                ],
            ],
            default => throw new InvalidArgumentException(
                "Instagram outbound: tipo no soportado [{$msg->type->value}]."
            ),
        };
    }
}
