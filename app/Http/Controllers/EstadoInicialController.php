<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EstadoInicialController extends Controller
{
    /**
     * GET /api/estado-inicial  (auth:sanctum)
     */
    public function estado(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'No autenticado'], 401);
        }

        // CI normalizada
        $ci = preg_replace('/\D/', '', (string)($user->ci_usuario ?? $user->ci ?? ''));
        if ($ci === '') {
            return response()->json(['ok' => false, 'message' => 'Usuario inválido'], 401);
        }

        /* --------- Estado de registro (propiedad en usuarios) --------- */
        $estadoRegistro = strtolower((string)($user->estado_registro ?? 'pendiente'));
        if (!in_array($estadoRegistro, ['pendiente','aprobado','rechazado'], true)) {
            $estadoRegistro = 'pendiente';
        }
        $aprobado = ($estadoRegistro === 'aprobado');

        /* --------- Perfil extendido / revisión --------- */
        $perfilCompleto  = (bool)($user->perfil_completo ?? false);
        $perfilRevision  = 'incompleto'; // default

        if (Schema::hasTable('usuarios_perfil')) {
            $perfilRow = DB::table('usuarios_perfil')->where('ci_usuario', $ci)->first();
            if ($perfilRow) {
                $perfilRevision = strtolower((string)($perfilRow->estado_revision ?? 'pendiente'));
                if (!in_array($perfilRevision, ['pendiente','aprobado','rechazado'], true)) {
                    $perfilRevision = 'pendiente';
                }
            }
        }

        /* --------- Aporte inicial (comprobantes) --------- */
        $aporte = 'no_presentado';
        if (Schema::hasTable('comprobantes')) {
            $comprob = null;
            if (Schema::hasColumn('comprobantes', 'tipo_aporte')) {
                $comprob = DB::table('comprobantes')
                    ->where('ci_usuario', $ci)
                    ->whereIn('tipo_aporte', ['inicial', 'aporte_inicial'])
                    ->orderByDesc('id')->first();
            } elseif (Schema::hasColumn('comprobantes', 'tipo')) {
                $comprob = DB::table('comprobantes')
                    ->where('ci_usuario', $ci)
                    ->whereIn('tipo', ['inicial', 'aporte_inicial'])
                    ->orderByDesc('id')->first();
            }

            if ($comprob) {
                $est = strtolower((string)($comprob->estado ?? 'pendiente'));
                $aporte = in_array($est, ['pendiente','aprobado','rechazado'], true) ? $est : 'pendiente';
            }
        }

        /* --------- Unidad asignada --------- */
        $unidad = 'no_asignada';
        if (Schema::hasTable('usuario_unidad')) {
            $asign = DB::table('usuario_unidad')
                ->where('ci_usuario', $ci)
                ->where('estado', 'activa')
                ->first();
            if ($asign) $unidad = 'asignada';
        }

        /* --------- Ready + next step --------- */
        $ready = (
            $aprobado &&
            $perfilCompleto &&
            $perfilRevision === 'aprobado' &&
            $aporte === 'aprobado' &&
            $unidad === 'asignada'
        );

        $nextStep = null;
        $redirect = null;

        if (!$aprobado) {
            $nextStep = $estadoRegistro === 'rechazado' ? 'solicitud_rechazada' : 'esperar_aprobacion_backoffice';
            $redirect = '/onboarding/espera-aprobacion';
        } elseif (!$perfilCompleto) {
            $nextStep = 'completar_perfil';
            $redirect = '/perfil';
        } elseif ($perfilRevision === 'rechazado') {
            $nextStep = 'corregir_perfil';
            $redirect = '/perfil';
        } elseif ($perfilRevision !== 'aprobado') {
            $nextStep = 'esperar_revision_perfil';
            $redirect = '/onboarding/espera-revision';
        } elseif ($aporte === 'no_presentado' || $aporte === 'rechazado') {
            $nextStep = 'enviar_aporte_inicial';
            $redirect = '/comprobantes/nuevo';
        } elseif ($aporte === 'pendiente') {
            $nextStep = 'esperar_revision_aporte';
            $redirect = '/onboarding/espera-revision';
        } elseif ($unidad !== 'asignada') {
            $nextStep = 'esperar_asignacion_unidad';
            $redirect = '/onboarding/espera-asignacion';
        } else {
            $redirect = '/panel';
        }

        /* --------- RESPUESTA (plana para tu front) --------- */
        return response()->json([
            'ok'               => true,
            'estado_registro'  => $estadoRegistro,
            'perfil_completo'  => $perfilCompleto,
            'perfil'           => $perfilRevision,     // ← tu front lo llama "perfil"
            'aporte_inicial'   => $aporte,
            'unidad'           => $unidad,
            'ready_for_dashboard' => $ready,
            'next_step'        => $nextStep,
            'redirect_to'      => $redirect,
        ]);
    }

    /**
     * GET /api/unidad/mia  (auth:sanctum)
     */
    public function miUnidad(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['ok' => false], 401);
        }

        $ci = preg_replace('/\D/', '', (string)($user->ci_usuario ?? $user->ci ?? ''));
        if ($ci === '') {
            return response()->json(['ok' => false, 'message' => 'Usuario inválido'], 401);
        }

        $row = null;
        if (Schema::hasTable('usuario_unidad') && Schema::hasTable('unidades')) {
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
        }

        return response()->json([
            'ok'    => true,
            'unidad'=> $row ?: null,
        ]);
    }
}