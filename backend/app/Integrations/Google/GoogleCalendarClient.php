<?php

namespace App\Integrations\Google;

use App\Models\Integration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Cliente mínimo de Google Calendar API v3 sobre una Integration.
 * Fuera de aquí nadie mira el payload de Google: se devuelven arrays normalizados.
 */
class GoogleCalendarClient
{
    public const BASE_URL = 'https://www.googleapis.com/calendar/v3';

    public function __construct(
        protected GoogleOAuthService $oauth,
    ) {}

    /**
     * Bloques ocupados por calendario entre $timeMin y $timeMax (ISO 8601).
     *
     * @param  string[]  $calendarIds
     * @return array<string, array<int, array{start: string, end: string}>>
     */
    public function busy(Integration $integration, string $timeMin, string $timeMax, array $calendarIds = ['primary']): array
    {
        $data = $this->http($integration)->post('/freeBusy', [
            'timeMin' => $timeMin,
            'timeMax' => $timeMax,
            'items' => array_map(fn ($id) => ['id' => $id], $calendarIds),
        ])->throw()->json();

        $result = [];
        foreach ($data['calendars'] ?? [] as $id => $calendar) {
            $result[$id] = array_map(
                fn ($b) => ['start' => $b['start'], 'end' => $b['end']],
                $calendar['busy'] ?? [],
            );
        }

        return $result;
    }

    /**
     * @return array{data: array<int, array>, next_page_token: ?string}
     */
    public function listEvents(Integration $integration, string $calendarId, array $filters = []): array
    {
        $data = $this->http($integration)->get('/calendars/' . rawurlencode($calendarId) . '/events', array_filter([
            'timeMin' => $filters['time_min'] ?? null,
            'timeMax' => $filters['time_max'] ?? null,
            'pageToken' => $filters['page_token'] ?? null,
            'maxResults' => $filters['limit'] ?? 50,
            'singleEvents' => 'true',
            'orderBy' => 'startTime',
        ]))->throw()->json();

        return [
            'data' => array_map(fn ($e) => $this->normalizeEvent($e, $calendarId), $data['items'] ?? []),
            'next_page_token' => $data['nextPageToken'] ?? null,
        ];
    }

    public function createEvent(Integration $integration, string $calendarId, array $event): array
    {
        $data = $this->http($integration)->post('/calendars/' . rawurlencode($calendarId) . '/events?sendUpdates=all', array_filter([
            'summary' => $event['summary'],
            'description' => $event['description'] ?? null,
            'location' => $event['location'] ?? null,
            'start' => array_filter(['dateTime' => $event['start'], 'timeZone' => $event['time_zone'] ?? null]),
            'end' => array_filter(['dateTime' => $event['end'], 'timeZone' => $event['time_zone'] ?? null]),
            'attendees' => isset($event['attendees'])
                ? array_map(fn ($a) => array_filter(['email' => $a['email'], 'displayName' => $a['name'] ?? null]), $event['attendees'])
                : null,
        ]))->throw()->json();

        return $this->normalizeEvent($data, $calendarId);
    }

    public function deleteEvent(Integration $integration, string $calendarId, string $eventId): void
    {
        $this->http($integration)
            ->delete('/calendars/' . rawurlencode($calendarId) . '/events/' . rawurlencode($eventId) . '?sendUpdates=all')
            ->throw();
    }

    private function normalizeEvent(array $e, string $calendarId): array
    {
        return [
            'id' => $e['id'],
            'calendar_id' => $calendarId,
            'status' => $e['status'] ?? null,
            'summary' => $e['summary'] ?? null,
            'description' => $e['description'] ?? null,
            'location' => $e['location'] ?? null,
            'start' => $e['start']['dateTime'] ?? $e['start']['date'] ?? null,
            'end' => $e['end']['dateTime'] ?? $e['end']['date'] ?? null,
            'all_day' => isset($e['start']['date']),
            'attendees' => array_map(fn ($a) => [
                'email' => $a['email'] ?? null,
                'name' => $a['displayName'] ?? null,
                'response_status' => $a['responseStatus'] ?? null,
            ], $e['attendees'] ?? []),
            'html_link' => $e['htmlLink'] ?? null,
        ];
    }

    private function http(Integration $integration): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->withToken($this->oauth->accessToken($integration))
            ->acceptJson()
            ->timeout(15);
    }
}
