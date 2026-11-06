<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginRequest;
use App\Models\AdminUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Autenticación de administrador por sesión (Sanctum SPA + cookie
 * HttpOnly + token CSRF). No existe endpoint de registro.
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        if (! Auth::guard('admin')->attempt($credentials)) {
            // Mensaje genérico: no revela si el correo existe.
            throw ValidationException::withMessages([
                'email' => 'Credenciales inválidas.',
            ]);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        /** @var AdminUser $admin */
        $admin = Auth::guard('admin')->user();
        $admin->forceFill(['last_login_at' => now()])->save();

        return response()->json(['data' => $this->present($admin)]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->present($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('admin')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    /** @return array<string, mixed> */
    private function present(AdminUser $admin): array
    {
        return [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'last_login_at' => $admin->last_login_at?->toIso8601String(),
        ];
    }
}
