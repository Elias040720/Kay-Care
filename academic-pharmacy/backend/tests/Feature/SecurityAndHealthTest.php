<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Order;
use App\Models\Product;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityAndHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_reports_database_status(): void
    {
        $this->getJson(self::API.'/health')->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('database', 'ok');
    }

    public function test_security_headers_are_present(): void
    {
        $response = $this->getJson(self::API.'/products');

        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('X-Powered-By');
        $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $response->headers->get('X-Request-Id'));
    }

    public function test_sensitive_responses_are_not_cached(): void
    {
        $this->actingAsAdmin();
        $this->getJson(self::API.'/admin/dashboard')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_cors_only_allows_configured_origins(): void
    {
        $allowed = $this->withHeaders(['Origin' => 'http://localhost:8088'])->getJson(self::API.'/categories');
        $allowed->assertHeader('Access-Control-Allow-Origin', 'http://localhost:8088');

        // Un origen no listado nunca recibe su propio origen de vuelta, por lo
        // que el navegador bloquea la lectura de la respuesta.
        $denied = $this->withHeaders(['Origin' => 'https://sitio-malicioso.example'])->getJson(self::API.'/categories');
        $this->assertNotSame('https://sitio-malicioso.example', $denied->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $denied->headers->get('Access-Control-Allow-Origin'));

        $preflight = $this->call('OPTIONS', self::API.'/checkout', server: [
            'HTTP_ORIGIN' => 'https://sitio-malicioso.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);
        $this->assertNotSame('https://sitio-malicioso.example', $preflight->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_errors_use_a_uniform_json_shape_without_internals(): void
    {
        $this->getJson(self::API.'/ruta-inexistente')->assertNotFound()
            ->assertJsonStructure(['message', 'code'])
            ->assertJsonMissingPath('trace')
            ->assertJsonMissingPath('exception');

        $this->deleteJson(self::API.'/products')->assertStatus(405)->assertJsonPath('code', 'method_not_allowed');
    }

    public function test_demo_seeders_create_a_consistent_dataset(): void
    {
        config(['store.admin.password' => 'Clave-Demo-Segura-2026']);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(24, Product::query()->count());
        $this->assertSame(18, Order::query()->count());
        $this->assertSame(0, Product::query()->where('stock', '<', 0)->count());
        $this->assertTrue(AdminUser::query()->where('email', 'admin@farmacia.demo')->exists());

        // Cada pedido no cancelado tiene su descuento de stock registrado.
        foreach (Order::query()->with('items')->get() as $order) {
            $this->assertSame($order->items->sum('line_total_cents'), $order->total_cents);
        }

        // Re-ejecutar el seeder es idempotente.
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(24, Product::query()->count());
        $this->assertSame(18, Order::query()->count());
    }
}
