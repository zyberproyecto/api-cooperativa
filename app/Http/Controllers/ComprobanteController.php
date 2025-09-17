<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ComprobanteController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['ok' => false, 'msg' => 'No autenticado.'], 401);
        }

        $ci = preg_replace('/\D/', '', (string)($user->ci_usuario ?? ''));
        if ($ci === '' || strlen($ci) !== 8) {
            return response()->json(['ok' => false, 'msg' => 'CI inválido para la operación.'], 400);
        }

        if (!Schema::hasTable('comprobantes')) {
            return response()->json(['ok' => false, 'msg' => 'Tabla comprobantes inexistente.'], 500);
        }

        $data = $request->validate([
            'tipo'    => ['required', 'in:aporte_mensual,aporte_inicial,compensatorio'],
            'periodo' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/', 'required_if:tipo,aporte_mensual,compensatorio'],
            'monto'   => ['required', 'numeric', 'min:0'],
            'archivo' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
        ]);

        // 1) Reglas de flujo
        $perfilCompleto = (bool)($user->perfil_completo ?? false);
        if (!$perfilCompleto && $data['tipo'] !== 'aporte_inicial') {
            return response()->json([
                'ok'  => false,
                'msg' => 'Debes completar tu perfil para enviar este tipo de comprobante.',
            ], 422);
        }

        // Para mensuales/compensatorio: exigir unidad asignada
        if (in_array($data['tipo'], ['aporte_mensual','compensatorio'], true)) {
            if (!Schema::hasTable('usuario_unidad')) {
                return response()->json(['ok' => false, 'msg' => 'No está configurada la tabla de asignaciones.'], 500);
            }
            $tieneUnidad = DB::table('usuario_unidad')
                ->where('ci_usuario', $ci)
                ->where('estado', 'activa')
                ->exists();
            if (!$tieneUnidad) {
                return response()->json([
                    'ok'  => false,
                    'msg' => 'No tienes una unidad asignada. No puedes enviar aportes mensuales ni compensatorios.',
                ], 422);
            }
        }

        if (!$request->hasFile('archivo') || !$request->file('archivo')->isValid()) {
            return response()->json([
                'ok'  => false,
                'msg' => 'Debes adjuntar un archivo válido (PDF/JPG/PNG).',
            ], 422);
        }

        // 2) Unicidad lógica: evitar duplicados molestos
        $cols = Schema::getColumnListing('comprobantes');
        $colTipo = in_array('tipo_aporte', $cols, true) ? 'tipo_aporte' : 'tipo';

        // Aporte inicial: permitir solo 1 pendiente/aprobado. Si el último es rechazado, permitir reintento.
        if ($data['tipo'] === 'aporte_inicial') {
            $existeAI = DB::table('comprobantes')
                ->where('ci_usuario', $ci)
                ->whereIn($colTipo, ['inicial','aporte_inicial'])
                ->whereIn('estado', ['pendiente','aprobado'])
                ->exists();
            if ($existeAI) {
                return response()->json([
                    'ok'  => false,
                    'msg' => 'Ya existe un aporte inicial pendiente o aprobado.',
                ], 422);
            }
        }

        // Mensual/Compensatorio: evitar duplicar mismo periodo+tipo si ya hay pendiente/aprobado
        if (in_array($data['tipo'], ['aporte_mensual','compensatorio'], true)) {
            $existePeriodo = DB::table('comprobantes')
                ->where('ci_usuario', $ci)
                ->where($colTipo, $data['tipo'])
                ->when(in_array('periodo', $cols, true), fn($q) => $q->where('periodo', $data['periodo']))
                ->whereIn('estado', ['pendiente','aprobado'])
                ->exists();
            if ($existePeriodo) {
                return response()->json([
                    'ok'  => false,
                    'msg' => 'Ya existe un comprobante de ese tipo para el período indicado (pendiente o aprobado).',
                ], 422);
            }
        }

        // 3) Guardar archivo
        try {
            $path      = $request->file('archivo')->store("comprobantes/{$ci}", 'public');
            $publicUrl = Storage::url($path);
        } catch (\Throwable $e) {
            Log::error('Comprobantes@store: fallo guardando archivo', ['ci' => $ci, 'error' => $e->getMessage()]);
            return response()->json([
                'ok'  => false,
                'msg' => 'No se pudo guardar el archivo.',
                'err' => $e->getMessage(),
            ], 500);
        }

        // 4) Insert DB
        try {
            $insert = [
                'ci_usuario' => $ci,
                'archivo'    => $publicUrl,
                'estado'     => 'pendiente',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $insert[$colTipo] = $data['tipo'];

            $esMensualOComp = in_array($data['tipo'], ['aporte_mensual', 'compensatorio'], true);
            if (in_array('periodo', $cols, true)) {
                $insert['periodo'] = $esMensualOComp ? ($data['periodo'] ?? null) : null;
            }

            if (in_array('monto', $cols, true)) {
                $insert['monto'] = $data['monto'];
            }

            $id = DB::table('comprobantes')->insertGetId($insert);

        } catch (\Throwable $e) {
            try { Storage::disk('public')->delete($path); } catch (\Throwable $ignored) {}
            Log::error('Comprobantes@store: fallo insert DB', [
                'ci' => $ci,
                'tipo' => $data['tipo'] ?? null,
                'periodo' => $data['periodo'] ?? null,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'ok'  => false,
                'msg' => 'No se pudo guardar el comprobante.',
                'err' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'ok'      => true,
            'id'      => $id,
            'msg'     => 'Comprobante cargado.',
            'archivo' => $publicUrl,
        ], 201);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['ok' => false, 'msg' => 'No autenticado.'], 401);
        }

        $ci = preg_replace('/\D/', '', (string)($user->ci_usuario ?? ''));
        if ($ci === '' || strlen($ci) !== 8) {
            return response()->json(['ok' => false, 'msg' => 'CI inválido.'], 400);
        }

        if (!Schema::hasTable('comprobantes')) {
            return response()->json(['ok' => false, 'msg' => 'Tabla comprobantes inexistente.'], 500);
        }

        $cols = Schema::getColumnListing('comprobantes');
        $colTipo = in_array('tipo_aporte', $cols, true) ? 'tipo_aporte' : 'tipo';

        $items = DB::table('comprobantes')
            ->where('ci_usuario', $ci)
            ->orderByDesc('created_at')
            ->get()
            // normalizar: asegurar que siempre haya clave 'tipo'
            ->map(function ($it) use ($colTipo) {
                $it->tipo = $it->{$colTipo} ?? null;
                return $it;
            });

        $resumen = [
            'pendientes' => $items->where('estado', 'pendiente')->count(),
            'aprobados'  => $items->where('estado', 'aprobado')->count(),
            'rechazados' => $items->where('estado', 'rechazado')->count(),
        ];

        return response()->json(['ok' => true, 'resumen' => $resumen, 'items' => $items], 200);
    }
}