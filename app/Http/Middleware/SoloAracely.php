<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe rutas a usuarios con rol Aracely o Gerencia (dueños operativos/administrativos).
 * Fix auditor #19: proteger endpoints de Compras que exponían costos de proveedor.
 */
class SoloAracely
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(auth()->user()?->esAracely(), 403, 'Acceso restringido al equipo administrativo.');

        return $next($request);
    }
}
