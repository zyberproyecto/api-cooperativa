<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();
        if (!$u || ($u->rol ?? null) !== 'admin') {
            return response()->json(['ok' => false, 'message' => 'Solo administradores.'], 403);
        }
        return $next($request);
    }
}