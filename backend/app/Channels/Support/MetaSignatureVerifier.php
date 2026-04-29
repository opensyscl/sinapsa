<?php

namespace App\Channels\Support;

/**
 * Verifica la firma `X-Hub-Signature-256` que Meta añade a todos los webhooks.
 *
 * Header esperado:  X-Hub-Signature-256: sha256=<hex_hmac>
 * Donde hex_hmac = hash_hmac('sha256', raw_body, app_secret)
 */
class MetaSignatureVerifier
{
    /**
     * @param  string|null  $signatureHeader Valor crudo del header `X-Hub-Signature-256`.
     * @param  string  $rawBody El body crudo de la petición (no decodificado JSON).
     * @param  string  $appSecret App secret de Meta (config('sinapsa.meta.app_secret')).
     */
    public function verify(?string $signatureHeader, string $rawBody, string $appSecret): bool
    {
        // Dev sin app_secret configurado → aceptar para poder probar local
        // con `messages:simulate-webhook` antes de tener app real Meta.
        if ($appSecret === '') {
            return true;
        }

        if (! $signatureHeader || ! str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $appSecret);
        $provided = substr($signatureHeader, 7); // strip "sha256="

        return hash_equals($expected, $provided);
    }
}
