<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiRequestLog;
use App\Models\WebhookInboundLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Registros del workspace para el panel:
 *  - GET /api/v1/logs/requests  → llamadas a la API pública (filtrables por canal/integración)
 *  - GET /api/v1/logs/webhooks  → webhooks entrantes de Meta por canal
 *  - GET /api/v1/logs/summary   → últimas 24 h: llamadas por hora y por conexión (Inicio del panel)
 */
class LogController extends Controller
{
    public function requests(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel_id' => ['nullable', 'integer'],
            'integration_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:ok,error'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $logs = ApiRequestLog::query()
            ->with('apiToken:id,name')
            ->when($data['channel_id'] ?? null, fn ($q, $id) => $q->where('channel_id', $id))
            ->when($data['integration_id'] ?? null, fn ($q, $id) => $q->where('integration_id', $id))
            ->when(($data['status'] ?? null) === 'ok', fn ($q) => $q->where('status', '<', 400))
            ->when(($data['status'] ?? null) === 'error', fn ($q) => $q->where('status', '>=', 400))
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 50);

        return response()->json([
            'data' => $logs->getCollection()->map(fn (ApiRequestLog $l) => [
                'id' => $l->id,
                'method' => $l->method,
                'path' => $l->path,
                'route_name' => $l->route_name,
                'status' => $l->status,
                'duration_ms' => $l->duration_ms,
                'error_code' => $l->error_code,
                'channel_id' => $l->channel_id,
                'integration_id' => $l->integration_id,
                'caller' => $l->api_token_id ? ($l->apiToken?->name ?? 'Token borrado') : 'Panel',
                'ip' => $l->ip,
                'request_body' => $l->request_body,
                'response_body' => $l->response_body,
                'created_at' => $l->created_at?->toIso8601String(),
            ]),
            'meta' => ['current_page' => $logs->currentPage(), 'last_page' => $logs->lastPage(), 'total' => $logs->total()],
        ]);
    }

    public function webhooks(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        // webhook_inbound_log no lleva WorkspaceScope (puede llegar sin workspace): filtrar a mano
        $logs = WebhookInboundLog::query()
            ->where('workspace_id', $request->user()->workspace_id)
            ->when($data['channel_id'] ?? null, fn ($q, $id) => $q->where('channel_id', $id))
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 50);

        return response()->json([
            'data' => $logs->getCollection()->map(fn (WebhookInboundLog $l) => [
                'id' => $l->id,
                'source' => $l->source,
                'channel_id' => $l->channel_id,
                'signature_valid' => $l->signature_valid,
                'processed_at' => $l->processed_at?->toIso8601String(),
                'processing_error' => $l->processing_error,
                'payload' => $l->payload,
                'created_at' => $l->created_at?->toIso8601String(),
            ]),
            'meta' => ['current_page' => $logs->currentPage(), 'last_page' => $logs->lastPage(), 'total' => $logs->total()],
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $workspaceId = $request->user()->workspace_id;
        // 24 franjas de una hora que terminan en la hora actual
        $end = now()->startOfHour()->addHour();
        $start = $end->copy()->subHours(24);

        $base = ApiRequestLog::query()->where('created_at', '>=', $start);

        $byHour = (clone $base)
            ->selectRaw("date_trunc('hour', created_at) as hour, count(*) as total, count(*) filter (where status >= 400) as errors")
            ->groupBy('hour')
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->hour)->toIso8601String());

        $hourly = [];
        for ($h = $start->copy(); $h < $end; $h->addHour()) {
            $row = $byHour->get($h->toIso8601String());
            $hourly[] = ['hour' => $h->toIso8601String(), 'total' => (int) ($row->total ?? 0), 'errors' => (int) ($row->errors ?? 0)];
        }

        $totals = (clone $base)
            ->selectRaw('count(*) as calls, count(*) filter (where status >= 400) as errors, avg(duration_ms) as avg_ms')
            ->first();

        $byConnection = (clone $base)
            ->where(fn ($q) => $q->whereNotNull('channel_id')->orWhereNotNull('integration_id'))
            ->selectRaw('channel_id, integration_id, count(*) as calls, count(*) filter (where status >= 400) as errors, max(created_at) as last_at')
            ->groupBy('channel_id', 'integration_id')
            ->get()
            ->map(fn ($r) => [
                'channel_id' => $r->channel_id,
                'integration_id' => $r->integration_id,
                'calls' => (int) $r->calls,
                'errors' => (int) $r->errors,
                'last_at' => Carbon::parse($r->last_at)->toIso8601String(),
            ]);

        $webhooks = DB::table('webhook_inbound_log')
            ->where('workspace_id', $workspaceId)
            ->where('created_at', '>=', $start)
            ->count();

        return response()->json([
            'since' => $start->toIso8601String(),
            'hourly' => $hourly,
            'totals' => [
                'calls' => (int) $totals->calls,
                'errors' => (int) $totals->errors,
                'avg_ms' => $totals->avg_ms !== null ? (int) round($totals->avg_ms) : null,
                'webhooks' => $webhooks,
            ],
            'by_connection' => $byConnection,
        ]);
    }
}
