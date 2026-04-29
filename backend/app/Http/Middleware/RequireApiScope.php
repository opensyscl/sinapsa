<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;

/**
 * Middleware: chequea que el ApiToken activo tiene un scope concreto.
 *
 *   Route::post('/messages', ...)
 *       ->middleware(['auth:api-token', 'scope:messages:write']);
 *
 * Si el caller es un User humano (Sanctum), pasa sin chequeo —
 * los users del workspace tienen acceso completo por defecto.
 */
class RequireApiScope
{
    public function handle(Request $request, Closure $next, string $scope)
    {
        $user = $request->user();

        if (! $user) {
            throw ApiException::authentication('unauthenticated', 'No authenticated principal.');
        }

        // Sanctum users (humanos) bypass — su autorización va por roles más adelante
        if (! $user instanceof ApiToken) {
            return $next($request);
        }

        if (! $user->hasScope($scope)) {
            throw ApiException::permission(
                'missing_scope',
                "Token missing required scope [{$scope}].",
            );
        }

        return $next($request);
    }
}
