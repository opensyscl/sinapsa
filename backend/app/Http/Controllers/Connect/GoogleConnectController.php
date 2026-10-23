<?php

namespace App\Http\Controllers\Connect;

use App\Connect\ConnectSessionTokenService;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Integrations\Google\GoogleOAuthService;
use App\Integrations\IntegrationType;
use App\Models\ConnectSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Conexión de cuentas Google desde la Hosted Connect Page.
 *
 *  - GET /api/v1/connect-sessions/{token}/google/authorize?type=google_calendar
 *      Público (JWT en path). → { url } — la hosted page navega a esa URL (mismo popup).
 *
 *  - GET /api/oauth/google/callback?code=...&state=<jwt>
 *      Google redirige aquí. Crea la Integration, completa la sesión y vuelve a
 *      `/connect/done` del frontend, que avisa al window.opener y cierra.
 */
class GoogleConnectController extends Controller
{
    public function __construct(
        protected ConnectSessionTokenService $tokens,
        protected GoogleOAuthService $google,
    ) {}

    public function authorizationUrl(Request $request, string $token): JsonResponse
    {
        $session = $this->tokens->resolve($token);
        $type = $this->allowedType($session, (string) $request->query('type', IntegrationType::GoogleCalendar->value));

        if (! $this->google->isConfigured()) {
            throw ApiException::invalidRequest(
                'google_not_configured',
                'Google OAuth is not configured on this Sinapsa instance (GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET).',
            );
        }

        return response()->json([
            'url' => $this->google->authorizationUrl($token, [$type->product()]),
        ]);
    }

    public function callback(Request $request): RedirectResponse
    {
        $state = (string) $request->query('state', '');

        try {
            $session = $this->tokens->resolve($state);
        } catch (ApiException $e) {
            return $this->done(['status' => 'error', 'message' => $e->getMessage()]);
        }

        // El usuario cerró o rechazó el consentimiento: la sesión sigue usable
        if ($request->filled('error')) {
            return $this->done([
                'status' => $request->query('error') === 'access_denied' ? 'cancelled' : 'error',
                'message' => (string) $request->query('error'),
            ], $session);
        }

        $type = IntegrationType::GoogleCalendar;

        try {
            $integration = $this->google->connect($session, (string) $request->query('code'), [$type->product()]);
        } catch (Throwable $e) {
            Log::warning('google_connect_failed', ['session_id' => $session->id, 'error' => $e->getMessage()]);
            $session->forceFill([
                'status' => ConnectSession::STATUS_FAILED,
                'error_message' => substr($e->getMessage(), 0, 500),
            ])->save();

            return $this->done(['status' => 'error', 'message' => 'Could not connect the Google account.'], $session);
        }

        $session->forceFill([
            'status' => ConnectSession::STATUS_COMPLETED,
            'completed_at' => now(),
            'completed_integration_id' => $integration->id,
        ])->save();

        return $this->done([
            'status' => 'connected',
            'type' => $type->value,
            'integration_id' => $integration->id,
            'email' => $integration->email,
        ], $session);
    }

    private function allowedType(ConnectSession $session, string $value): IntegrationType
    {
        $type = IntegrationType::tryFrom($value);
        if (! $type || ! in_array($type->value, $session->allowed_channel_types, true)) {
            throw ApiException::permission(
                'channel_type_not_allowed',
                "This connect session is not allowed to connect [{$value}].",
            );
        }

        return $type;
    }

    private function done(array $params, ?ConnectSession $session = null): RedirectResponse
    {
        $params['return_url'] = $session?->return_url;

        return redirect()->away(
            rtrim((string) config('sinapsa.connect.hosted_url_base'), '/') . '/connect/done?' . http_build_query(array_filter($params)),
        );
    }
}
