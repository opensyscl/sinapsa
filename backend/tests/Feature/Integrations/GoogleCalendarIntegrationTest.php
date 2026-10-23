<?php

namespace Tests\Feature\Integrations;

use App\Connect\ConnectSessionTokenService;
use App\Models\ApiToken;
use App\Models\ConnectSession;
use App\Models\Integration;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleCalendarIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sinapsa.google.client_id' => 'test-client-id',
            'sinapsa.google.client_secret' => 'test-secret',
            'sinapsa.google.redirect_uri' => 'http://localhost:48000/api/oauth/google/callback',
            'sinapsa.connect.hosted_url_base' => 'http://localhost:3002',
        ]);
    }

    private function workspace(string $slug = 'cliente-a'): Workspace
    {
        return Workspace::create(['slug' => $slug, 'name' => ucfirst($slug), 'status' => 'active']);
    }

    private function apiKey(Workspace $workspace, array $scopes = ['*']): string
    {
        return ApiToken::issue($workspace->id, 'test', $scopes)['plain'];
    }

    private function connectSession(Workspace $workspace, array $types = ['google_calendar']): string
    {
        $session = ConnectSession::create([
            'workspace_id' => $workspace->id,
            'jti' => ConnectSessionTokenService::newJti(),
            'allowed_channel_types' => $types,
            'client_metadata' => ['user_id' => 'u-123'],
            'end_user_id' => 'u-123',
            'status' => ConnectSession::STATUS_PENDING,
            'expires_at' => now()->addMinutes(15),
        ]);

        return app(ConnectSessionTokenService::class)->issue($session);
    }

    private function fakeGoogleConsent(string $sub = 'google-sub-1', ?string $scope = null): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.access',
                'refresh_token' => '1//refresh',
                'expires_in' => 3599,
                'scope' => $scope ?? 'openid email profile https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.freebusy',
                'token_type' => 'Bearer',
            ]),
            'openidconnect.googleapis.com/*' => Http::response([
                'sub' => $sub,
                'email' => "{$sub}@example.com",
                'name' => 'Ana Test',
            ]),
        ]);
    }

    private function integration(Workspace $workspace, array $attrs = []): Integration
    {
        $integration = new Integration(array_merge([
            'workspace_id' => $workspace->id,
            'provider' => 'google',
            'products' => ['calendar'],
            'external_id' => 'sub-' . $workspace->id,
            'email' => 'ana@example.com',
            'status' => Integration::STATUS_CONNECTED,
            'token_expires_at' => now()->addHour(),
        ], $attrs));
        $integration->setAccessToken('ya29.valid');
        $integration->setRefreshToken('1//refresh');
        $integration->save();

        return $integration;
    }

    public function test_connect_session_accepts_google_calendar(): void
    {
        $ws = $this->workspace();

        $this->withToken($this->apiKey($ws))
            ->postJson('/api/v1/connect-sessions', ['allowed_channel_types' => ['google_calendar']])
            ->assertCreated()
            ->assertJsonPath('session.allowed_channel_types', ['google_calendar']);
    }

    public function test_authorize_returns_google_consent_url_with_session_as_state(): void
    {
        $jwt = $this->connectSession($this->workspace());

        $url = $this->getJson("/api/v1/connect-sessions/{$jwt}/google/authorize?type=google_calendar")
            ->assertOk()
            ->json('url');

        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth', $url);
        $this->assertSame($jwt, $query['state']);
        $this->assertSame('offline', $query['access_type']);
        $this->assertStringContainsString('calendar.events', $query['scope']);
    }

    public function test_authorize_rejects_type_not_allowed_by_session(): void
    {
        $jwt = $this->connectSession($this->workspace(), ['whatsapp']);

        $this->getJson("/api/v1/connect-sessions/{$jwt}/google/authorize?type=google_calendar")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'channel_type_not_allowed');
    }

    public function test_callback_creates_integration_with_encrypted_tokens_and_completes_session(): void
    {
        $ws = $this->workspace();
        $jwt = $this->connectSession($ws);
        $this->fakeGoogleConsent();

        $response = $this->get("/api/oauth/google/callback?code=4/abc&state={$jwt}");

        $integration = Integration::withoutGlobalScopes()->sole();
        $response->assertRedirect();
        $this->assertStringStartsWith('http://localhost:3002/connect/done?', $response->headers->get('Location'));
        $this->assertStringContainsString('status=connected', $response->headers->get('Location'));

        $this->assertSame($ws->id, $integration->workspace_id);
        $this->assertSame('google-sub-1', $integration->external_id);
        $this->assertSame(['user_id' => 'u-123'], $integration->client_metadata);
        $this->assertSame('u-123', $integration->end_user_id);
        $this->assertSame('1//refresh', $integration->getRefreshToken());
        $this->assertNotSame('1//refresh', $integration->refresh_token_encrypted);

        $session = ConnectSession::sole();
        $this->assertSame(ConnectSession::STATUS_COMPLETED, $session->status);
        $this->assertSame($integration->id, $session->completed_integration_id);
    }

    public function test_callback_fails_when_user_unchecks_calendar_scope(): void
    {
        $jwt = $this->connectSession($this->workspace());
        $this->fakeGoogleConsent(scope: 'openid email profile');

        $response = $this->get("/api/oauth/google/callback?code=4/abc&state={$jwt}");

        $this->assertStringContainsString('status=error', $response->headers->get('Location'));
        $this->assertSame(0, Integration::withoutGlobalScopes()->count());
        $this->assertSame(ConnectSession::STATUS_FAILED, ConnectSession::sole()->status);
    }

    public function test_callback_with_access_denied_keeps_session_pending(): void
    {
        $jwt = $this->connectSession($this->workspace());

        $response = $this->get("/api/oauth/google/callback?error=access_denied&state={$jwt}");

        $this->assertStringContainsString('status=cancelled', $response->headers->get('Location'));
        $this->assertSame(ConnectSession::STATUS_PENDING, ConnectSession::sole()->status);
    }

    public function test_callback_with_invalid_state_redirects_with_error(): void
    {
        $response = $this->get('/api/oauth/google/callback?code=4/abc&state=garbage');

        $this->assertStringContainsString('status=error', $response->headers->get('Location'));
    }

    public function test_two_workspaces_connect_separately_and_cannot_see_each_other(): void
    {
        $a = $this->workspace('cliente-a');
        $b = $this->workspace('cliente-b');

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.access',
                'refresh_token' => '1//refresh',
                'expires_in' => 3599,
                'scope' => 'openid https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.freebusy',
            ]),
            'openidconnect.googleapis.com/*' => Http::sequence()
                ->push(['sub' => 'sub-a', 'email' => 'a@example.com'])
                ->push(['sub' => 'sub-b', 'email' => 'b@example.com']),
        ]);
        $this->get('/api/oauth/google/callback?code=x&state=' . $this->connectSession($a));
        $this->get('/api/oauth/google/callback?code=x&state=' . $this->connectSession($b));

        $integrationA = Integration::withoutGlobalScopes()->where('workspace_id', $a->id)->sole();

        $this->withToken($this->apiKey($b))
            ->getJson('/api/v1/integrations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.external_id', 'sub-b');

        $this->withToken($this->apiKey($b))
            ->getJson("/api/v1/integrations/{$integrationA->id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'integration_not_found');
    }

    public function test_availability_returns_busy_blocks(): void
    {
        $ws = $this->workspace();
        $integration = $this->integration($ws);
        Http::fake([
            'www.googleapis.com/calendar/v3/freeBusy' => Http::response([
                'calendars' => ['primary' => ['busy' => [
                    ['start' => '2026-10-24T10:00:00Z', 'end' => '2026-10-24T11:00:00Z'],
                ]]],
            ]),
        ]);

        $this->withToken($this->apiKey($ws, ['calendar:read']))
            ->getJson("/api/v1/integrations/{$integration->id}/calendar/availability?time_min=2026-10-24T00:00:00Z&time_max=2026-10-25T00:00:00Z")
            ->assertOk()
            ->assertJsonPath('busy.primary.0.start', '2026-10-24T10:00:00Z');

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer ya29.valid'));
    }

    public function test_create_event_refreshes_expired_token_first(): void
    {
        $ws = $this->workspace();
        $integration = $this->integration($ws, ['token_expires_at' => now()->subMinute()]);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.fresh', 'expires_in' => 3599]),
            'www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::response([
                'id' => 'evt_1',
                'status' => 'confirmed',
                'summary' => 'Visita',
                'start' => ['dateTime' => '2026-10-24T10:00:00Z'],
                'end' => ['dateTime' => '2026-10-24T10:30:00Z'],
                'htmlLink' => 'https://calendar.google.com/event?eid=1',
            ]),
        ]);

        $this->withToken($this->apiKey($ws, ['calendar:write']))
            ->postJson("/api/v1/integrations/{$integration->id}/calendar/events", [
                'summary' => 'Visita',
                'start' => '2026-10-24T10:00:00Z',
                'end' => '2026-10-24T10:30:00Z',
                'attendees' => [['email' => 'cliente@example.com']],
            ])
            ->assertCreated()
            ->assertJsonPath('id', 'evt_1')
            ->assertJsonPath('all_day', false);

        $this->assertSame('ya29.fresh', $integration->refresh()->getAccessToken());
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/events') && $r->hasHeader('Authorization', 'Bearer ya29.fresh'));
    }

    public function test_revoked_refresh_token_marks_integration_and_returns_reauth_error(): void
    {
        $ws = $this->workspace();
        $integration = $this->integration($ws, ['token_expires_at' => now()->subMinute()]);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $this->withToken($this->apiKey($ws))
            ->getJson("/api/v1/integrations/{$integration->id}/calendar/events")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'integration_reauth_required');

        $this->assertSame(Integration::STATUS_ERROR, $integration->refresh()->status);
    }

    public function test_scopes_are_enforced(): void
    {
        $ws = $this->workspace();
        $integration = $this->integration($ws);

        $this->withToken($this->apiKey($ws, ['calendar:read']))
            ->postJson("/api/v1/integrations/{$integration->id}/calendar/events", [
                'summary' => 'x', 'start' => '2026-10-24T10:00:00Z', 'end' => '2026-10-24T11:00:00Z',
            ])
            ->assertForbidden();
    }

    public function test_delete_revokes_and_soft_deletes(): void
    {
        $ws = $this->workspace();
        $integration = $this->integration($ws);
        Http::fake(['oauth2.googleapis.com/revoke' => Http::response()]);

        $this->withToken($this->apiKey($ws))
            ->deleteJson("/api/v1/integrations/{$integration->id}")
            ->assertOk();

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/revoke'));
        $this->assertSoftDeleted('integrations', ['id' => $integration->id]);
        $this->assertNull(Integration::withoutGlobalScopes()->withTrashed()->find($integration->id)->refresh_token_encrypted);
    }

    public function test_connect_session_stores_end_user_id(): void
    {
        $ws = $this->workspace();

        $this->withToken($this->apiKey($ws))
            ->postJson('/api/v1/connect-sessions', [
                'allowed_channel_types' => ['google_calendar'],
                'end_user_id' => 'cliente-a',
            ])
            ->assertCreated()
            ->assertJsonPath('session.end_user_id', 'cliente-a');
    }

    public function test_integrations_filter_by_end_user(): void
    {
        $ws = $this->workspace();
        $this->integration($ws, ['external_id' => 'g-a', 'end_user_id' => 'cliente-a']);
        $this->integration($ws, ['external_id' => 'g-b', 'end_user_id' => 'cliente-b']);

        $this->withToken($this->apiKey($ws))
            ->getJson('/api/v1/integrations?end_user_id=cliente-b')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.end_user_id', 'cliente-b');
    }

    public function test_dashboard_user_lists_only_own_workspace_integrations(): void
    {
        $a = $this->workspace('cliente-a');
        $b = $this->workspace('cliente-b');
        $this->integration($a);
        $this->integration($b);
        $user = \App\Models\User::factory()->create(['workspace_id' => $a->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/integrations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.workspace_id', $a->id);
    }
}
