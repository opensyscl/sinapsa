<?php

namespace App\Channels\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente HTTP fino sobre Meta Graph API.
 *
 * Responsabilidades:
 *  - Bearer token desde Channel
 *  - Retry de errores transitorios (5xx + 429) con backoff
 *  - Logging del raw response para debugging (sin tokens)
 *  - Devolver Response cruda — el adapter interpreta cada error code
 */
class MetaGraphClient
{
    public function __construct(
        protected ?string $baseUrl = null,
    ) {
        $this->baseUrl ??= config('sinapsa.meta.graph_url');
    }

    public function withAccessToken(string $token): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->connectTimeout(5)
            ->retry(2, 250, function (\Throwable $e, PendingRequest $r) {
                // Solo retry de 5xx y 429
                if (! method_exists($e, 'response') || ! $e->response) {
                    return true; // network error → retry
                }
                $status = $e->response->status();

                return $status >= 500 || $status === 429;
            }, throw: false);
    }

    /**
     * POST {phone_number_id}/messages para WhatsApp Cloud.
     */
    public function sendWhatsAppMessage(string $accessToken, string $phoneNumberId, array $payload): Response
    {
        $response = $this->withAccessToken($accessToken)
            ->post("/{$phoneNumberId}/messages", $payload);

        Log::info('meta.graph.send', [
            'phone_number_id' => $phoneNumberId,
            'status' => $response->status(),
            'request_payload' => $payload,
            'response_body' => $response->json(),
        ]);

        return $response;
    }
}
