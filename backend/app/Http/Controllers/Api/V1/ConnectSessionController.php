<?php

namespace App\Http\Controllers\Api\V1;

use App\Channels\Enums\ChannelType;
use App\Channels\WhatsAppCloud\Services\MetaEmbeddedSignupService;
use App\Connect\ConnectSessionTokenService;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChannelResource;
use App\Http\Resources\ConnectSessionResource;
use App\Models\ApiToken;
use App\Models\ConnectSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Connect-as-a-Service API.
 *
 * Sinapsa actúa como Tech Provider ante Meta. Sus clientes SaaS embeben un
 * botón "Conectar WhatsApp" en sus apps; ese botón abre la Hosted Connect
 * Page de Sinapsa, que dispara el Embedded Signup de Meta usando MI App ID.
 * El número conectado queda en el workspace del cliente SaaS.
 *
 * Endpoints:
 *  - POST /api/v1/connect-sessions
 *      Auth: sk_live_ con scope channels:write.
 *      Body: { allowed_channel_types?, display_label?, return_url?, client_metadata? }
 *      → { session_token, hosted_url, expires_at, session: {...} }
 *
 *  - GET /api/v1/connect-sessions/{token}/info
 *      Público (auth via JWT en path).
 *      Lo consume la Hosted Connect Page para mostrar la UI inicial.
 *      → { session: {...}, meta_app_id, embedded_signup_config_id }
 *
 *  - POST /api/v1/connect-sessions/{token}/complete
 *      Público (auth via JWT en path).
 *      Body (WA): { code, phone_number_id, waba_id, channel_type: "whatsapp" }
 *      Ejecuta el Embedded Signup, persiste el Channel y marca la sesión completed.
 *      → { channel: {...}, session: {...} }
 */
class ConnectSessionController extends Controller
{
    public function __construct(
        protected ConnectSessionTokenService $tokens,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'allowed_channel_types' => ['nullable', 'array'],
            'allowed_channel_types.*' => [Rule::in([
                ChannelType::WhatsApp->value,
                ChannelType::Instagram->value,
                ChannelType::Messenger->value,
            ])],
            'display_label' => ['nullable', 'string', 'max:120'],
            'return_url' => ['nullable', 'url', 'max:512'],
            'client_metadata' => ['nullable', 'array'],
        ]);

        $caller = $request->user();
        $apiTokenId = $caller instanceof ApiToken ? $caller->id : null;

        $session = ConnectSession::create([
            'workspace_id' => $caller->workspace_id,
            'api_token_id' => $apiTokenId,
            'jti' => ConnectSessionTokenService::newJti(),
            'allowed_channel_types' => $data['allowed_channel_types'] ?? [ChannelType::WhatsApp->value],
            'display_label' => $data['display_label'] ?? null,
            'return_url' => $data['return_url'] ?? null,
            'client_metadata' => $data['client_metadata'] ?? null,
            'status' => ConnectSession::STATUS_PENDING,
            'expires_at' => ConnectSessionTokenService::defaultExpiry(),
            'client_ip' => $request->ip(),
            'client_user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);

        $jwt = $this->tokens->issue($session);

        return response()->json([
            'session_token' => $jwt,
            'hosted_url' => rtrim((string) config('app.frontend_url'), '/') . '/connect?token=' . $jwt,
            'expires_at' => $session->expires_at->toIso8601String(),
            'session' => new ConnectSessionResource($session),
        ], 201);
    }

    /**
     * Endpoint público para la Hosted Connect Page.
     */
    public function info(string $token): JsonResponse
    {
        $session = $this->resolveSession($token);

        return response()->json([
            'session' => new ConnectSessionResource($session),
            'meta' => [
                'app_id' => config('sinapsa.meta.app_id'),
                'graph_version' => config('sinapsa.meta.graph_version'),
                'wa_embedded_signup_config_id' => config('sinapsa.meta.wa_embedded_signup_config_id'),
            ],
        ]);
    }

    /**
     * Completa la sesión usando los IDs que Meta devolvió en el popup.
     * Ejecuta el flujo Embedded Signup ya implementado y persiste el Channel.
     */
    public function complete(Request $request, string $token, MetaEmbeddedSignupService $service): JsonResponse
    {
        $session = $this->resolveSession($token);

        $data = $request->validate([
            'channel_type' => ['required', Rule::in([
                ChannelType::WhatsApp->value,
                ChannelType::Instagram->value,
                ChannelType::Messenger->value,
            ])],
            'code' => ['required_if:channel_type,whatsapp', 'string'],
            'phone_number_id' => ['required_if:channel_type,whatsapp', 'string'],
            'waba_id' => ['required_if:channel_type,whatsapp', 'string'],
            'display_name' => ['nullable', 'string', 'max:120'],
        ]);

        if (! in_array($data['channel_type'], $session->allowed_channel_types, true)) {
            throw ApiException::permission(
                'channel_type_not_allowed',
                "This connect session is not allowed to connect [{$data['channel_type']}].",
            );
        }

        try {
            // De momento solo WhatsApp tiene Embedded Signup automatizado.
            // IG / Messenger Embedded Signup se añade en una iteración posterior.
            if ($data['channel_type'] !== ChannelType::WhatsApp->value) {
                throw ApiException::invalidRequest(
                    'channel_type_not_implemented',
                    "Embedded Signup for [{$data['channel_type']}] is not implemented yet.",
                    'channel_type',
                );
            }

            $channel = $service->connect(
                workspaceId: $session->workspace_id,
                code: $data['code'],
                phoneNumberId: $data['phone_number_id'],
                wabaId: $data['waba_id'],
                displayName: $data['display_name'] ?? null,
            );

            $session->forceFill([
                'status' => ConnectSession::STATUS_COMPLETED,
                'completed_at' => now(),
                'completed_channel_id' => $channel->id,
            ])->save();

            return response()->json([
                'channel' => new ChannelResource($channel),
                'session' => new ConnectSessionResource($session->refresh()),
            ]);
        } catch (Throwable $e) {
            $session->forceFill([
                'status' => ConnectSession::STATUS_FAILED,
                'error_message' => substr($e->getMessage(), 0, 500),
            ])->save();

            if ($e instanceof ApiException) {
                throw $e;
            }
            throw ApiException::invalidRequest('connect_failed', $e->getMessage());
        }
    }

    /**
     * Decodifica el JWT y carga el ConnectSession DB. Lanza si:
     *  - JWT inválido / firma incorrecta / expirado.
     *  - jti no encontrado en DB.
     *  - status != pending.
     *  - DB expires_at en el pasado (defensa en profundidad).
     */
    protected function resolveSession(string $token): ConnectSession
    {
        try {
            $claims = $this->tokens->decode($token);
        } catch (Throwable $e) {
            throw ApiException::authentication('invalid_session_token', $e->getMessage());
        }

        $session = ConnectSession::where('jti', $claims['jti'] ?? '__missing__')->first();
        if (! $session) {
            throw ApiException::authentication('session_not_found', 'Connect session not found.');
        }

        if ($session->status === ConnectSession::STATUS_COMPLETED) {
            throw ApiException::invalidRequest(
                'session_already_completed',
                'This connect session has already been completed.',
            );
        }
        if ($session->status === ConnectSession::STATUS_EXPIRED || $session->expires_at->isPast()) {
            $session->forceFill(['status' => ConnectSession::STATUS_EXPIRED])->save();
            throw ApiException::authentication('session_expired', 'Connect session expired.');
        }

        return $session;
    }
}
