<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WebhookDeliveryResource;
use App\Http\Resources\WebhookEndpointResource;
use App\Jobs\DeliverWebhookJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * CRUD de webhook endpoints + listado/replay de deliveries.
 * SOLO Sanctum (humanos del dashboard) — los api-tokens NO gestionan webhooks.
 */
class WebhookEndpointController extends Controller
{
    public const AVAILABLE_EVENTS = [
        '*',                  // todos
        'message.*',          // toda la familia message.*
        'message.received',
        'message.sent',
        'message.delivered',
        'message.read',
        'message.failed',
        'template.*',         // toda la familia template.*
        'template.status_updated',
        'webhook.test',       // evento dummy del endpoint de testeo
    ];

    public function index(): JsonResponse
    {
        $endpoints = WebhookEndpoint::query()
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => WebhookEndpointResource::collection($endpoints),
            'available_events' => self::AVAILABLE_EVENTS,
        ]);
    }

    public function show(WebhookEndpoint $webhook): JsonResponse
    {
        return response()->json([
            'webhook' => new WebhookEndpointResource($webhook),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url', 'max:512'],
            'description' => ['nullable', 'string', 'max:255'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(self::AVAILABLE_EVENTS)],
        ]);

        $secret = WebhookEndpoint::generateSecret();

        $endpoint = new WebhookEndpoint([
            'url' => $data['url'],
            'description' => $data['description'] ?? null,
            'events' => $data['events'],
            'status' => WebhookEndpoint::STATUS_ACTIVE,
            'created_by_user_id' => $request->user()->id,
        ]);
        $endpoint->workspace_id = $request->user()->workspace_id;
        $endpoint->setSecret($secret);
        $endpoint->save();

        return response()->json([
            // Plain secret SOLO aquí — el operador tiene que copiarlo y guardarlo.
            'plain_secret' => $secret,
            'webhook' => new WebhookEndpointResource($endpoint),
        ], 201);
    }

    public function update(Request $request, WebhookEndpoint $webhook): JsonResponse
    {
        $data = $request->validate([
            'url' => ['sometimes', 'required', 'url', 'max:512'],
            'description' => ['nullable', 'string', 'max:255'],
            'events' => ['sometimes', 'required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(self::AVAILABLE_EVENTS)],
            'status' => ['sometimes', 'required', Rule::in([
                WebhookEndpoint::STATUS_ACTIVE,
                WebhookEndpoint::STATUS_PAUSED,
            ])],
        ]);

        $webhook->fill($data)->save();

        return response()->json([
            'webhook' => new WebhookEndpointResource($webhook),
        ]);
    }

    public function destroy(WebhookEndpoint $webhook): JsonResponse
    {
        $webhook->delete();

        return response()->json(['ok' => true]);
    }

    public function deliveries(Request $request, WebhookEndpoint $webhook): JsonResponse
    {
        $deliveries = WebhookDelivery::where('endpoint_id', $webhook->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(min((int) $request->integer('limit', 25), 100));

        return response()->json([
            'data' => WebhookDeliveryResource::collection($deliveries),
            'meta' => [
                'next_cursor' => $deliveries->nextCursor()?->encode(),
                'prev_cursor' => $deliveries->previousCursor()?->encode(),
            ],
        ]);
    }

    /**
     * Re-dispatch manual de un delivery concreto. Útil cuando el cliente recibió
     * el evento pero su sistema lo procesó mal y quiere reproducirlo.
     */
    public function replay(WebhookEndpoint $webhook, WebhookDelivery $delivery): JsonResponse
    {
        abort_if($delivery->endpoint_id !== $webhook->id, 404);

        // Resetea estado e intentos, mantiene el payload original (es la idea del replay)
        $delivery->forceFill([
            'status' => WebhookDelivery::STATUS_PENDING,
            'attempt' => 0,
            'response_status' => null,
            'response_headers' => null,
            'response_body' => null,
            'error_message' => null,
            'next_attempt_at' => null,
            'delivered_at' => null,
            'failed_at' => null,
        ])->save();

        DeliverWebhookJob::dispatch($delivery->id)
            ->onQueue(config('sinapsa.queues.webhooks_out'));

        return response()->json([
            'ok' => true,
            'delivery' => new WebhookDeliveryResource($delivery->fresh()),
        ]);
    }

    /**
     * Envía un evento dummy `webhook.test` al endpoint, sin tocar mensajes reales.
     */
    public function test(WebhookEndpoint $webhook): JsonResponse
    {
        $eventId = 'evt_' . Str::ulid();
        $payload = [
            'id' => $eventId,
            'type' => 'webhook.test',
            'workspace_id' => $webhook->workspace_id,
            'occurred_at' => now()->toIso8601String(),
            'data' => [
                'message' => 'Dispatched from /api/v1/webhooks/{id}/test',
            ],
        ];

        $delivery = WebhookDelivery::create([
            'workspace_id' => $webhook->workspace_id,
            'endpoint_id' => $webhook->id,
            'event_id' => $eventId,
            'event_type' => 'webhook.test',
            'payload' => $payload,
            'attempt' => 0,
            'status' => WebhookDelivery::STATUS_PENDING,
        ]);

        DeliverWebhookJob::dispatch($delivery->id)
            ->onQueue(config('sinapsa.queues.webhooks_out'));

        return response()->json([
            'ok' => true,
            'delivery' => new WebhookDeliveryResource($delivery),
        ]);
    }
}
