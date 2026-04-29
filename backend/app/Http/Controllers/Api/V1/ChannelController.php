<?php

namespace App\Http\Controllers\Api\V1;

use App\Channels\Enums\ChannelType;
use App\Channels\WhatsAppCloud\Services\MetaEmbeddedSignupService;
use App\Channels\WhatsAppCloud\Services\WhatsAppTemplateSyncService;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChannelResource;
use App\Http\Resources\WaTemplateResource;
use App\Jobs\SyncWaTemplatesJob;
use App\Models\Channel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChannelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $channels = Channel::query()
            ->withCount('templates')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => ChannelResource::collection($channels),
        ]);
    }

    public function show(Channel $channel): JsonResponse
    {
        return response()->json([
            'channel' => new ChannelResource($channel->loadCount('templates')),
        ]);
    }

    /**
     * Embedded Signup real (frontend pasa `code` del popup Meta).
     * Requiere META_APP_ID y META_APP_SECRET configurados.
     */
    public function connectWhatsApp(Request $request, MetaEmbeddedSignupService $service): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'phone_number_id' => ['required', 'string'],
            'waba_id' => ['required', 'string'],
            'display_name' => ['nullable', 'string', 'max:120'],
        ]);

        $channel = $service->connect(
            workspaceId: $request->user()->workspace_id,
            code: $data['code'],
            phoneNumberId: $data['phone_number_id'],
            wabaId: $data['waba_id'],
            displayName: $data['display_name'] ?? null,
        );

        // Sync inicial async — no bloquear la response
        SyncWaTemplatesJob::dispatch($channel->id)->onQueue(config('sinapsa.queues.inbound'));

        return response()->json([
            'channel' => new ChannelResource($channel),
        ], 201);
    }

    /**
     * Connect manual (DEV only) para integradores que ya tienen access_token,
     * o para probar antes de pasar Meta App Review.
     */
    public function connectWhatsAppManual(Request $request, MetaEmbeddedSignupService $service): JsonResponse
    {
        abort_unless(app()->environment(['local', 'staging']), 404);

        $data = $request->validate([
            'access_token' => ['required', 'string', 'min:20'],
            'phone_number_id' => ['required', 'string'],
            'waba_id' => ['required', 'string'],
            'display_name' => ['nullable', 'string', 'max:120'],
            'skip_meta_calls' => ['nullable', 'boolean'],
        ]);

        $channel = $service->connectManual(
            workspaceId: $request->user()->workspace_id,
            accessToken: $data['access_token'],
            phoneNumberId: $data['phone_number_id'],
            wabaId: $data['waba_id'],
            displayName: $data['display_name'] ?? null,
            skipMetaCalls: (bool) ($data['skip_meta_calls'] ?? false),
        );

        if (! ($data['skip_meta_calls'] ?? false)) {
            SyncWaTemplatesJob::dispatch($channel->id)->onQueue(config('sinapsa.queues.inbound'));
        }

        return response()->json([
            'channel' => new ChannelResource($channel),
        ], 201);
    }

    /**
     * Connect manual de Instagram DM (DEV only).
     * Recibe access_token + ig_user_id + page_id (algunos clientes lo trabajan
     * por separado, otros lo unifican). Para MVP guardamos ig_user_id como
     * external_id y page_id en config.
     */
    public function connectInstagramManual(Request $request): JsonResponse
    {
        abort_unless(app()->environment(['local', 'staging']), 404);

        $data = $request->validate([
            'access_token' => ['required', 'string', 'min:20'],
            'ig_user_id' => ['required', 'string'],
            'page_id' => ['nullable', 'string'],
            'display_name' => ['nullable', 'string', 'max:120'],
        ]);

        $channel = Channel::withoutGlobalScopes()->updateOrCreate(
            [
                'workspace_id' => $request->user()->workspace_id,
                'type' => ChannelType::Instagram->value,
                'external_id' => $data['ig_user_id'],
            ],
            [
                'display_name' => $data['display_name'] ?? "Instagram {$data['ig_user_id']}",
                'meta_business_id' => $data['page_id'] ?? null,
                'status' => Channel::STATUS_CONNECTED,
                'token_expires_at' => now()->addDays(60),
                'webhook_subscribed_at' => now(),
                'last_health_check_at' => now(),
                'last_error_code' => null,
                'last_error_message' => null,
                'config' => [
                    'connected_via' => 'manual',
                    'connected_at' => now()->toIso8601String(),
                    'page_id' => $data['page_id'] ?? null,
                ],
            ],
        );
        $channel->setAccessToken($data['access_token']);
        $channel->save();

        return response()->json([
            'channel' => new ChannelResource($channel),
        ], 201);
    }

    /**
     * Connect manual de Facebook Messenger (DEV only).
     * Recibe page access_token + page_id. El external_id es el page_id.
     */
    public function connectMessengerManual(Request $request): JsonResponse
    {
        abort_unless(app()->environment(['local', 'staging']), 404);

        $data = $request->validate([
            'access_token' => ['required', 'string', 'min:20'],
            'page_id' => ['required', 'string'],
            'display_name' => ['nullable', 'string', 'max:120'],
        ]);

        $channel = Channel::withoutGlobalScopes()->updateOrCreate(
            [
                'workspace_id' => $request->user()->workspace_id,
                'type' => ChannelType::Messenger->value,
                'external_id' => $data['page_id'],
            ],
            [
                'display_name' => $data['display_name'] ?? "Messenger Page {$data['page_id']}",
                'meta_business_id' => $data['page_id'],
                'status' => Channel::STATUS_CONNECTED,
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
        $channel->setAccessToken($data['access_token']);
        $channel->save();

        return response()->json([
            'channel' => new ChannelResource($channel),
        ], 201);
    }

    public function disconnect(Channel $channel): JsonResponse
    {
        $channel->forceFill([
            'status' => Channel::STATUS_DISCONNECTED,
            'access_token_encrypted' => null,
            'refresh_token_encrypted' => null,
            'token_expires_at' => null,
        ])->save();

        return response()->json(['ok' => true]);
    }

    public function templates(Channel $channel): JsonResponse
    {
        $templates = $channel->templates()->orderBy('name')->get();

        return response()->json([
            'data' => WaTemplateResource::collection($templates),
        ]);
    }

    public function syncTemplates(Channel $channel, WhatsAppTemplateSyncService $sync): JsonResponse
    {
        if (! $channel->isConnected()) {
            return response()->json([
                'error' => ['code' => 'channel_not_connected', 'message' => 'Channel no está conectado.'],
            ], 422);
        }

        $count = $sync->sync($channel);

        return response()->json([
            'synced' => $count,
        ]);
    }

    public function healthCheck(Channel $channel, MetaEmbeddedSignupService $service): JsonResponse
    {
        $ok = $service->healthCheck($channel);

        return response()->json([
            'ok' => $ok,
            'channel' => new ChannelResource($channel->fresh()),
        ]);
    }
}
