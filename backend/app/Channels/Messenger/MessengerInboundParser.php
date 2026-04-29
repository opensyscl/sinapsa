<?php

namespace App\Channels\Messenger;

use App\Channels\DTO\InboundParseResult;
use App\Channels\DTO\NormalizedContact;
use App\Channels\DTO\NormalizedMedia;
use App\Channels\DTO\NormalizedMessage;
use App\Channels\Enums\ChannelType;
use App\Channels\Enums\MessageDirection;
use App\Channels\Enums\MessageType;
use Carbon\Carbon;

/**
 * Parser de webhooks Facebook Messenger.
 *
 * Shape Meta:
 *   payload.object = "page"
 *   payload.entry[].id                       → page_id (channel.external_id)
 *   payload.entry[].messaging[]              → eventos
 *   payload.entry[].messaging[].sender.id    → PSID del contacto
 *   payload.entry[].messaging[].message.mid  → message id
 *
 * Estructura casi idéntica a Instagram. La diferencia clave:
 *  - El external_thread_id es el PSID (sender.id en inbound).
 *  - Ventana 7 días (vs 24h IG).
 *  - Mensaje template con tags HUMAN_AGENT permite extender la ventana.
 */
class MessengerInboundParser
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
                    $messages[] = $this->parseMessageEvent($event);
                }
                // delivery / read events: para MVP no los procesamos ya que Messenger
                // no asocia un mid concreto en los eventos de read (timestamp watermark).
            }
        }

        return new InboundParseResult(
            channel_external_id: (string) ($channelExternalId ?? ''),
            messages: $messages,
            statuses: $statuses,
        );
    }

    protected function parseMessageEvent(array $event): NormalizedMessage
    {
        $senderId = (string) data_get($event, 'sender.id', '');
        $contact = new NormalizedContact(
            external_id: $senderId,
            name: null,
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
                external_id: (string) data_get($first, 'payload.url', ''),
                mime_type: null,
                url: data_get($first, 'payload.url'),
            );
        }

        return new NormalizedMessage(
            channel_type: ChannelType::Messenger,
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
            'location' => MessageType::Location,
            default => MessageType::Unknown,
        };
    }

    protected function parseTimestamp(mixed $ts): string
    {
        if (! $ts) {
            return now()->toIso8601String();
        }

        return Carbon::createFromTimestampMs((int) $ts)->toIso8601String();
    }
}
