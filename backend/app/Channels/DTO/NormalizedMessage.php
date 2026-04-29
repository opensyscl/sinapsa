<?php

namespace App\Channels\DTO;

use App\Channels\Enums\ChannelType;
use App\Channels\Enums\MessageDirection;
use App\Channels\Enums\MessageType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * Mensaje normalizado, agnóstico al canal.
 * - Inbound: el adapter lo construye al parsear un webhook Meta.
 * - Outbound: el caller (API pública / dashboard) lo construye y el adapter lo envía.
 */
class NormalizedMessage extends Data
{
    public function __construct(
        public ChannelType $channel_type,
        public MessageDirection $direction,
        public MessageType $type,

        // Contacto destinatario (outbound) o emisor (inbound)
        public NormalizedContact $contact,

        // ID del thread del lado de Meta (= contacto.external_id en WhatsApp)
        public string $external_thread_id,

        // ISO 8601
        public string $timestamp,

        // Para inbound: wamid de Meta. Para outbound: lo rellena el adapter al enviar.
        public ?string $external_id = null,

        // Idempotency-Key del cliente externo (solo outbound, opcional)
        public ?string $client_idempotency_key = null,

        // Contenido — solo uno de los siguientes según `type`
        public ?string $body = null,                 // text | caption fallback
        public ?NormalizedMedia $media = null,       // image/audio/video/document/sticker
        public ?string $template_name = null,        // template
        public ?string $template_language = null,    // template
        public ?array $template_components = null,   // template
        public ?array $interactive_payload = null,   // interactive (botones/listas)
        public ?array $location = null,              // location { lat, lng, name?, address? }
        public ?string $reaction_emoji = null,       // reaction (apunta a external_id de otro msg)
        public ?string $reacted_to_external_id = null,

        // Snippet del payload original Meta para auditoría (no el webhook entero)
        public ?array $raw = null,
    ) {}
}
