<?php

use App\Exceptions\StoreException;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sesión + CSRF para peticiones del SPA (mismo origen vía proxy nginx).
        $middleware->statefulApi();

        // Proxies de confianza (nginx del frontend / ingress de Container Apps).
        $middleware->trustProxies(
            at: array_filter(explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1'))) ?: null,
        );

        $middleware->append(SecurityHeaders::class);

        // Un invitado que llama a /admin recibe 401 JSON, nunca una redirección.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        $exceptions->render(function (StoreException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->errorCode,
                'details' => (object) $e->details,
            ], $e->status);
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'Los datos enviados no son válidos.',
                'code' => 'validation_failed',
                'errors' => $e->errors(),
            ], 422);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Debes iniciar sesión como administrador.', 'code' => 'unauthenticated'], 401);
            }

            return null;
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(
                    ['message' => 'Demasiadas solicitudes. Intenta nuevamente en unos segundos.', 'code' => 'too_many_requests'],
                    429,
                    $e->getHeaders(),
                );
            }

            return null;
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                $message = $e->getPrevious() instanceof ModelNotFoundException || $e->getMessage() === ''
                    ? 'Recurso no encontrado.'
                    : $e->getMessage();

                return response()->json(['message' => $message, 'code' => 'not_found'], 404);
            }

            return null;
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->is('api/*') && $e->getStatusCode() < 500) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'Solicitud no permitida.',
                    'code' => match ($e->getStatusCode()) {
                        403 => 'forbidden',
                        405 => 'method_not_allowed',
                        419 => 'csrf_token_mismatch',
                        default => 'http_error',
                    },
                ], $e->getStatusCode(), $e->getHeaders());
            }

            return null;
        });
    })->create();
