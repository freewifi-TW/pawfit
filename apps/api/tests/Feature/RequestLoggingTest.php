<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RequestLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_echoes_incoming_request_id_and_logs_request_and_response(): void
    {
        Log::spy();

        $this->getJson('/api/share/does-not-exist', ['X-Request-Id' => 'caddy-uuid-0001'])
            ->assertStatus(404)
            ->assertHeader('X-Request-Id', 'caddy-uuid-0001');

        $this->assertSame('caddy-uuid-0001', Context::get('request_id'));

        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context) {
            return $message === 'http request'
                && $context['event'] === 'http.request'
                && $context['method'] === 'GET'
                && $context['path'] === '/api/share/does-not-exist';
        })->once();

        Log::shouldHaveReceived('log')->withArgs(function (string $level, string $message, array $context) {
            return $level === 'warning'
                && $context['event'] === 'http.response'
                && $context['status'] === 404
                && is_int($context['duration_ms']);
        })->once();
    }

    public function test_generates_request_id_when_missing_or_malformed(): void
    {
        $r1 = $this->getJson('/api/share/x');
        $r2 = $this->getJson('/api/share/x', ['X-Request-Id' => 'bad id with spaces!']);

        $this->assertMatchesRegularExpression('/^[0-9A-Z]{26}$/', $r1->headers->get('X-Request-Id'));
        $this->assertMatchesRegularExpression('/^[0-9A-Z]{26}$/', $r2->headers->get('X-Request-Id'));
        $this->assertNotSame($r1->headers->get('X-Request-Id'), $r2->headers->get('X-Request-Id'));
    }

    public function test_user_id_is_set_for_authenticated_requests_and_cleared_after(): void
    {
        $user = User::factory()->create(['pawfit_id' => 'logtester', 'tos_accepted_at' => now()]);

        $this->actingAs($user)->getJson('/api/me')->assertOk();
        $this->assertSame((string) $user->id, Context::get('user_id'));

        // actingAs 會延續到後續請求，切回訪客要清掉 guard
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/share/x')->assertStatus(404);
        $this->assertNull(Context::get('user_id'));
    }

    public function test_unauthenticated_request_without_accept_header_is_401_not_500(): void
    {
        $this->get('/api/me')->assertStatus(401);
    }
}
