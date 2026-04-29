<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\IdempotencyKey;
use App\Http\Middleware\RequireApiScope;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            // Meta webhooks: sin middleware api (sin throttle, sin Sanctum).
            // La auth la hace HMAC dentro del controller.
            Route::prefix('webhooks')
                ->group(__DIR__.'/../routes/webhooks.php');
        },
    )
    // Reverb broadcasting auth montado bajo /api/broadcasting/auth con Sanctum bearer.
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        attributes: ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'scope' => RequireApiScope::class,
            'idempotency' => IdempotencyKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Renderiza ApiException como JSON Stripe-like en cualquier endpoint /api/*
        $exceptions->render(function (ApiException $e, Request $request) {
            if ($request->is('api/*')) {
                return $e->toResponse($request);
            }

            return null; // delega al render por defecto en otros contextos
        });

        // Auth failures en endpoints públicos /api/v1/* (api-token guard) → JSON 401 Stripe-like.
        // Sin esto, Laravel redirige al welcome (no hay /login backend) y devuelve HTML.
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            $isApiTokenGuard = in_array('api-token', $e->guards(), true);

            return response()->json([
                'error' => [
                    'type' => $isApiTokenGuard ? 'authentication_error' : 'authentication_error',
                    'code' => 'unauthenticated',
                    'message' => $isApiTokenGuard
                        ? 'Missing or invalid API key. Use a Bearer sk_live_xxx or sk_test_xxx token.'
                        : 'Unauthenticated.',
                ],
            ], 401);
        });
    })->create();
