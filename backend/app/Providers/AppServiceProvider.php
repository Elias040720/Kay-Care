<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Fail loudly in development instead of silently ignoring attributes
        // or issuing hidden N+1 queries.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Model::preventLazyLoading(! $this->app->isProduction());

        // Brute-force protection: per account+IP and per IP.
        RateLimiter::for('login', function (Request $request) {
            $email = mb_strtolower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by('login:'.$email.'|'.$request->ip())->response(fn () => response()->json([
                    'message' => 'Demasiados intentos de inicio de sesión. Intente nuevamente en un minuto.',
                ], 429)),
                Limit::perMinute(20)->by('login-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->getAuthIdentifier() ?: $request->ip());
        });
    }
}
