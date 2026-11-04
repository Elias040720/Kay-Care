<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Sanctum SPA authentication (session cookie + CSRF). No tokens are issued,
 * so nothing has to be stored in localStorage by the frontend.
 */
class AuthController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function login(LoginRequest $request): JsonResponse
    {
        if (! $request->hasSession()) {
            return response()->json([
                'message' => 'La autenticación por sesión solo está disponible desde un origen SPA autorizado.',
            ], 403);
        }

        $credentials = $request->validated();

        $ok = Auth::guard('web')->attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'is_active' => true,
        ]);

        if (! $ok) {
            $this->audit->log(AuditLogger::LOGIN_FAILED, null, ['email' => $credentials['email']]);

            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son válidas.'],
            ]);
        }

        // Prevent session fixation.
        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::guard('web')->user();
        $user->forceFill(['last_login_at' => now()])->save();

        $this->audit->log(AuditLogger::LOGIN, $user, [], $user);

        return response()->json(['data' => new UserResource($user)]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => new UserResource($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $this->audit->log(AuditLogger::LOGOUT, $user, [], $user);

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }
}
