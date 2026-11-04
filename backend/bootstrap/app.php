<?php

use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\SecureApiHeaders;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum SPA (cookie + session) authentication with CSRF protection
        // for requests coming from SANCTUM_STATEFUL_DOMAINS.
        $middleware->statefulApi();

        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);
        $middleware->api(append: [
            SecureApiHeaders::class,
        ]);

        // No HTML login page exists: never redirect API guests.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/');

        $middleware->encryptCookies(except: []);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every API path (including unknown ones) answers JSON, never HTML.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return null;
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Recurso no encontrado.'], 404);
            }

            return null;
        });

        // Never flash secrets or clinical free text back into the session.
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
            'clinical_note',
        ]);
    })->create();
