<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'password-de-prueba';

    public function test_health_is_public_and_matches_contract(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok', 'version' => 'v1']);
    }

    public function test_login_with_valid_credentials_returns_user(): void
    {
        $user = $this->nurse(['email' => 'enfermera@example.test']);

        $response = $this->spa()->postJson('/api/v1/auth/login', [
            'email' => 'Enfermera@Example.test',
            'password' => self::PASSWORD,
        ]);

        $response->assertOk()
            ->assertExactJson(['data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => 'enfermera@example.test',
                'role' => 'nurse',
            ]]);

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.login', 'user_id' => $user->id]);

        $this->spa()->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role', 'nurse');
    }

    public function test_login_with_wrong_password_returns_422(): void
    {
        $this->nurse(['email' => 'enfermera@example.test']);

        $this->spa()->postJson('/api/v1/auth/login', [
            'email' => 'enfermera@example.test',
            'password' => 'incorrecta',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['email']]);

        $this->assertGuest('web');
        $log = AuditLog::query()->where('event', 'auth.login_failed')->firstOrFail();
        $this->assertArrayNotHasKey('password', $log->metadata);
    }

    public function test_login_validation_errors_use_contract_format(): void
    {
        $this->spa()->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['email', 'password']]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->inactive()->create(['email' => 'inactiva@example.test']);

        $this->spa()->postJson('/api/v1/auth/login', [
            'email' => 'inactiva@example.test',
            'password' => self::PASSWORD,
        ])->assertStatus(422);

        $this->assertGuest('web');
    }

    public function test_login_is_rate_limited(): void
    {
        RateLimiter::clear('login:enfermera@example.test|127.0.0.1');
        $this->nurse(['email' => 'enfermera@example.test']);

        for ($i = 0; $i < 5; $i++) {
            $this->spa()->postJson('/api/v1/auth/login', ['email' => 'enfermera@example.test', 'password' => 'x'])
                ->assertStatus(422);
        }

        $this->spa()->postJson('/api/v1/auth/login', ['email' => 'enfermera@example.test', 'password' => self::PASSWORD])
            ->assertStatus(429)
            ->assertJsonStructure(['message']);
    }

    public function test_login_requires_a_stateful_spa_origin(): void
    {
        $this->nurse(['email' => 'enfermera@example.test']);

        $this->postJson('/api/v1/auth/login', ['email' => 'enfermera@example.test', 'password' => self::PASSWORD])
            ->assertStatus(403);
    }

    public function test_logout_ends_the_session(): void
    {
        $user = $this->actingAsNurse();

        $this->spa()->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertGuest('web');
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.logout', 'user_id' => $user->id]);

        $this->app['auth']->forgetGuards();
        $this->spa()->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public static function protectedEndpoints(): array
    {
        $uuid = '9b2f4c1e-8d3a-4f6b-9c2d-1e5f7a8b9c0d';

        return [
            'me' => ['GET', '/api/v1/auth/me'],
            'logout' => ['POST', '/api/v1/auth/logout'],
            'patients index' => ['GET', '/api/v1/patients'],
            'patients store' => ['POST', '/api/v1/patients'],
            'patients show' => ['GET', "/api/v1/patients/{$uuid}"],
            'patients update' => ['PATCH', "/api/v1/patients/{$uuid}"],
            'patient encounters' => ['GET', "/api/v1/patients/{$uuid}/encounters"],
            'encounters store' => ['POST', '/api/v1/encounters'],
            'encounters show' => ['GET', "/api/v1/encounters/{$uuid}"],
            'encounters update' => ['PATCH', "/api/v1/encounters/{$uuid}"],
            'his suggestions' => ['GET', "/api/v1/encounters/{$uuid}/his-suggestions"],
            'his lines' => ['PUT', "/api/v1/encounters/{$uuid}/his-lines"],
            'his preview' => ['GET', "/api/v1/encounters/{$uuid}/his-preview"],
            'his pdf' => ['GET', "/api/v1/encounters/{$uuid}/documents/his.pdf"],
            'note pdf' => ['GET', "/api/v1/encounters/{$uuid}/documents/clinical-note.pdf"],
        ];
    }

    #[DataProvider('protectedEndpoints')]
    public function test_protected_endpoints_return_401_without_session(string $method, string $uri): void
    {
        $this->spa()->json($method, $uri)
            ->assertStatus(401)
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_requests_without_accept_header_still_get_json_401(): void
    {
        $response = $this->get('/api/v1/patients');

        $response->assertStatus(401);
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
    }

    public function test_deactivated_user_loses_access_with_live_session(): void
    {
        $user = $this->actingAsNurse();
        $user->forceFill(['is_active' => false])->save();

        $this->spa()->getJson('/api/v1/patients')->assertStatus(401);
    }

    public function test_there_is_no_public_registration(): void
    {
        $this->spa()->postJson('/api/v1/auth/register', [
            'name' => 'X', 'email' => 'x@example.test', 'password' => 'secret-secret',
        ])->assertStatus(404)->assertJsonStructure(['message']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_unknown_api_routes_return_json_not_html(): void
    {
        $response = $this->get('/api/v1/no-existe');

        $response->assertStatus(404)->assertJsonStructure(['message']);
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
    }

    public function test_api_responses_disable_caching(): void
    {
        $this->actingAsNurse();

        $response = $this->spa()->getJson('/api/v1/patients');

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
