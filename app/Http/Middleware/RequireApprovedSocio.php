<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireApprovedSocio
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();

        if (!$u) {
            return response()->json([
                'ok'  => false,
                'msg' => 'No autenticado.',
            ], 401);
        }

        // Aceptamos solo socios con estado_registro = 'aprobado'
        $estado = strtolower((string)($u->estado_registro ?? ''));
        if ($estado !== 'aprobado') {
            return response()->json([
                'ok'  => false,
                'msg' => 'Usuario no aprobado.',
            ], 403);
        }

        return $next($request);
    }
}