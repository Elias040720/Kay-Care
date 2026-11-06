<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Detecta asignaciones masivas y lazy loading accidentales fuera de producción.
        Model::shouldBeStrict(! $this->app->isProduction());

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        Password::defaults(fn () => Password::min(12)->letters()->mixedCase()->numbers());

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        $perMinute = fn (string $key) => max(1, (int) config("store.rate_limits.{$key}"));

        RateLimiter::for('store-api', fn (Request $request) => Limit::perMinute($perMinute('store'))->by($request->ip()));

        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute($perMinute('checkout'))->by($request->ip()));

        RateLimiter::for('admin-api', fn (Request $request) => Limit::perMinute($perMinute('admin'))->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        // Fuerza bruta: 5 intentos/min por correo+IP y 20/min por IP.
        RateLimiter::for('admin-login', function (Request $request) use ($perMinute) {
            $email = mb_strtolower((string) $request->input('email'));

            return [
                Limit::perMinute($perMinute('login'))->by($email.'|'.$request->ip()),
                Limit::perMinute(20)->by('login-ip|'.$request->ip()),
            ];
        });
    }
}
