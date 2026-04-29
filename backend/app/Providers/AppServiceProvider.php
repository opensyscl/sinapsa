<?php

namespace App\Providers;

use App\Models\ApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Guard `api-token` para la API pública del SaaS.
        // Lee `Authorization: Bearer sk_live_xxx`, valida hash + revocado + expiry,
        // y devuelve el ApiToken como Authenticatable. `auth()->user()->workspace_id`
        // funciona igual que con un User real.
        Auth::viaRequest('api-token', function (Request $request) {
            $bearer = $request->bearerToken();
            if (! $bearer || ! str_starts_with($bearer, 'sk_')) {
                return null;
            }

            $token = ApiToken::findByToken($bearer);
            if ($token) {
                $token->touchUsage($request->ip());
            }

            return $token;
        });
    }
}
