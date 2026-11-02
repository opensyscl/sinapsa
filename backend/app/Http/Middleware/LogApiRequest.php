<?php

namespace App\Http\Middleware;

use App\Models\ApiRequestLog;
use App\Models\ApiToken;
use App\Models\Message;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Guarda cada llamada a la API pública en `api_request_logs`, ligada al canal
 * o integración que toca, para que el cliente la vea en el panel → Registros.
 * Nunca rompe la petición: si el log falla, se ignora.
 */
class LogApiRequest
{
    /** Tamaño máximo guardado por body (bytes de JSON). */
    private const MAX_BODY = 16_000;

    /** Campos que nunca se guardan. */
    private const REDACT = ['password', 'access_token', 'refresh_token', 'code', 'token', 'session_token', 'secret'];

    public function handle(Request $request, Closure $next): Response
    {
        $start = hrtime(true);
        $response = $next($request);

        try {
            $this->record($request, $response, (int) ((hrtime(true) - $start) / 1_000_000));
        } catch (Throwable $e) {
            report($e);
        }

        return $response;
    }

    private function record(Request $request, Response $response, int $ms): void
    {
        $caller = $request->user();
        if (! $caller || ! $caller->workspace_id) {
            return;
        }

        $responseBody = json_decode((string) $response->getContent(), true);

        ApiRequestLog::create([
            'workspace_id' => $caller->workspace_id,
            'api_token_id' => $caller instanceof ApiToken ? $caller->id : null,
            'user_id' => $caller instanceof ApiToken ? null : $caller->id,
            'channel_id' => $this->channelId($request, $responseBody),
            'integration_id' => $this->integrationId($request),
            'method' => $request->method(),
            'path' => substr('/'.$request->path(), 0, 512),
            'route_name' => $request->route()?->getName(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $ms,
            'error_code' => is_array($responseBody) ? data_get($responseBody, 'error.code') : null,
            'ip' => $request->ip(),
            'request_body' => $this->clip($request->all() + ($request->query() ? ['_query' => $request->query()] : [])),
            'response_body' => is_array($responseBody) ? $this->clip($responseBody) : null,
            'created_at' => now(),
        ]);
    }

    private function channelId(Request $request, mixed $responseBody): ?int
    {
        $message = $request->route('message');
        if ($message instanceof Message) {
            return $message->channel_id;
        }

        $id = $request->input('channel_id') ?? (is_array($responseBody) ? data_get($responseBody, 'channel_id') : null);

        return is_numeric($id) ? (int) $id : null;
    }

    private function integrationId(Request $request): ?int
    {
        $id = $request->route('integration');

        return is_numeric($id) ? (int) $id : null;
    }

    private function clip(array $data): ?array
    {
        if ($data === []) {
            return null;
        }

        $data = $this->redact($data);
        $json = json_encode($data);

        return strlen((string) $json) > self::MAX_BODY ? ['_truncated' => true, 'preview' => substr((string) $json, 0, self::MAX_BODY)] : $data;
    }

    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::REDACT, true)) {
                $data[$key] = '[oculto]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }
}
