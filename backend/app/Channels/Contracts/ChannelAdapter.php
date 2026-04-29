<?php

namespace App\Channels\Contracts;

use App\Channels\DTO\InboundParseResult;
use App\Channels\DTO\NormalizedMessage;
use App\Channels\DTO\SendResult;
use App\Channels\Enums\ChannelType;
use App\Models\Channel;

/**
 * Contrato común para los adapters de canal (WA Cloud, IG, FB Messenger…).
 *
 * Reglas de oro para implementadores:
 *  - El adapter ES el ÚNICO sitio en el código que conoce el shape de Meta.
 *  - Fuera del adapter SOLO circulan DTOs normalizados.
 *  - Si Meta cambia el contrato, solo este archivo se toca.
 */
interface ChannelAdapter
{
    /**
     * Tipo de canal que sirve este adapter.
     */
    public static function type(): ChannelType;

    /**
     * Verifica la firma HMAC del webhook entrante.
     * En Meta es `X-Hub-Signature-256: sha256=<hex>` y el secret es el APP_SECRET.
     */
    public function verifySignature(string $rawBody, ?string $signatureHeader): bool;

    /**
     * Resuelve el `channel_external_id` (= phone_number_id en WA, etc.) a partir
     * del payload, sin parsear nada más. Sirve para localizar el Channel y poder
     * resolver el workspace antes de meter el job en cola.
     */
    public function extractChannelExternalId(array $payload): ?string;

    /**
     * Parsea el payload de Meta a DTOs normalizados.
     * No persiste nada; solo transforma.
     */
    public function parseInbound(array $payload): InboundParseResult;

    /**
     * Envía un mensaje a Meta usando el access_token cifrado del Channel.
     * El adapter es responsable de:
     *  - construir el payload correcto para Meta
     *  - llamar a la Graph API
     *  - traducir errores Meta a SendResult con error_code/error_message
     */
    public function send(Channel $channel, NormalizedMessage $message): SendResult;
}
