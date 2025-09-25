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

        $ci = $this->ciFromUser($user);
        if ($ci === '') {
            return response()->json(['ok' => false, 'message' => 'Usuario inválido'], 401);
        }

        $estadoRegistro = strtolower((string)($user->estado_registro ?? 'pendiente'));
        if (!in_array($estadoRegistro, ['pendiente','aprobado','rechazado'], true)) {
            $estadoRegistro = 'pendiente';
        }
        $aprobado = ($estadoRegistro === 'aprobado');

        $perfilRevision = 'incompleto';
        if (Schema::hasTable('usuarios_perfil')) {
            $perfilRow = DB::table('usuarios_perfil')->where('ci_usuario', $ci)->first();

            if ($perfilRow) {
                $completo =
                    (isset($perfilRow->ocupacion) && trim($perfilRow->ocupacion) !== '') &&
                    (!is_null($perfilRow->ingresos_nucleo_familiar)) &&
                    ((int)($perfilRow->integrantes_familia ?? 0) > 0) &&
                    (isset($perfilRow->contacto) && trim($perfilRow->contacto) !== '') &&
                    (isset($perfilRow->direccion) && trim($perfilRow->direccion) !== '') &&
                    ((int)($perfilRow->acepta_declaracion_jurada ?? 0) === 1) &&
                    ((int)($perfilRow->acepta_reglamento_interno ?? 0) === 1);

                if ($completo) {
                    $rev = strtolower((string)($perfilRow->estado_revision ?? 'pendiente'));
                    $perfilRevision = in_array($rev, ['pendiente','aprobado','rechazado'], true) ? $rev : 'pendiente';
                } else {
                    $perfilRevision = 'incompleto';
                }
            }
        }

        $aporte = 'no_presentado';
        if (Schema::hasTable('comprobantes')) {
            $comprob = DB::table('comprobantes')
                ->where('ci_usuario', $ci)
                ->where('tipo', 'aporte_inicial')
                ->orderByDesc('id')
                ->first();

            if ($comprob) {
                $est = strtolower((string)($comprob->estado ?? 'pendiente'));
                $aporte = in_array($est, ['pendiente','aprobado','rechazado'], true) ? $est : 'pendiente';
            }
        }

        $unidad = 'no_asignada';
        if (Schema::hasTable('usuario_unidad')) {
            $asign = DB::table('usuario_unidad')
                ->where('ci_usuario', $ci)
                ->where('estado', 'activa')
                ->first();

            if ($asign) {
                $unidad = 'asignada';
            }
        }

        $ready = (
            $aprobado &&
            $perfilRevision === 'aprobado' &&
            $aporte === 'aprobado' &&
            $unidad === 'asignada'
        );

        $nextStep = null;
        $redirect = null;

        if (!$aprobado) {
            $nextStep = $estadoRegistro === 'rechazado'
                ? 'solicitud_rechazada'
                : 'esperar_aprobacion_backoffice';
            $redirect = '/onboarding/espera-aprobacion';
        } elseif ($perfilRevision === 'rechazado' || $perfilRevision === 'incompleto') {
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

        return response()->json([
            'ok'                  => true,
            'estado_registro'     => $estadoRegistro,
            'aporte_inicial'      => $aporte,
            'perfil'              => $perfilRevision,
            'unidad'              => $unidad,
            'ready_for_dashboard' => $ready,
            'next_step'           => $nextStep,
            'redirect_to'         => $redirect,
            'estado'              => [
                'aporte_inicial' => $aporte,
                'perfil'         => $perfilRevision,
                'unidad'         => $unidad,
            ],
        ]);
    }

    public function perfil(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['ok' => false], 401);
        }

        $ci = $this->ciFromUser($user);
        if ($ci === '') {
            return response()->json(['ok' => false], 401);
        }

        $row = Schema::hasTable('usuarios_perfil')
            ? DB::table('usuarios_perfil')->where('ci_usuario', $ci)->first()
            : null;

        return response()->json(['ok' => true, 'perfil' => $row]);
    }

    public function guardarPerfil(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['ok' => false], 401);
        }

        $ci = $this->ciFromUser($user);
        if ($ci === '') {
            return response()->json(['ok' => false, 'message' => 'Usuario inválido'], 401);
        }

        if (!Schema::hasTable('usuarios_perfil')) {
            return response()->json(['ok' => false, 'message' => 'Tabla usuarios_perfil inexistente'], 500);
        }

        $data = $request->validate([
            'ocupacion'                 => ['nullable','string','max:255'],
            'ingresos_nucleo_familiar'  => ['nullable','integer','min:0'],
            'integrantes_familia'       => ['nullable','integer','min:0'],
            'contacto'                  => ['nullable','string','max:255'],
            'direccion'                 => ['nullable','string','max:255'],
            'acepta_declaracion_jurada' => ['nullable','boolean'],
            'acepta_reglamento_interno' => ['nullable','boolean'],
        ]);

        $row = [
            'ocupacion'                 => $data['ocupacion'] ?? '',
            'ingresos_nucleo_familiar'  => (int)($data['ingresos_nucleo_familiar'] ?? 0),
            'integrantes_familia'       => (int)($data['integrantes_familia'] ?? 0),
            'contacto'                  => $data['contacto'] ?? '',
            'direccion'                 => $data['direccion'] ?? '',
            'acepta_declaracion_jurada' => (int)($data['acepta_declaracion_jurada'] ?? 0),
            'acepta_reglamento_interno' => (int)($data['acepta_reglamento_interno'] ?? 0),
            'estado_revision'           => 'pendiente',  // siempre vuelve a pendiente hasta que backoffice revise
            'updated_at'                => now(),
        ];

        $exists = DB::table('usuarios_perfil')->where('ci_usuario', $ci)->exists();

        if ($exists) {
            DB::table('usuarios_perfil')->where('ci_usuario', $ci)->update($row);
        } else {
            $row['ci_usuario'] = $ci;
            if (Schema::hasColumn('usuarios_perfil', 'created_at')) {
                $row['created_at'] = now();
            }
            DB::table('usuarios_perfil')->insert($row);
        }

        return response()->json(['ok' => true]);
    }


    public function miUnidad(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['ok' => false], 401);
        }

        $ci = $this->ciFromUser($user);
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
                    DB::raw('uu.estado as estado_asignacion'),
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
            'ok'     => true,
            'unidad' => $row ?: null,
        ]);
    }

    private function ciFromUser($user): string
    {
        $ci = preg_replace('/\D/', '', (string)($user->ci_usuario ?? $user->ci ?? ''));
        return $ci ?? '';
    }
}