<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Idempotency-Key — solo aplica a POSTs de la API pública.
 *
 *   Cliente envía:  Idempotency-Key: order-12345-welcome
 *   Si vimos esa key en las últimas 24h por este token → devolvemos la respuesta cacheada.
 *
 * Usamos Redis (cache_store=redis) con TTL = config('sinapsa.idempotency.ttl_seconds').
 * El namespace incluye el ApiToken id para que dos workspaces distintos puedan usar
 * la misma key sin colisión.
 */
class IdempotencyKey
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->method() !== 'POST') {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');
        if (! $key) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user instanceof ApiToken) {
            return $next($request);
        }

        $cacheKey = "idempotency:{$user->id}:{$key}";
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return response($cached['body'], $cached['status'])
                ->withHeaders(array_merge(
                    $cached['headers'] ?? [],
                    ['Idempotent-Replayed' => 'true'],
                ));
        }

        $response = $next($request);

        // Solo cacheamos respuestas exitosas (2xx) y errores cliente intencionales (4xx)
        // que no son temporales (excluimos 429). 5xx no las cacheamos para que un retry
        // legítimo del cliente pueda reintentarse.
        $status = $response->getStatusCode();
        if (($status >= 200 && $status < 300) || ($status >= 400 && $status < 500 && $status !== 429)) {
            Cache::put(
                $cacheKey,
                [
                    'status' => $status,
                    'body' => $response->getContent(),
                    'headers' => ['Content-Type' => $response->headers->get('Content-Type', 'application/json')],
                ],
                (int) config('sinapsa.idempotency.ttl_seconds', 86400),
            );
        }

        return $response;
    }
}
