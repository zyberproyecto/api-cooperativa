<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ComprobanteController extends Controller
{
    /** POST /api/comprobantes  (auth:sanctum, multipart/form-data) */
    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['ok' => false, 'msg' => 'No autenticado.'], 401);
        }

        // CI solo dígitos
        $ci = preg_replace('/\D/', '', (string)($user->ci_usuario ?? ''));
        if ($ci === '') {
            return response()->json(['ok' => false, 'msg' => 'No se pudo determinar el CI del usuario.'], 400);
        }

        // ✅ Reglas:
        // - tipo ∈ {mensual, aporte_inicial, compensatorio}
        // - periodo: requerido si tipo ∈ {mensual, compensatorio} (YYYY-MM)
        // - monto:   requerido SIEMPRE (incluye aporte_inicial)
        // - archivo: adjunto obligatorio
        $data = $request->validate([
            'tipo'    => ['required', 'in:aporte_mensual,aporte_inicial,compensatorio'],
            'periodo' => [
                'nullable',
                'regex:/^\d{4}-(0[1-9]|1[0-2])$/',
                'required_if:tipo,aporte_mensual,compensatorio',
            ],
            'monto'   => ['required', 'numeric', 'min:0'], // <- ahora obligatorio también para aporte_inicial
            'archivo' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
        ]);

        if (!Schema::hasTable('comprobantes')) {
            return response()->json(['ok' => false, 'msg' => 'Tabla comprobantes inexistente.'], 500);
        }

        // Guardar archivo en /storage (disco public)
        $path      = $request->file('archivo')->store("comprobantes/{$ci}", 'public');
        $publicUrl = Storage::url($path);

        try {
            $cols   = Schema::getColumnListing('comprobantes');

            $insert = [
                'ci_usuario' => $ci,
                'tipo'       => $data['tipo'],
                'archivo'    => $publicUrl,
                'estado'     => 'pendiente',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (in_array('periodo', $cols, true)) $insert['periodo'] = $data['periodo'] ?? null;
            if (in_array('monto',   $cols, true)) $insert['monto']   = $data['monto'];

            $id = DB::table('comprobantes')->insertGetId($insert);

        } catch (\Throwable $e) {
            try { Storage::disk('public')->delete($path); } catch (\Throwable $ignored) {}
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

    /** GET /api/comprobantes/estado  (auth:sanctum) */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['ok' => false, 'msg' => 'No autenticado.'], 401);
        }

        $ci = preg_replace('/\D/', '', (string)($user->ci_usuario ?? ''));
        if ($ci === '') {
            return response()->json(['ok' => false, 'msg' => 'No se pudo determinar el CI del usuario.'], 400);
        }

        if (!Schema::hasTable('comprobantes')) {
            return response()->json(['ok' => false, 'msg' => 'Tabla comprobantes inexistente.'], 500);
        }

        $items = DB::table('comprobantes')
            ->where('ci_usuario', $ci)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['ok' => true, 'items' => $items], 200);
    }
}