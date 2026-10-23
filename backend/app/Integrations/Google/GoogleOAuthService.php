<?php

namespace App\Integrations\Google;

use App\Models\ConnectSession;
use App\Models\Integration;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OAuth 2.0 de Google (web server flow) para Connect-as-a-Service.
 *
 * Sinapsa tiene UN solo OAuth client. Cada usuario final que conecta su cuenta
 * deja un refresh_token propio → una fila `integrations` en el workspace del
 * cliente SaaS. El `state` del OAuth es el JWT de la Connect Session.
 */
class GoogleOAuthService
{
    public const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    public const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    public const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';
    public const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    public function isConfigured(): bool
    {
        return filled(config('sinapsa.google.client_id')) && filled(config('sinapsa.google.client_secret'));
    }

    /**
     * @param  string[]  $products
     */
    public function authorizationUrl(string $state, array $products): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id' => config('sinapsa.google.client_id'),
            'redirect_uri' => config('sinapsa.google.redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(' ', $this->scopesFor($products)),
            // offline + consent: garantiza refresh_token también en reconexiones
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    /**
     * Canjea el code, lee la identidad de la cuenta y crea/actualiza la Integration.
     *
     * @param  string[]  $products
     */
    public function connect(ConnectSession $session, string $code, array $products): Integration
    {
        $tokens = $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => config('sinapsa.google.redirect_uri'),
        ]);

        $granted = explode(' ', (string) ($tokens['scope'] ?? ''));
        $missing = array_diff($this->scopesFor($products, withIdentity: false), $granted);
        if ($missing) {
            // El usuario puede desmarcar scopes en la pantalla de consentimiento
            throw new RuntimeException('Missing Google permissions: ' . implode(', ', $missing));
        }

        $profile = Http::withToken($tokens['access_token'])
            ->acceptJson()
            ->get(self::USERINFO_URL)
            ->throw()
            ->json();

        $integration = Integration::withoutGlobalScopes()->withTrashed()->firstOrNew([
            'workspace_id' => $session->workspace_id,
            'provider' => Integration::PROVIDER_GOOGLE,
            'external_id' => (string) $profile['sub'],
        ]);

        $integration->fill([
            'products' => array_values(array_unique(array_merge($integration->products ?? [], $products))),
            'email' => $profile['email'] ?? null,
            'display_name' => $profile['name'] ?? ($profile['email'] ?? null),
            'status' => Integration::STATUS_CONNECTED,
            'granted_scopes' => $granted,
            'token_expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600)),
            'last_error_code' => null,
            'last_error_message' => null,
            'client_metadata' => $session->client_metadata,
            'end_user_id' => $session->end_user_id,
        ]);
        $integration->setAccessToken($tokens['access_token']);
        // Google solo manda refresh_token en el primer consent; si no viene, conservamos el anterior
        if (! empty($tokens['refresh_token'])) {
            $integration->setRefreshToken($tokens['refresh_token']);
        }
        $integration->deleted_at = null;
        $integration->save();

        return $integration;
    }

    /**
     * Devuelve un access_token vigente, refrescándolo si vence en < 2 min.
     */
    public function accessToken(Integration $integration): string
    {
        $current = $integration->getAccessToken();
        if ($current && $integration->token_expires_at?->isAfter(now()->addMinutes(2))) {
            return $current;
        }

        $refresh = $integration->getRefreshToken();
        if (! $refresh) {
            $this->markError($integration, 'missing_refresh_token', 'No refresh token stored. Reconnect the account.');
            throw new GoogleReauthRequired('Google account must be reconnected.');
        }

        try {
            $tokens = $this->tokenRequest([
                'grant_type' => 'refresh_token',
                'refresh_token' => $refresh,
            ]);
        } catch (GoogleReauthRequired $e) {
            $this->markError($integration, 'invalid_grant', $e->getMessage());
            throw $e;
        }

        $integration->setAccessToken($tokens['access_token']);
        $integration->token_expires_at = now()->addSeconds((int) ($tokens['expires_in'] ?? 3600));
        $integration->save();

        return $tokens['access_token'];
    }

    public function revoke(Integration $integration): void
    {
        $token = $integration->getRefreshToken() ?? $integration->getAccessToken();
        if ($token) {
            // Best effort: si Google ya lo invalidó, seguimos con la desconexión local
            Http::asForm()->post(self::REVOKE_URL, ['token' => $token]);
        }
    }

    /**
     * @param  string[]  $products
     * @return string[]
     */
    public function scopesFor(array $products, bool $withIdentity = true): array
    {
        $scopes = $withIdentity ? ['openid', 'email', 'profile'] : [];
        foreach ($products as $product) {
            $scopes = array_merge($scopes, config("sinapsa.google.scopes.{$product}", []));
        }

        return array_values(array_unique($scopes));
    }

    private function tokenRequest(array $params): array
    {
        $response = Http::asForm()->acceptJson()->post(self::TOKEN_URL, $params + [
            'client_id' => config('sinapsa.google.client_id'),
            'client_secret' => config('sinapsa.google.client_secret'),
        ]);

        if ($response->json('error') === 'invalid_grant') {
            throw new GoogleReauthRequired('Google refused the grant (revoked or expired).');
        }

        return $response->throw()->json();
    }

    private function markError(Integration $integration, string $code, string $message): void
    {
        $integration->forceFill([
            'status' => Integration::STATUS_ERROR,
            'last_error_code' => $code,
            'last_error_message' => $message,
        ])->save();
    }
}
