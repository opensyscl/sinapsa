<?php

namespace App\Channels\WhatsAppCloud\Services;

use App\Channels\Enums\ChannelType;
use App\Channels\Support\MetaGraphClient;
use App\Models\Channel;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Orquesta el flujo Embedded Signup de WhatsApp Cloud:
 *  1. Exchange code (corto, del popup Meta) → user access token (60s ~).
 *  2. Exchange user → system user access token (60d, "long-lived").
 *  3. POST /{phone_number_id}/register para activar el número en WhatsApp Cloud.
 *  4. POST /{waba_id}/subscribed_apps para que Meta dispare webhooks a nuestra app.
 *  5. Sync inicial de plantillas (GET /{waba_id}/message_templates).
 *
 * Pre-requisitos en Meta Business Manager:
 *  - App de Meta tipo Business (developers.facebook.com).
 *  - WhatsApp product añadido a la app.
 *  - Sinapsa registrada como Tech Provider verificado.
 *  - Embedded Signup config_id creado en la app (extras.config_id en el popup).
 *
 * Antes de App Review (modo dev), Meta solo deja conectar números de prueba (50 contactos cap).
 */
class MetaEmbeddedSignupService
{
    public function __construct(
        protected MetaGraphClient $graph,
    ) {}

    /**
     * Conecta un canal WhatsApp Cloud usando el `code` que Meta devolvió en el popup.
     *
     * @return Channel  con status='connected' y access_token cifrado.
     */
    public function connect(
        int $workspaceId,
        string $code,
        string $phoneNumberId,
        string $wabaId,
        ?string $displayName = null,
    ): Channel {
        $appId = (string) config('sinapsa.meta.app_id');
        $appSecret = (string) config('sinapsa.meta.app_secret');
        if ($appId === '' || $appSecret === '') {
            throw new RuntimeException(
                'META_APP_ID / META_APP_SECRET no configurados. Para dev usa connectManual().'
            );
        }

        // 1) code → user access token
        $userToken = $this->exchangeCodeForUserToken($code, $appId, $appSecret);

        // 2) user → system user (60d). En Embedded Signup, Meta expone
        //    `oauth/access_token?grant_type=fb_exchange_token` con fb_exchange_token=$userToken
        $systemToken = $this->exchangeForSystemToken($userToken, $appId, $appSecret);

        // 3) registrar número y 4) suscribir webhooks
        $this->registerPhoneNumber($systemToken, $phoneNumberId);
        $this->subscribeWebhooks($systemToken, $wabaId);

        // 5) crear/actualizar Channel
        $channel = Channel::withoutGlobalScopes()->updateOrCreate(
            [
                'workspace_id' => $workspaceId,
                'type' => ChannelType::WhatsApp->value,
                'external_id' => $phoneNumberId,
            ],
            [
                'display_name' => $displayName ?? "WhatsApp {$phoneNumberId}",
                'meta_business_id' => $wabaId,
                'status' => Channel::STATUS_CONNECTED,
                'token_expires_at' => now()->addDays(60),
                'webhook_subscribed_at' => now(),
                'last_health_check_at' => now(),
                'last_error_code' => null,
                'last_error_message' => null,
                'config' => [
                    'connected_via' => 'embedded_signup',
                    'connected_at' => now()->toIso8601String(),
                ],
            ],
        );
        $channel->setAccessToken($systemToken);
        $channel->save();

        return $channel;
    }

    /**
     * Connect manual (dev only): el caller proporciona directamente el access_token.
     * Útil antes de pasar Meta App Review o para integradores que ya tienen su token.
     */
    public function connectManual(
        int $workspaceId,
        string $accessToken,
        string $phoneNumberId,
        string $wabaId,
        ?string $displayName = null,
        bool $skipMetaCalls = false,
    ): Channel {
        if (! $skipMetaCalls) {
            // Intentamos registrar/suscribir, pero NO fallamos si Meta rechaza
            // (puede que ya estuviera registrado o que sea token de prueba).
            try {
                $this->registerPhoneNumber($accessToken, $phoneNumberId);
            } catch (\Throwable $e) {
                Log::info('connect_manual.register_skipped', ['error' => $e->getMessage()]);
            }
            try {
                $this->subscribeWebhooks($accessToken, $wabaId);
            } catch (\Throwable $e) {
                Log::info('connect_manual.subscribe_skipped', ['error' => $e->getMessage()]);
            }
        }

        $channel = Channel::withoutGlobalScopes()->updateOrCreate(
            [
                'workspace_id' => $workspaceId,
                'type' => ChannelType::WhatsApp->value,
                'external_id' => $phoneNumberId,
            ],
            [
                'display_name' => $displayName ?? "WhatsApp {$phoneNumberId}",
                'meta_business_id' => $wabaId,
                'status' => Channel::STATUS_CONNECTED,
                // 60d desde ahora — el caller debería refrescar manual antes de eso
                'token_expires_at' => now()->addDays(60),
                'webhook_subscribed_at' => now(),
                'last_health_check_at' => now(),
                'last_error_code' => null,
                'last_error_message' => null,
                'config' => [
                    'connected_via' => 'manual',
                    'connected_at' => now()->toIso8601String(),
                ],
            ],
        );
        $channel->setAccessToken($accessToken);
        $channel->save();

        return $channel;
    }

    /**
     * Refresca el system user token. En realidad WhatsApp Cloud system user tokens
     * NO se refrescan: hay que generar uno nuevo desde el Business Manager. Pero
     * podemos validar que el token actual sigue vivo y, si caduca, marcar el canal
     * como `error` para que el operador re-conecte.
     */
    public function healthCheck(Channel $channel): bool
    {
        $token = $channel->getAccessToken();
        if (! $token) {
            $channel->forceFill([
                'status' => Channel::STATUS_ERROR,
                'last_error_code' => 'no_token',
                'last_error_message' => 'No access_token stored',
                'last_health_check_at' => now(),
            ])->save();

            return false;
        }

        $response = $this->graph->withAccessToken($token)
            ->get("/{$channel->external_id}");

        $ok = $response->successful();
        $channel->forceFill([
            'status' => $ok ? Channel::STATUS_CONNECTED : Channel::STATUS_ERROR,
            'last_health_check_at' => now(),
            'last_error_code' => $ok ? null : (string) data_get($response->json(), 'error.code', $response->status()),
            'last_error_message' => $ok ? null : (string) data_get($response->json(), 'error.message'),
        ])->save();

        return $ok;
    }

    // ─────────────────────────────────────────────────────────────
    // Internals
    // ─────────────────────────────────────────────────────────────

    protected function exchangeCodeForUserToken(string $code, string $appId, string $appSecret): string
    {
        $base = (string) config('sinapsa.meta.graph_url');
        $resp = Http::timeout(10)->get("{$base}/oauth/access_token", [
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'code' => $code,
            'redirect_uri' => '', // Embedded Signup no usa redirect_uri pero algunos endpoints lo piden vacío
        ]);
        $this->throwIfFailed($resp, 'oauth.exchange_code');

        return (string) data_get($resp->json(), 'access_token');
    }

    protected function exchangeForSystemToken(string $userToken, string $appId, string $appSecret): string
    {
        $base = (string) config('sinapsa.meta.graph_url');
        $resp = Http::timeout(10)->get("{$base}/oauth/access_token", [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'fb_exchange_token' => $userToken,
        ]);
        $this->throwIfFailed($resp, 'oauth.exchange_long_lived');

        return (string) data_get($resp->json(), 'access_token');
    }

    protected function registerPhoneNumber(string $accessToken, string $phoneNumberId): void
    {
        $resp = $this->graph->withAccessToken($accessToken)
            ->post("/{$phoneNumberId}/register", [
                'messaging_product' => 'whatsapp',
                // PIN: en flujo real el usuario lo introduce. Para un número de prueba
                // o ya registrado anteriormente, Meta acepta el reuso. Si Meta exige,
                // habrá que prompt-earlo en el frontend.
                'pin' => '000000',
            ]);
        if (! $resp->successful()) {
            $code = data_get($resp->json(), 'error.code');
            // 100 / 133010 / etc → puede ser que ya esté registrado
            if (in_array((int) $code, [100, 133010, 133011], true)) {
                Log::info('whatsapp.register.already_registered', [
                    'phone_number_id' => $phoneNumberId,
                ]);

                return;
            }
            $this->throwIfFailed($resp, 'whatsapp.register');
        }
    }

    protected function subscribeWebhooks(string $accessToken, string $wabaId): void
    {
        $resp = $this->graph->withAccessToken($accessToken)
            ->post("/{$wabaId}/subscribed_apps");

        $this->throwIfFailed($resp, 'whatsapp.subscribe_webhooks');
    }

    protected function throwIfFailed(Response $resp, string $context): void
    {
        if ($resp->successful()) {
            return;
        }
        $err = $resp->json('error', []);
        Log::warning("meta.{$context}.failed", [
            'status' => $resp->status(),
            'error' => $err,
        ]);
        throw new RuntimeException(
            "Meta {$context} failed [" . ($err['code'] ?? $resp->status()) . ']: ' .
                ($err['message'] ?? 'Unknown')
        );
    }
}
