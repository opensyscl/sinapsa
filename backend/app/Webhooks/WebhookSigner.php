<?php

namespace App\Webhooks;

/**
 * Firma de webhooks salientes estilo Stripe:
 *
 *   X-Sinapsa-Signature: t=1714210800,v1=<hex_hmac_sha256>
 *
 * Donde:
 *   - t es el unix timestamp (segundos) cuando se generó la firma
 *   - v1 es HMAC_SHA256(secret, "{t}.{raw_body}")
 *
 * El cliente debe:
 *   1) Parsear t y v1.
 *   2) Calcular hash con su copia del secret + el body crudo recibido.
 *   3) Comparar con `hash_equals` (timing-safe).
 *   4) Rechazar si t es muy antiguo (>5 min) → mitiga replay attacks.
 */
class WebhookSigner
{
    public function sign(string $rawBody, string $secret, ?int $timestamp = null): string
    {
        $t = $timestamp ?? time();
        $signedPayload = "{$t}.{$rawBody}";
        $v1 = hash_hmac('sha256', $signedPayload, $secret);

        return "t={$t},v1={$v1}";
    }
}
