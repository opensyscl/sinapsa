<?php

namespace Tests\Feature\Logs;

use App\Models\ApiRequestLog;
use App\Models\ApiToken;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiRequestLogTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(string $slug): Workspace
    {
        return Workspace::create(['slug' => $slug, 'name' => ucfirst($slug), 'status' => 'active']);
    }

    public function test_public_api_calls_are_logged_with_their_integration(): void
    {
        $ws = $this->workspace('cliente-a');
        $key = ApiToken::issue($ws->id, 'test', ['*'])['plain'];

        $this->withToken($key)->getJson('/api/v1/integrations')->assertOk();
        $this->withToken($key)->getJson('/api/v1/integrations/999/calendar/events')->assertNotFound();

        $logs = ApiRequestLog::withoutGlobalScopes()->orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertSame($ws->id, $logs[1]->workspace_id);
        $this->assertSame(999, $logs[1]->integration_id);
        $this->assertSame(404, $logs[1]->status);
        $this->assertSame('integration_not_found', $logs[1]->error_code);
    }

    public function test_dashboard_sees_only_its_workspace_logs_and_summary(): void
    {
        $a = $this->workspace('cliente-a');
        $b = $this->workspace('cliente-b');
        $this->withToken(ApiToken::issue($a->id, 'a', ['*'])['plain'])->getJson('/api/v1/integrations/7/calendar/events');
        $this->app['auth']->forgetGuards(); // el guard cachea al usuario entre peticiones del test
        $this->withToken(ApiToken::issue($b->id, 'b', ['*'])['plain'])->getJson('/api/v1/integrations');

        $this->assertSame(2, ApiRequestLog::withoutGlobalScopes()->count());

        $this->app['auth']->forgetGuards();
        $user = User::factory()->create(['workspace_id' => $a->id]);
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/v1/logs/requests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.integration_id', 7)
            ->assertJsonPath('data.0.caller', 'a');

        $summary = $this->getJson('/api/v1/logs/summary')->assertOk()->json();
        $this->assertSame(1, $summary['totals']['calls']);
        $this->assertSame(1, $summary['totals']['errors']);
        $this->assertCount(24, $summary['hourly']);
        $this->assertSame(1, array_sum(array_column($summary['hourly'], 'total')));
        $this->assertSame(7, $summary['by_connection'][0]['integration_id']);
    }

    public function test_secrets_are_not_stored_in_the_log(): void
    {
        $ws = $this->workspace('cliente-a');
        $key = ApiToken::issue($ws->id, 'test', ['*'])['plain'];

        $this->withToken($key)->postJson('/api/v1/connect-sessions', [
            'allowed_channel_types' => ['google_calendar'],
            'client_metadata' => ['token' => 'secreto'],
        ])->assertCreated();

        $log = ApiRequestLog::withoutGlobalScopes()->first();
        $this->assertSame('[oculto]', $log->request_body['client_metadata']['token']);
        $this->assertSame('[oculto]', $log->response_body['session_token']);
    }
}
