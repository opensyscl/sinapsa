<?php

namespace App\Channels\WhatsAppCloud;

use App\Channels\DTO\InboundParseResult;
use App\Channels\DTO\NormalizedContact;
use App\Channels\DTO\NormalizedMedia;
use App\Channels\DTO\NormalizedMessage;
use App\Channels\DTO\NormalizedStatus;
use App\Channels\DTO\NormalizedTemplateUpdate;
use App\Channels\Enums\ChannelType;
use App\Channels\Enums\MessageDirection;
use App\Channels\Enums\MessageType;
use Carbon\Carbon;

/**
 * Convierte el payload de webhook WA Cloud a DTOs normalizados.
 *
 * Shape de referencia (lo importante):
 *   payload.entry[].changes[].value.metadata.phone_number_id   → channel.external_id
 *   payload.entry[].changes[].value.contacts[]                 → contactos
 *   payload.entry[].changes[].value.messages[]                 → mensajes inbound
 *   payload.entry[].changes[].value.statuses[]                 → status updates outbound
 *
 * Importante: un solo webhook puede traer múltiples mensajes y/o statuses,
 * pero todos comparten el mismo phone_number_id.
 */
class WhatsAppCloudInboundParser
{
    public function parse(array $payload): InboundParseResult
    {
        $messages = [];
        $statuses = [];
        $templateUpdates = [];
        $channelExternalId = null;

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                $field = $change['field'] ?? null;

                if ($field === 'messages') {
                    $channelExternalId ??= data_get($value, 'metadata.phone_number_id');
                    $contactsByWaId = $this->indexContacts($value['contacts'] ?? []);

                    foreach ($value['messages'] ?? [] as $msg) {
                        $messages[] = $this->parseMessage($msg, $contactsByWaId);
                    }
                    foreach ($value['statuses'] ?? [] as $status) {
                        $statuses[] = $this->parseStatus($status);
                    }
                    continue;
                }

                if ($field === 'message_template_status_update') {
                    // Meta no manda phone_number_id en este field — usamos waba_id (entry.id)
                    // como fallback, pero en realidad localizamos el canal por waba en el job.
                    $templateUpdates[] = $this->parseTemplateUpdate(
                        $value,
                        wabaId: (string) ($entry['id'] ?? ''),
                    );
                }
            }
        }

        return new InboundParseResult(
            channel_external_id: (string) ($channelExternalId ?? ''),
            messages: $messages,
            statuses: $statuses,
            template_updates: $templateUpdates,
        );
    }

    protected function parseTemplateUpdate(array $value, string $wabaId): NormalizedTemplateUpdate
    {
        return new NormalizedTemplateUpdate(
            template_name: (string) ($value['message_template_name'] ?? ''),
            language: (string) ($value['message_template_language'] ?? ''),
            // Meta usa `event` (APPROVED, REJECTED, etc.) — lo guardamos como status local
            status: (string) ($value['event'] ?? 'PENDING'),
            meta_template_id: isset($value['message_template_id']) ? (string) $value['message_template_id'] : null,
            reason: $value['reason'] ?? null,
            raw: $value + ['waba_id' => $wabaId],
        );
    }

    /**
     * @return array<string, array<string, mixed>> indexed by wa_id
     */
    protected function indexContacts(array $contacts): array
    {
        $out = [];
        foreach ($contacts as $c) {
            if (isset($c['wa_id'])) {
                $out[$c['wa_id']] = $c;
            }
        }

        return $out;
    }

    protected function parseMessage(array $msg, array $contactsByWaId): NormalizedMessage
    {
        $from = (string) ($msg['from'] ?? '');
        $contactRaw = $contactsByWaId[$from] ?? [];
        $contact = new NormalizedContact(
            external_id: $from,
            name: data_get($contactRaw, 'profile.name'),
            phone: $this->normalizePhone($from),
        );

        $type = $this->mapType($msg['type'] ?? null);
        $timestamp = $this->parseTimestamp($msg['timestamp'] ?? null);

        // Contenido por tipo
        $body = null;
        $media = null;
        $location = null;
        $reactionEmoji = null;
        $reactedToExternalId = null;
        $interactivePayload = null;

        switch ($type) {
            case MessageType::Text:
                $body = data_get($msg, 'text.body');
                break;
            case MessageType::Image:
            case MessageType::Audio:
            case MessageType::Video:
            case MessageType::Document:
            case MessageType::Sticker:
                $media = $this->parseMedia($msg, $type);
                $body = $media?->caption;
                break;
            case MessageType::Location:
                $location = [
                    'lat' => data_get($msg, 'location.latitude'),
                    'lng' => data_get($msg, 'location.longitude'),
                    'name' => data_get($msg, 'location.name'),
                    'address' => data_get($msg, 'location.address'),
                ];
                break;
            case MessageType::Reaction:
                $reactionEmoji = data_get($msg, 'reaction.emoji');
                $reactedToExternalId = data_get($msg, 'reaction.message_id');
                break;
            case MessageType::Interactive:
                $interactivePayload = $msg['interactive'] ?? null;
                $body = data_get($msg, 'interactive.button_reply.title')
                    ?? data_get($msg, 'interactive.list_reply.title');
                break;
            default:
                // tipos que no soportamos todavía: contacts share, etc — guardamos raw
                break;
        }

        return new NormalizedMessage(
            channel_type: ChannelType::WhatsApp,
            direction: MessageDirection::Inbound,
            type: $type,
            contact: $contact,
            external_thread_id: $from,                 // En WA, thread = wa_id del contacto
            timestamp: $timestamp,
            external_id: $msg['id'] ?? null,           // wamid.xxx
            body: $body,
            media: $media,
            interactive_payload: $interactivePayload,
            location: $location,
            reaction_emoji: $reactionEmoji,
            reacted_to_external_id: $reactedToExternalId,
            raw: $msg,
        );
    }

    protected function parseMedia(array $msg, MessageType $type): ?NormalizedMedia
    {
        $key = $type->value; // image | audio | video | document | sticker
        $media = $msg[$key] ?? null;
        if (! is_array($media)) {
            return null;
        }

        return new NormalizedMedia(
            external_id: (string) ($media['id'] ?? ''),
            mime_type: $media['mime_type'] ?? null,
            caption: $media['caption'] ?? null,
            sha256: $media['sha256'] ?? null,
            filename: $media['filename'] ?? null,
        );
    }

    protected function parseStatus(array $status): NormalizedStatus
    {
        $errCode = null;
        $errMessage = null;
        if (! empty($status['errors'])) {
            $err = $status['errors'][0];
            $errCode = (string) ($err['code'] ?? '');
            $errMessage = (string) ($err['message'] ?? $err['title'] ?? '');
        }

        return new NormalizedStatus(
            external_id: (string) ($status['id'] ?? ''),
            status: (string) ($status['status'] ?? 'unknown'),
            timestamp: $this->parseTimestamp($status['timestamp'] ?? null),
            recipient_external_id: $status['recipient_id'] ?? null,
            error_code: $errCode,
            error_message: $errMessage,
            raw: $status,
        );
    }

    protected function mapType(?string $waType): MessageType
    {
        return match ($waType) {
            'text' => MessageType::Text,
            'image' => MessageType::Image,
            'audio' => MessageType::Audio,
            'video' => MessageType::Video,
            'document' => MessageType::Document,
            'sticker' => MessageType::Sticker,
            'location' => MessageType::Location,
            'reaction' => MessageType::Reaction,
            'interactive', 'button' => MessageType::Interactive,
            'template' => MessageType::Template,
            default => MessageType::Unknown,
        };
    }

    protected function parseTimestamp(mixed $ts): string
    {
        if (! $ts) {
            return now()->toIso8601String();
        }
        // Meta envía unix timestamp en segundos como string
        return Carbon::createFromTimestampUTC((int) $ts)->toIso8601String();
    }

    /**
     * WA usa wa_id sin "+" — añadimos "+" si falta y solo dígitos.
     * No validamos E.164 estrictamente porque eso lo asume Meta del lado del usuario.
     */
    protected function normalizePhone(string $waId): ?string
    {
        $digits = preg_replace('/\D/', '', $waId);
        if (! $digits) {
            return null;
        }

        return '+' . $digits;
    }
}
