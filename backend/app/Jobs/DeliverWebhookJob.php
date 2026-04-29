<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Webhooks\WebhookSigner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as QueueableTrait;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Entrega un WebhookDelivery a la URL del cliente.
 *
 * Backoff manual (no Laravel retry): persistimos el siguiente intento en
 * `next_attempt_at` y reencolamos con `delay()`. Nos da control total del
 * total de intentos y el listado en UI muestra el progreso.
 *
 * Política:
 *  - Backoffs: 1m, 5m, 30m, 2h, 12h, 24h. Tras eso → status='dead' (DLQ).
 *  - 2xx → delivered, endpoint.consecutive_failures = 0.
 *  - 4xx (excepto 429) → marcar dead inmediatamente — el cliente devolvió un error
 *    intencional (URL inválida, body mal parseado…). Reintentar es ruido.
 *  - 5xx / 429 / network error → reintentar.
 *  - Tras 6 fallos consecutivos del endpoint → endpoint.status='failing' (pero
 *    sigue recibiendo nuevos deliveries; la UI muestra alerta para el operador).
 */
class DeliverWebhookJob implements ShouldQueue
{
    use QueueableTrait, Queueable;

    /** Backoffs por intento, en segundos. attempt 1 → 60s, 2 → 5m, etc. */
    public const BACKOFFS = [60, 300, 1800, 7200, 43200, 86400];

    public int $tries = 1; // No usamos retry de Laravel — manejamos el backoff manualmente.

    public function __construct(public int $deliveryId) {}

    public function handle(WebhookSigner $signer): void
    {
        $delivery = WebhookDelivery::withoutGlobalScopes()->find($this->deliveryId);
        if (! $delivery || $delivery->status === WebhookDelivery::STATUS_DELIVERED || $delivery->status === WebhookDelivery::STATUS_DEAD) {
            return;
        }

        $endpoint = WebhookEndpoint::withoutGlobalScopes()->find($delivery->endpoint_id);
        if (! $endpoint || $endpoint->status === WebhookEndpoint::STATUS_PAUSED) {
            $delivery->forceFill([
                'status' => WebhookDelivery::STATUS_DEAD,
                'error_message' => 'Endpoint paused or removed',
                'failed_at' => now(),
            ])->save();

            return;
        }

        $secret = $endpoint->getSecret();
        if (! $secret) {
            $delivery->forceFill([
                'status' => WebhookDelivery::STATUS_DEAD,
                'error_message' => 'Endpoint has no secret',
                'failed_at' => now(),
            ])->save();

            return;
        }

        $rawBody = json_encode($delivery->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = $signer->sign($rawBody, $secret);

        $deliveryToken = 'wd_' . Str::ulid();
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'Sinapsa-Webhooks/1.0',
            'X-Sinapsa-Signature' => $signature,
            'X-Sinapsa-Event' => $delivery->event_type,
            'X-Sinapsa-Event-Id' => $delivery->event_id,
            'X-Sinapsa-Delivery' => $deliveryToken,
            'X-Sinapsa-Workspace' => (string) $delivery->workspace_id,
        ];

        $delivery->increment('attempt');

        try {
            $response = Http::withHeaders($headers)
                ->withBody($rawBody, 'application/json')
                ->timeout(10)
                ->connectTimeout(5)
                ->send('POST', $endpoint->url);

            $status = $response->status();
            $body = $response->body();

            $update = [
                'response_status' => $status,
                'response_headers' => collect($response->headers())
                    ->map(fn ($v) => is_array($v) && count($v) === 1 ? $v[0] : $v)
                    ->all(),
                'response_body' => substr($body, 0, 4096),
            ];

            if ($status >= 200 && $status < 300) {
                $delivery->forceFill($update + [
                    'status' => WebhookDelivery::STATUS_DELIVERED,
                    'delivered_at' => now(),
                    'next_attempt_at' => null,
                    'error_message' => null,
                ])->save();

                $endpoint->recordSuccess();

                return;
            }

            // 4xx (excepto 429) → muerto inmediato, no reintentar
            if ($status >= 400 && $status < 500 && $status !== 429) {
                $delivery->forceFill($update + [
                    'status' => WebhookDelivery::STATUS_DEAD,
                    'failed_at' => now(),
                    'error_message' => "Client error HTTP {$status}",
                ])->save();
                $endpoint->recordFailure();

                return;
            }

            // 5xx o 429: reintenta
            $this->scheduleRetry($delivery, "HTTP {$status}", $update, $endpoint);
        } catch (Throwable $e) {
            Log::warning('webhook.delivery.network_error', [
                'delivery_id' => $delivery->id,
                'attempt' => $delivery->attempt,
                'error' => $e->getMessage(),
            ]);

            $this->scheduleRetry($delivery, "Network: {$e->getMessage()}", [], $endpoint);
        }
    }

    protected function scheduleRetry(
        WebhookDelivery $delivery,
        string $errorMessage,
        array $update,
        WebhookEndpoint $endpoint,
    ): void {
        $endpoint->recordFailure();

        // attempt ya incrementado antes del request (1..N).
        $idx = $delivery->attempt - 1;
        $hasRetriesLeft = isset(self::BACKOFFS[$idx]);

        if (! $hasRetriesLeft) {
            $delivery->forceFill($update + [
                'status' => WebhookDelivery::STATUS_DEAD,
                'failed_at' => now(),
                'error_message' => $errorMessage . ' (retries exhausted)',
                'next_attempt_at' => null,
            ])->save();

            return;
        }

        $backoff = self::BACKOFFS[$idx];
        $next = now()->addSeconds($backoff);

        $delivery->forceFill($update + [
            'status' => WebhookDelivery::STATUS_FAILING,
            'error_message' => $errorMessage,
            'next_attempt_at' => $next,
        ])->save();

        self::dispatch($delivery->id)
            ->onQueue(config('sinapsa.queues.webhooks_out'))
            ->delay($next);
    }
}
