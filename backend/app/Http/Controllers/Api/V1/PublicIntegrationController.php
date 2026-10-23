<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\IntegrationResource;
use App\Integrations\Google\GoogleCalendarClient;
use App\Integrations\Google\GoogleOAuthService;
use App\Integrations\Google\GoogleReauthRequired;
use App\Models\Integration;
use Closure;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API pública de integraciones (Google Calendar) para clientes SaaS.
 *
 *  - GET    /api/v1/integrations                                   integrations:read
 *  - GET    /api/v1/integrations/{id}                              integrations:read
 *  - DELETE /api/v1/integrations/{id}                              integrations:write
 *  - GET    /api/v1/integrations/{id}/calendar/availability        calendar:read
 *  - GET    /api/v1/integrations/{id}/calendar/events              calendar:read
 *  - POST   /api/v1/integrations/{id}/calendar/events              calendar:write
 *  - DELETE /api/v1/integrations/{id}/calendar/events/{eventId}    calendar:write
 *
 * Auth: `sk_` con scope (clientes SaaS) o Sanctum (dashboard; los humanos no llevan scopes).
 *
 * A diferencia de los mensajes (outbox + cola), Calendar es síncrono: el cliente
 * necesita la disponibilidad y el id del evento en la misma respuesta.
 */
class PublicIntegrationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $integrations = Integration::query()
            ->where('workspace_id', $request->user()->workspace_id)
            ->when($request->query('provider'), fn ($q, $p) => $q->where('provider', $p))
            ->when($request->query('end_user_id'), fn ($q, $id) => $q->where('end_user_id', $id))
            ->latest()
            ->get();

        return response()->json(['data' => IntegrationResource::collection($integrations)]);
    }

    public function show(Request $request, string $integration): JsonResponse
    {
        return response()->json(new IntegrationResource($this->resolve($request, $integration)));
    }

    public function destroy(Request $request, string $integration, GoogleOAuthService $oauth): JsonResponse
    {
        $model = $this->resolve($request, $integration);
        $oauth->revoke($model);

        $model->forceFill([
            'status' => Integration::STATUS_DISCONNECTED,
            'access_token_encrypted' => null,
            'refresh_token_encrypted' => null,
        ])->save();
        $model->delete();

        return response()->json(['id' => $model->id, 'deleted' => true]);
    }

    public function availability(Request $request, string $integration, GoogleCalendarClient $calendar): JsonResponse
    {
        $data = $request->validate([
            'time_min' => ['required', 'date'],
            'time_max' => ['required', 'date', 'after:time_min'],
            'calendar_ids' => ['nullable', 'array', 'max:20'],
            'calendar_ids.*' => ['string'],
        ]);
        $model = $this->resolveCalendar($request, $integration);

        $busy = $this->call($model, fn () => $calendar->busy(
            $model,
            $this->iso($data['time_min']),
            $this->iso($data['time_max']),
            $data['calendar_ids'] ?? ['primary'],
        ));

        return response()->json(['busy' => $busy]);
    }

    public function events(Request $request, string $integration, GoogleCalendarClient $calendar): JsonResponse
    {
        $data = $request->validate([
            'calendar_id' => ['nullable', 'string'],
            'time_min' => ['nullable', 'date'],
            'time_max' => ['nullable', 'date'],
            'page_token' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:250'],
        ]);
        $model = $this->resolveCalendar($request, $integration);

        $result = $this->call($model, fn () => $calendar->listEvents($model, $data['calendar_id'] ?? 'primary', [
            'time_min' => isset($data['time_min']) ? $this->iso($data['time_min']) : null,
            'time_max' => isset($data['time_max']) ? $this->iso($data['time_max']) : null,
            'page_token' => $data['page_token'] ?? null,
            'limit' => $data['limit'] ?? null,
        ]));

        return response()->json($result);
    }

    public function createEvent(Request $request, string $integration, GoogleCalendarClient $calendar): JsonResponse
    {
        $data = $request->validate([
            'calendar_id' => ['nullable', 'string'],
            'summary' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:8000'],
            'location' => ['nullable', 'string', 'max:255'],
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
            'time_zone' => ['nullable', 'timezone'],
            'attendees' => ['nullable', 'array', 'max:50'],
            'attendees.*.email' => ['required', 'email'],
            'attendees.*.name' => ['nullable', 'string', 'max:120'],
        ]);
        $model = $this->resolveCalendar($request, $integration);

        $event = $this->call($model, fn () => $calendar->createEvent($model, $data['calendar_id'] ?? 'primary', [
            ...$data,
            'start' => $this->iso($data['start']),
            'end' => $this->iso($data['end']),
        ]));

        return response()->json($event, 201);
    }

    public function deleteEvent(Request $request, string $integration, string $eventId, GoogleCalendarClient $calendar): JsonResponse
    {
        $model = $this->resolveCalendar($request, $integration);
        $calendarId = (string) $request->query('calendar_id', 'primary');

        $this->call($model, fn () => $calendar->deleteEvent($model, $calendarId, $eventId));

        return response()->json(['id' => $eventId, 'deleted' => true]);
    }

    private function resolve(Request $request, string $id): Integration
    {
        $integration = Integration::query()
            ->where('workspace_id', $request->user()->workspace_id)
            ->whereKey($id)
            ->first();

        if (! $integration) {
            throw new ApiException(ApiException::TYPE_INVALID_REQUEST, 'integration_not_found', "No integration with id [{$id}].", 'integration', status: 404);
        }

        return $integration;
    }

    private function resolveCalendar(Request $request, string $id): Integration
    {
        $integration = $this->resolve($request, $id);

        if (! $integration->hasProduct(Integration::PRODUCT_CALENDAR)) {
            throw ApiException::invalidRequest('product_not_enabled', 'This integration does not have Google Calendar enabled.', 'integration');
        }
        if ($integration->status === Integration::STATUS_DISCONNECTED) {
            throw ApiException::invalidRequest('integration_disconnected', 'This integration is disconnected.', 'integration');
        }

        return $integration;
    }

    /**
     * Traduce los fallos de Google a errores Stripe-like.
     */
    private function call(Integration $integration, Closure $fn): mixed
    {
        try {
            return $fn();
        } catch (GoogleReauthRequired $e) {
            throw ApiException::invalidRequest('integration_reauth_required', 'The Google account must be reconnected by the end user.', 'integration');
        } catch (RequestException $e) {
            $status = $e->response->status();
            $message = (string) ($e->response->json('error.message') ?? 'Google Calendar request failed.');

            if ($status === 401 || $status === 403) {
                $integration->forceFill([
                    'status' => Integration::STATUS_ERROR,
                    'last_error_code' => "google_{$status}",
                    'last_error_message' => $message,
                ])->save();
            }

            throw new ApiException(
                $status === 429 ? ApiException::TYPE_RATE_LIMIT : ApiException::TYPE_API,
                $status === 404 ? 'calendar_resource_not_found' : 'provider_error',
                "Google Calendar: {$message}",
                status: in_array($status, [404, 429], true) ? $status : 502,
            );
        }
    }

    private function iso(string $date): string
    {
        return \Illuminate\Support\Carbon::parse($date)->toRfc3339String();
    }
}
