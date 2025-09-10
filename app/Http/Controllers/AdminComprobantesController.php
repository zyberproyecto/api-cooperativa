<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminComprobantesController extends Controller
{
    /**
     * GET /api/admin/comprobantes?estado=pendiente|aprobado|rechazado|todos&tipo=inicial|aporte_inicial|mensual
     */
    public function index(Request $r)
    {
        $estado = strtolower((string)$r->query('estado', 'pendiente'));   // pendiente|aprobado|rechazado|todos
        $tipo   = $r->query('tipo');                                      // inicial|aporte_inicial|mensual|null

        // Normalizar estado a los valores reales de la BD (enum: pendiente/aprobado/rechazado)
        $estadoNorm = match ($estado) {
            'pendiente', 'aprobado', 'rechazado' => $estado,
            'todos' => 'todos',
            default => 'pendiente',
        };

        // Normalizar tipo: alias "inicial" -> "aporte_inicial" (enum real: aporte_inicial|mensual)
        $tipoNorm = match ($tipo) {
            'inicial' => 'aporte_inicial',
            'aporte_inicial', 'mensual' => $tipo,
            default => null,
        };

        $q = DB::table('comprobantes');

        if ($estadoNorm !== 'todos') {
            $q->whereRaw('LOWER(estado) = ?', [$estadoNorm]);
        }

        if ($tipoNorm) {
            $q->where('tipo', $tipoNorm);
        }

        $rows = $q->orderByDesc('id')->get();

        return response()->json(['ok' => true, 'items' => $rows]);
    }

    /**
     * PUT /api/admin/comprobantes/{id}/validar
     * Body opcional: { "nota_admin": "..." }
     */
    public function validar(Request $r, int $id)
    {
        $nota = $r->input('nota_admin');

        $data = [
            'estado'     => 'aprobado',
            'updated_at' => now(),
        ];
        if ($nota !== null) {
            $data['nota_admin'] = $nota;
        }

        $aff = DB::table('comprobantes')->where('id', $id)->update($data);

        return $aff
            ? response()->json(['ok' => true, 'message' => "Comprobante #{$id} aprobado."])
            : response()->json(['ok' => false, 'message' => 'Comprobante no encontrado'], 404);
    }

    /**
     * PUT /api/admin/comprobantes/{id}/rechazar
     * Body opcional: { "motivo": "..." }
     */
    public function rechazar(Request $r, int $id)
    {
        $motivo = $r->input('motivo');

        $data = [
            'estado'     => 'rechazado',
            'updated_at' => now(),
        ];
        if ($motivo !== null) {
            $data['motivo_rechazo'] = $motivo;
        }

        $aff = DB::table('comprobantes')->where('id', $id)->update($data);

        return $aff
            ? response()->json(['ok' => true, 'message' => "Comprobante #{$id} rechazado."])
            : response()->json(['ok' => false, 'message' => 'Comprobante no encontrado'], 404);
    }
}