<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EstadoInicialController extends Controller
{
    public function estado(Request $request)
{
    $user = $request->user();
    if (!$user) {
        return response()->json(['ok' => false, 'message' => 'No autenticado'], 401);
    }

    $ci = preg_replace('/\D/', '', (string)($user->ci_usuario ?? $user->ci ?? ''));
    if ($ci === '') {
        return response()->json(['ok' => false, 'message' => 'Usuario inválido'], 401);
    }

    // Estado de registro
    $estadoRegistro = strtolower((string)($user->estado_registro ?? 'pendiente'));
    if (!in_array($estadoRegistro, ['pendiente','aprobado','rechazado'], true)) {
        $estadoRegistro = 'pendiente';
    }
    $aprobado = ($estadoRegistro === 'aprobado');

    // Perfil (usamos SOLO perfilRevision para decisiones)
    $perfilRevision = 'incompleto';
    if (\Illuminate\Support\Facades\Schema::hasTable('usuarios_perfil')) {
        $perfilRow = \Illuminate\Support\Facades\DB::table('usuarios_perfil')->where('ci_usuario', $ci)->first();
        if ($perfilRow) {
            $perfilRevision = strtolower((string)($perfilRow->estado_revision ?? 'pendiente'));
            if (!in_array($perfilRevision, ['pendiente','aprobado','rechazado'], true)) {
                $perfilRevision = 'pendiente';
            }
        }
    }

    // Aporte inicial
    $aporte = 'no_presentado';
    if (\Illuminate\Support\Facades\Schema::hasTable('comprobantes')) {
        $comprob = null;
        if (\Illuminate\Support\Facades\Schema::hasColumn('comprobantes', 'tipo_aporte')) {
            $comprob = \Illuminate\Support\Facades\DB::table('comprobantes')
                ->where('ci_usuario', $ci)
                ->whereIn('tipo_aporte', ['inicial','aporte_inicial'])
                ->orderByDesc('id')->first();
        } elseif (\Illuminate\Support\Facades\Schema::hasColumn('comprobantes', 'tipo')) {
            $comprob = \Illuminate\Support\Facades\DB::table('comprobantes')
                ->where('ci_usuario', $ci)
                ->whereIn('tipo', ['inicial','aporte_inicial'])
                ->orderByDesc('id')->first();
        }
        if ($comprob) {
            $est = strtolower((string)($comprob->estado ?? 'pendiente'));
            $aporte = in_array($est, ['pendiente','aprobado','rechazado'], true) ? $est : 'pendiente';
        }
    }

    // Unidad
    $unidad = 'no_asignada';
    if (\Illuminate\Support\Facades\Schema::hasTable('usuario_unidad')) {
        $asign = \Illuminate\Support\Facades\DB::table('usuario_unidad')
            ->where('ci_usuario', $ci)
            ->where('estado', 'activa')
            ->first();
        if ($asign) $unidad = 'asignada';
    }

    // ✅ Listo para panel: solo con perfil/aporte/unidad aprobados + usuario aprobado
    $ready = (
        $aprobado &&
        $perfilRevision === 'aprobado' &&
        $aporte === 'aprobado' &&
        $unidad === 'asignada'
    );

    // Próximo paso
    $nextStep = null;
    $redirect = null;
    if (!$aprobado) {
        $nextStep = $estadoRegistro === 'rechazado' ? 'solicitud_rechazada' : 'esperar_aprobacion_backoffice';
        $redirect = '/onboarding/espera-aprobacion';
    } elseif ($perfilRevision === 'rechazado' || $perfilRevision === 'incompleto') {
        // 🔁 Ya no miro perfil_completo; si está incompleto o rechazado -> volver a /perfil
        $nextStep = 'completar_perfil';
        $redirect = '/perfil';
    } elseif ($perfilRevision === 'pendiente') {
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

    // wrapper que consume el front
    $estado = [
        'aporte_inicial' => $aporte,
        'perfil'         => $perfilRevision,
        'unidad'         => $unidad,
    ];

    return response()->json([
        'ok'                  => true,
        'estado_registro'     => $estadoRegistro,
        // 'perfil_completo'   => (bool)($user->perfil_completo ?? false), // informativo si lo querés
        'aporte_inicial'      => $aporte,
        'perfil'              => $perfilRevision,
        'unidad'              => $unidad,
        'ready_for_dashboard' => $ready,
        'next_step'           => $nextStep,
        'redirect_to'         => $redirect,
        'estado'              => $estado,
    ]);
}

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
                    'uu.estado_asignacion',
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