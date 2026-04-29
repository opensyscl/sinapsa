<?php

namespace App\Channels\Instagram;

use App\Channels\DTO\InboundParseResult;
use App\Channels\DTO\NormalizedContact;
use App\Channels\DTO\NormalizedMedia;
use App\Channels\DTO\NormalizedMessage;
use App\Channels\Enums\ChannelType;
use App\Channels\Enums\MessageDirection;
use App\Channels\Enums\MessageType;
use Carbon\Carbon;

/**
 * Parser de webhooks Instagram Messaging.
 *
 * Shape Meta:
 *   payload.object = "instagram"
 *   payload.entry[].id                       → ig_user_id (channel.external_id)
 *   payload.entry[].messaging[]              → eventos
 *   payload.entry[].messaging[].sender.id    → IGSID del contacto
 *   payload.entry[].messaging[].message.mid  → message id
 *   payload.entry[].messaging[].message.text → texto
 *   payload.entry[].messaging[].message.attachments[] → media (image/video/audio/file/share)
 *   payload.entry[].messaging[].read         → status read del último msg outbound
 *   payload.entry[].messaging[].postback     → quick reply / icebreaker
 *
 * Diferencias importantes con WA Cloud:
 *  - El "external_thread_id" es el IGSID del contacto (no hay phone).
 *  - IG no manda "delivered" como status separado (solo read).
 *  - No hay templates aprobadas — los mensajes son texto/media libres dentro
 *    de la ventana 24h. Para fuera de ventana hay tags HUMAN_AGENT (en outbound).
 */
class InstagramInboundParser
{
    public function parse(array $payload): InboundParseResult
    {
        $messages = [];
        $statuses = [];
        $channelExternalId = null;

        foreach ($payload['entry'] ?? [] as $entry) {
            $channelExternalId ??= isset($entry['id']) ? (string) $entry['id'] : null;

            foreach ($entry['messaging'] ?? [] as $event) {
                if (! empty($event['message'])) {
                    $messages[] = $this->parseMessageEvent($event, $channelExternalId);
                    continue;
                }

                if (! empty($event['read'])) {
                    // No tenemos un wamid en IG; el "read" se asocia al thread,
                    // pero Sinapsa no aplica status read a un message concreto desde IG
                    // hasta que tenga el mid en el evento. De momento lo logueamos solo.
                    continue;
                }
            }
        }

        return new InboundParseResult(
            channel_external_id: (string) ($channelExternalId ?? ''),
            messages: $messages,
            statuses: $statuses,
        );
    }

    protected function parseMessageEvent(array $event, ?string $channelExternalId): NormalizedMessage
    {
        $senderId = (string) data_get($event, 'sender.id', '');
        $contact = new NormalizedContact(
            external_id: $senderId,
            name: null, // IG no manda nombre en webhook — lo obtenemos por API aparte (Fase futura)
            phone: null,
        );

        $msg = $event['message'] ?? [];
        $type = $this->mapType($msg);
        $body = data_get($msg, 'text');
        $media = null;

        $attachments = $msg['attachments'] ?? [];
        if (! empty($attachments) && $type !== MessageType::Text) {
            $first = $attachments[0];
            $media = new NormalizedMedia(
                external_id: (string) data_get($first, 'payload.url', data_get($first, 'payload.id', '')),
                mime_type: null, // IG no lo devuelve directamente
                url: data_get($first, 'payload.url'),
            );
        }

        return new NormalizedMessage(
            channel_type: ChannelType::Instagram,
            direction: MessageDirection::Inbound,
            type: $type,
            contact: $contact,
            external_thread_id: $senderId,
            timestamp: $this->parseTimestamp($event['timestamp'] ?? null),
            external_id: data_get($msg, 'mid'),
            body: $body,
            media: $media,
            raw: $event,
        );
    }

    protected function mapType(array $msg): MessageType
    {
        if (! empty($msg['text'])) {
            return MessageType::Text;
        }
        $first = $msg['attachments'][0] ?? null;
        $kind = $first['type'] ?? null;

        return match ($kind) {
            'image' => MessageType::Image,
            'video' => MessageType::Video,
            'audio' => MessageType::Audio,
            'file' => MessageType::Document,
            'story_mention', 'story_reply', 'share', 'ig_reel' => MessageType::Image,
            default => MessageType::Unknown,
        };
    }

    protected function parseTimestamp(mixed $ts): string
    {
        if (! $ts) {
            return now()->toIso8601String();
        }
        // Meta Messenger / IG mandan timestamp en MILISEGUNDOS unix
        return Carbon::createFromTimestampMs((int) $ts)->toIso8601String();
    }
}
