<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autorización: además de estar autenticado, el usuario debe ser una
 * instancia de AdminUser (defensa ante cambios futuros de guards).
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() instanceof AdminUser) {
            abort(403, 'Acceso restringido al administrador de la tienda.');
        }

        return $next($request);
    }
}
