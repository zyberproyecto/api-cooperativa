<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EstadoInicialController extends Controller
{
    /**
     * GET /api/estado-inicial  (auth:sanctum)
     *
     * Devuelve el estado del “primer ingreso”:
     * - aporte_inicial: no_presentado | pendiente | aprobado | rechazado
     * - perfil: incompleto | pendiente | aprobado | rechazado
     * - unidad: no_asignada | asignada
     * - ready_for_dashboard: bool (true si los tres están OK)
     */
    public function estado(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['ok' => false], 401);
        }

        // CI normalizada (sólo dígitos)
        $ci = preg_replace('/\D/', '', (string)($user->ci_usuario ?? $user->ci ?? ''));
        if ($ci === '') {
            return response()->json(['ok' => false, 'message' => 'Usuario inválido.'], 401);
        }

        // --- Aporte inicial (último comprobante de ese tipo)
        $comprob = DB::table('comprobantes')
            ->where('ci_usuario', $ci)
            ->where('tipo', 'aporte_inicial')
            ->orderByDesc('id')
            ->first();

        $aporte = $comprob ? strtolower((string)$comprob->estado) : 'no_presentado';
        if (!in_array($aporte, ['pendiente','aprobado','rechazado','no_presentado'], true)) {
            $aporte = 'pendiente';
        }

        // --- Perfil extendido (usuarios_perfil)
        $perfilRow = DB::table('usuarios_perfil')
            ->where('ci_usuario', $ci)
            ->first();

        if (!$perfilRow) {
            $perfil = 'incompleto';
        } else {
            $perfil = strtolower((string)($perfilRow->estado_revision ?? 'pendiente'));
            if (!in_array($perfil, ['pendiente','aprobado','rechazado'], true)) {
                $perfil = 'pendiente';
            }
        }

        // --- Unidad asignada (usuario_unidad con estado=activa)
        $asign = DB::table('usuario_unidad')
            ->where('ci_usuario', $ci)
            ->where('estado', 'activa')
            ->first();

        $unidad = $asign ? 'asignada' : 'no_asignada';

        $ready = ($aporte === 'aprobado' && $perfil === 'aprobado' && $unidad === 'asignada');

        return response()->json([
            'ok'     => true,
            'estado' => [
                'aporte_inicial'      => $aporte,
                'perfil'              => $perfil,
                'unidad'              => $unidad,
                'ready_for_dashboard' => $ready,
            ],
        ]);
    }

    /**
     * GET /api/unidad/mia  (auth:sanctum)
     *
     * Devuelve la unidad asignada activa (join usuario_unidad + unidades), o null.
     */
    public function miUnidad(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['ok' => false], 401);
        }

        $ci = preg_replace('/\D/', '', (string)($user->ci_usuario ?? $user->ci ?? ''));
        if ($ci === '') {
            return response()->json(['ok' => false, 'message' => 'Usuario inválido.'], 401);
        }

        $row = DB::table('usuario_unidad as uu')
            ->join('unidades as u', 'u.id', '=', 'uu.unidad_id')
            ->where('uu.ci_usuario', $ci)
            ->where('uu.estado', 'activa')
            ->select(
                'uu.id as asignacion_id',
                'uu.fecha_asignacion',
                'uu.estado as estado_asignacion',
                'u.id as unidad_id',
                'u.codigo',
                'u.descripcion',
                'u.dormitorios',
                'u.m2',
                'u.estado_unidad'
            )
            ->first();

        return response()->json([
            'ok'    => true,
            'unidad'=> $row ?: null,
        ]);
    }
}