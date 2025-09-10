<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ComprobanteController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();
        $ci   = $user->ci_usuario ?? $user->ci ?? null;
        if (!$ci) {
            return response()->json(['ok' => false, 'msg' => 'No se pudo determinar el CI del usuario.'], 400);
        }

        $data = $request->validate([
            'tipo'       => ['required', 'in:mensual,aporte_inicial,inicial'],
            'monto'      => ['nullable', 'numeric'],
            'fecha_pago' => ['nullable', 'date'],
            'archivo'    => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
        ]);

        if (!$request->hasFile('archivo')) {
            return response()->json(['ok' => false, 'msg' => 'No se adjuntó archivo.'], 422);
        }

        $tipo = ($data['tipo'] === 'inicial') ? 'aporte_inicial' : $data['tipo'];

        $cols  = Schema::getColumnListing('comprobantes');
        $colCi = in_array('ci_usuario', $cols, true) ? 'ci_usuario' : (in_array('ci', $cols, true) ? 'ci' : null);
        if (!$colCi) {
            return response()->json(['ok' => false, 'msg' => 'La tabla comprobantes no tiene columna ci_usuario ni ci.'], 500);
        }

        // 👇 ahora guardamos explícitamente en el DISCO 'public'
        $path = $request->file('archivo')->store("comprobantes/{$ci}", 'public');
        $publicUrl = Storage::url($path); // /storage/comprobantes/{ci}/...

        try {
            $id = DB::table('comprobantes')->insertGetId([
                $colCi       => $ci,
                'tipo'       => $tipo,
                'archivo'    => $publicUrl,
                'monto'      => $data['monto']      ?? null,
                'fecha_pago' => $data['fecha_pago'] ?? null,
                'estado'     => 'pendiente',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            try { Storage::disk('public')->delete($path); } catch (\Throwable $ignored) {}
            return response()->json([
                'ok'    => false,
                'msg'   => 'No se pudo guardar el comprobante.',
                'error' => $e->getMessage(),
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
        $ci   = $user->ci_usuario ?? $user->ci ?? null;
        if (!$ci) {
            return response()->json(['ok' => false, 'msg' => 'No se pudo determinar el CI del usuario.'], 400);
        }

        $cols  = Schema::getColumnListing('comprobantes');
        $colCi = in_array('ci_usuario', $cols, true) ? 'ci_usuario' : (in_array('ci', $cols, true) ? 'ci' : null);
        if (!$colCi) {
            return response()->json(['ok' => false, 'msg' => 'La tabla comprobantes no tiene columna ci_usuario ni ci.'], 500);
        }

        $items = DB::table('comprobantes')
            ->where($colCi, $ci)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['ok' => true, 'items' => $items]);
    }
}