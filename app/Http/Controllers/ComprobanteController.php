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

        $perfilCompleto = (bool)($user->perfil_completo ?? false);
        if (!$perfilCompleto && $data['tipo'] !== 'aporte_inicial') {
            return response()->json([
                'ok'  => false,
                'msg' => 'Debes completar tu perfil para enviar este tipo de comprobante.',
            ], 422);
        }

        if (!$request->hasFile('archivo') || !$request->file('archivo')->isValid()) {
            return response()->json([
                'ok'  => false,
                'msg' => 'Debes adjuntar un archivo válido (PDF/JPG/PNG).',
            ], 422);
        }

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

        try {
            $cols = Schema::getColumnListing('comprobantes');

            $insert = [
                'ci_usuario' => $ci,
                'archivo'    => $publicUrl,
                'estado'     => 'pendiente',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (in_array('tipo_aporte', $cols, true)) {
                $insert['tipo_aporte'] = $data['tipo'];
            } else {
                $insert['tipo'] = $data['tipo'];
            }

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

        $items = DB::table('comprobantes')
            ->where('ci_usuario', $ci)
            ->orderByDesc('created_at')
            ->get();

        $resumen = [
            'pendientes' => $items->where('estado', 'pendiente')->count(),
            'aprobados'  => $items->where('estado', 'aprobado')->count(),
            'rechazados' => $items->where('estado', 'rechazado')->count(),
        ];

        return response()->json(['ok' => true, 'resumen' => $resumen, 'items' => $items], 200);
    }
}