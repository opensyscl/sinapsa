<?php

namespace App\Channels\WhatsAppCloud;

use App\Channels\DTO\NormalizedMessage;
use App\Channels\Enums\MessageType;
use InvalidArgumentException;

/**
 * Construye el body JSON para POST {phone_number_id}/messages.
 *
 * Reference: https://developers.facebook.com/docs/whatsapp/cloud-api/reference/messages
 */
class WhatsAppCloudOutboundBuilder
{
    public function build(NormalizedMessage $msg): array
    {
        $base = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $msg->contact->external_id,
            'type' => $this->mapType($msg->type),
        ];

        return match ($msg->type) {
            MessageType::Text => $base + [
                'text' => [
                    'preview_url' => false,
                    'body' => (string) ($msg->body ?? ''),
                ],
            ],
            MessageType::Image => $base + [
                'image' => array_filter([
                    'link' => $msg->media?->url,
                    'id' => $msg->media?->external_id,
                    'caption' => $msg->body ?? $msg->media?->caption,
                ]),
            ],
            MessageType::Document => $base + [
                'document' => array_filter([
                    'link' => $msg->media?->url,
                    'id' => $msg->media?->external_id,
                    'filename' => $msg->media?->filename,
                    'caption' => $msg->body ?? $msg->media?->caption,
                ]),
            ],
            MessageType::Audio => $base + [
                'audio' => array_filter([
                    'link' => $msg->media?->url,
                    'id' => $msg->media?->external_id,
                ]),
            ],
            MessageType::Video => $base + [
                'video' => array_filter([
                    'link' => $msg->media?->url,
                    'id' => $msg->media?->external_id,
                    'caption' => $msg->body ?? $msg->media?->caption,
                ]),
            ],
            MessageType::Template => $base + [
                'template' => array_filter([
                    'name' => $msg->template_name,
                    'language' => ['code' => $msg->template_language ?? 'es'],
                    'components' => $msg->template_components ?? [],
                ], fn ($v) => $v !== null && $v !== []),
            ],
            MessageType::Interactive => $base + [
                'interactive' => $msg->interactive_payload ?? [],
            ],
            MessageType::Location => $base + [
                'location' => [
                    'latitude' => data_get($msg->location, 'lat'),
                    'longitude' => data_get($msg->location, 'lng'),
                    'name' => data_get($msg->location, 'name'),
                    'address' => data_get($msg->location, 'address'),
                ],
            ],
            default => throw new InvalidArgumentException(
                "WhatsApp Cloud outbound: tipo no soportado [{$msg->type->value}]."
            ),
        };
    }

    /**
     * Meta no acepta nuestro `unknown`/`reaction` directamente para outbound.
     * Para reactions hace falta un payload distinto (referencing message_id),
     * lo dejamos para Fase 5 cuando expongamos la API pública completa.
     */
    protected function mapType(MessageType $type): string
    {
        return match ($type) {
            MessageType::Text, MessageType::Image, MessageType::Audio, MessageType::Video,
            MessageType::Document, MessageType::Template, MessageType::Interactive,
            MessageType::Location => $type->value,
            default => throw new InvalidArgumentException(
                "Tipo {$type->value} no enviable a WA Cloud."
            ),
        };
    }
}
