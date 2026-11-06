<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    /** Peticiones del SPA: Origin de un dominio "stateful" ⇒ sesión por cookie. */
    private array $spa = ['Origin' => 'http://localhost', 'Referer' => 'http://localhost/admin/login'];

    public function test_admin_can_log_in_and_read_profile_with_session_cookie(): void
    {
        $admin = AdminUser::factory()->create(['email' => 'admin@farmacia.test', 'password' => 'Clave-Segura-2026']);

        $this->withHeaders($this->spa)
            ->postJson(self::API.'/admin/login', ['email' => 'admin@farmacia.test', 'password' => 'Clave-Segura-2026'])
            ->assertOk()
            ->assertJsonPath('data.email', 'admin@farmacia.test')
            ->assertJsonMissingPath('data.password');

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->withHeaders($this->spa)->getJson(self::API.'/admin/me')->assertOk()->assertJsonPath('data.id', $admin->id);
        $this->assertNotNull($admin->fresh()->last_login_at);
    }

    public function test_passwords_are_stored_hashed(): void
    {
        $admin = AdminUser::factory()->create(['password' => 'Clave-Segura-2026']);

        $this->assertNotSame('Clave-Segura-2026', $admin->getRawOriginal('password'));
        $this->assertTrue(Hash::check('Clave-Segura-2026', $admin->getRawOriginal('password')));
    }

    public function test_wrong_credentials_get_a_generic_error(): void
    {
        AdminUser::factory()->create(['email' => 'admin@farmacia.test']);

        $wrongPassword = $this->withHeaders($this->spa)
            ->postJson(self::API.'/admin/login', ['email' => 'admin@farmacia.test', 'password' => 'incorrecta'])
            ->assertStatus(422)->json('errors.email.0');
        $unknownEmail = $this->withHeaders($this->spa)
            ->postJson(self::API.'/admin/login', ['email' => 'nadie@farmacia.test', 'password' => 'incorrecta'])
            ->assertStatus(422)->json('errors.email.0');

        $this->assertSame('Credenciales inválidas.', $wrongPassword);
        $this->assertSame($wrongPassword, $unknownEmail);
        $this->assertGuest('admin');
    }

    public function test_login_is_rate_limited_against_brute_force(): void
    {
        AdminUser::factory()->create(['email' => 'admin@farmacia.test']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(self::API.'/admin/login', ['email' => 'admin@farmacia.test', 'password' => 'mala-'.$i])->assertStatus(422);
        }

        $this->postJson(self::API.'/admin/login', ['email' => 'admin@farmacia.test', 'password' => 'mala-final'])
            ->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_logout_ends_the_session(): void
    {
        $this->actingAsAdmin();

        $this->withHeaders($this->spa)->postJson(self::API.'/admin/logout')->assertOk();
        $this->assertGuest('admin');
    }

    public function test_there_is_no_public_registration_endpoint(): void
    {
        $this->postJson(self::API.'/admin/register', ['email' => 'x@y.z', 'password' => 'x'])->assertNotFound();
        $this->postJson('/api/register')->assertNotFound();
        $this->assertSame(0, AdminUser::query()->count());
    }

    /** @return array<string, array{string, string}> */
    public static function protectedRoutes(): array
    {
        return [
            'me' => ['GET', '/admin/me'],
            'dashboard' => ['GET', '/admin/dashboard'],
            'products index' => ['GET', '/admin/products'],
            'products store' => ['POST', '/admin/products'],
            'products update' => ['PUT', '/admin/products/1'],
            'products delete' => ['DELETE', '/admin/products/1'],
            'products image' => ['POST', '/admin/products/1/image'],
            'stock adjust' => ['POST', '/admin/products/1/stock'],
            'stock movements' => ['GET', '/admin/stock-movements'],
            'orders index' => ['GET', '/admin/orders'],
            'orders show' => ['GET', '/admin/orders/1'],
            'orders status' => ['PATCH', '/admin/orders/1/status'],
            'logout' => ['POST', '/admin/logout'],
        ];
    }

    #[DataProvider('protectedRoutes')]
    public function test_guests_cannot_reach_admin_routes(string $method, string $uri): void
    {
        $this->json($method, self::API.$uri)
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }
}
