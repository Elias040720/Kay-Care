<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Laravel skips CSRF verification while running unit tests, so these tests
 * temporarily switch the environment to exercise the real Sanctum flow:
 * GET /sanctum/csrf-cookie -> X-XSRF-TOKEN header -> POST login.
 */
class CsrfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['env'] = 'local'; // enable CSRF verification
    }

    protected function tearDown(): void
    {
        $this->app['env'] = 'testing';
        parent::tearDown();
    }

    public function test_stateful_post_without_csrf_token_is_rejected_with_419(): void
    {
        $this->nurse(['email' => 'enfermera@example.test']);

        $this->spa()->postJson('/api/v1/auth/login', [
            'email' => 'enfermera@example.test',
            'password' => 'password-de-prueba',
        ])->assertStatus(419)->assertJsonStructure(['message']);

        $this->assertGuest('web');
    }

    public function test_login_succeeds_with_sanctum_csrf_cookie_flow(): void
    {
        $user = $this->nurse(['email' => 'enfermera@example.test']);

        $cookieResponse = $this->spa()->get('/sanctum/csrf-cookie');
        $cookieResponse->assertNoContent();

        $xsrf = $cookieResponse->getCookie('XSRF-TOKEN', false);
        $session = $cookieResponse->getCookie(config('session.cookie'));
        $this->assertNotNull($xsrf, 'XSRF-TOKEN cookie must be issued');
        $this->assertNotNull($session, 'session cookie must be issued');
        $this->assertFalse($xsrf->isHttpOnly(), 'XSRF-TOKEN must be readable by the SPA');
        $this->assertTrue($session->isHttpOnly(), 'session cookie must be HttpOnly');

        $this->spa()
            ->withCookie(config('session.cookie'), $session->getValue())
            ->withHeader('X-XSRF-TOKEN', $xsrf->getValue())
            ->postJson('/api/v1/auth/login', [
                'email' => 'enfermera@example.test',
                'password' => 'password-de-prueba',
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_cors_allows_only_the_configured_spa_origin(): void
    {
        $allowed = $this->withHeaders([
            'Origin' => self::SPA_ORIGIN,
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/v1/auth/login');

        $allowed->assertHeader('Access-Control-Allow-Origin', self::SPA_ORIGIN);
        $allowed->assertHeader('Access-Control-Allow-Credentials', 'true');

        $denied = $this->withHeaders([
            'Origin' => 'http://evil.example',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/v1/auth/login');

        $this->assertNotSame('http://evil.example', $denied->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $denied->headers->get('Access-Control-Allow-Origin'));
    }
}
