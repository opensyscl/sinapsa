<?php

namespace App\Http\Controllers\Api\V1;

use App\Channels\WhatsAppCloud\Services\WhatsAppTemplateCreateService;
use App\Channels\WhatsAppCloud\Services\WhatsAppTemplateSyncService;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\WaTemplateResource;
use App\Models\Channel;
use App\Models\WaTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TemplateController extends Controller
{
    public const CATEGORIES = [
        WaTemplate::CATEGORY_UTILITY,
        WaTemplate::CATEGORY_MARKETING,
        WaTemplate::CATEGORY_AUTHENTICATION,
    ];

    public function index(Request $request): JsonResponse
    {
        $q = WaTemplate::query()->orderBy('name');

        if ($channelId = $request->integer('channel_id')) {
            $q->where('channel_id', $channelId);
        }
        if ($status = $request->string('status')->toString()) {
            $q->where('status', $status);
        }
        if ($language = $request->string('language')->toString()) {
            $q->where('language', $language);
        }
        if ($search = $request->string('q')->toString()) {
            $q->where('name', 'ilike', "%{$search}%");
        }

        return response()->json([
            'data' => WaTemplateResource::collection($q->get()),
            'available_categories' => self::CATEGORIES,
            'available_statuses' => [
                WaTemplate::STATUS_PENDING,
                WaTemplate::STATUS_APPROVED,
                WaTemplate::STATUS_REJECTED,
                WaTemplate::STATUS_DISABLED,
                WaTemplate::STATUS_PAUSED,
            ],
        ]);
    }

    public function show(WaTemplate $template): JsonResponse
    {
        return response()->json([
            'template' => new WaTemplateResource($template),
        ]);
    }

    /**
     * Crea una plantilla nueva en Meta. Llega con status=PENDING — el approval
     * llegará después por webhook `message_template_status_update`.
     */
    public function store(Request $request, WhatsAppTemplateCreateService $service): JsonResponse
    {
        $data = $request->validate([
            'channel_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'regex:/^[a-z0-9_]{1,512}$/'],
            'language' => ['required', 'string', 'max:16'],
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'components' => ['required', 'array', 'min:1'],
            'components.*.type' => ['required', 'string', Rule::in(['BODY', 'HEADER', 'FOOTER', 'BUTTONS'])],
            'components.*.text' => ['nullable', 'string', 'max:1024'],
            'components.*.format' => ['nullable', 'string'],
            'components.*.example' => ['nullable', 'array'],
            'components.*.buttons' => ['nullable', 'array'],
        ]);

        $channel = Channel::query()
            ->where('id', $data['channel_id'])
            ->where('workspace_id', $request->user()->workspace_id)
            ->first();

        if (! $channel) {
            throw ApiException::invalidRequest('channel_not_found', 'Channel not found.', 'channel_id');
        }

        // Validación: al menos un BODY.
        $hasBody = collect($data['components'])->contains(fn ($c) => $c['type'] === 'BODY');
        if (! $hasBody) {
            throw ApiException::invalidRequest(
                'missing_body_component',
                'Templates require at least one BODY component.',
                'components',
            );
        }

        $template = $service->create(
            channel: $channel,
            name: $data['name'],
            language: $data['language'],
            category: $data['category'],
            components: $data['components'],
        );

        return response()->json([
            'template' => new WaTemplateResource($template),
        ], 201);
    }

    public function destroy(WaTemplate $template, WhatsAppTemplateCreateService $service): JsonResponse
    {
        $ok = $service->delete($template);

        return response()->json([
            'ok' => $ok,
        ], $ok ? 200 : 502);
    }

    /**
     * Re-sync forzado de TODAS las plantillas de un canal desde Meta.
     */
    public function syncChannel(Request $request, WhatsAppTemplateSyncService $service): JsonResponse
    {
        $data = $request->validate([
            'channel_id' => ['required', 'integer'],
        ]);

        $channel = Channel::query()
            ->where('id', $data['channel_id'])
            ->where('workspace_id', $request->user()->workspace_id)
            ->first();
        if (! $channel) {
            throw ApiException::invalidRequest('channel_not_found', 'Channel not found.', 'channel_id');
        }

        $count = $service->sync($channel);

        return response()->json([
            'synced' => $count,
        ]);
    }
}
