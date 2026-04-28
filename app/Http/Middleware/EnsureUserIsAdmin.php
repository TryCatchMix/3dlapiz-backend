<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Permite el paso solo a usuarios autenticados con rol admin.
     * Devuelve 403 (no 401) cuando el usuario está autenticado pero no es admin,
     * y 401 cuando no hay usuario autenticado.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Acceso restringido a administradores.'], 403);
        }

        return $next($request);
    }
}
