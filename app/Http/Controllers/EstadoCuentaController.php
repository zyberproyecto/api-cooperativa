<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EstadoCuentaController extends Controller
{
    /**
     * Estado de cuenta del socio autenticado.
     * - Genera los periodos desde el primer comprobante hasta el mes actual.
     * - Marca como pagado si hay comprobante aprobado del tipo aporte_mensual.
     * - Marca pendiente si es el mes actual sin pago.
     * - Marca atrasado si pasó más de 30 días del inicio del mes sin pago.
     */
    public function show(Request $request)
    {
        $user = $request->user();
        $ci   = preg_replace('/\D/', '', (string)($user->ci_usuario ?? $user->ci ?? ''));

        if (!$ci) {
            return response()->json(['ok' => false, 'msg' => 'Usuario sin CI definido.'], 422);
        }

        $MONTO_MENSUAL = 1500; // UYU

        // --- Traer comprobantes del socio ---
        $comprobantes = DB::table('comprobantes')
            ->select('id', 'periodo', 'estado', 'monto', 'aprobado_at', 'created_at')
            ->where('ci_usuario', $ci)
            ->whereIn('estado', ['pendiente', 'aprobado'])
            ->whereIn('tipo', ['aporte_mensual', 'aporte_mensual']) // compatibilidad
            ->get()
            ->groupBy('periodo');

        // --- Determinar inicio ---
        $primerComprobante = DB::table('comprobantes')
            ->where('ci_usuario', $ci)
            ->where('tipo', 'aporte_mensual')
            ->orderBy('periodo', 'asc')
            ->value('periodo');

        $inicio = $primerComprobante
            ? Carbon::createFromFormat('Y-m', $primerComprobante)->startOfMonth()
            : Carbon::parse($user->created_at ?? now())->startOfMonth();

        $fin = Carbon::now()->startOfMonth();

        // --- Generar periodos mensuales ---
        $periodos = [];
        $cursor = $inicio->copy();
        while ($cursor->lte($fin)) {
            $periodos[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        // --- Recorrer periodos ---
        $items = [];
        $resumen = ['pagados' => 0, 'pendientes' => 0, 'atrasados' => 0, 'adeudado' => 0];

        foreach ($periodos as $periodo) {
            $inicioMes = Carbon::createFromFormat('Y-m', $periodo)->startOfMonth();
            $vence = $inicioMes->copy()->addDays(30);
            $hoy = Carbon::now();

            $regs = $comprobantes->get($periodo, collect());
            $aprobado = $regs->firstWhere('estado', 'aprobado');

            if ($aprobado) {
                $items[] = [
                    'periodo'        => $periodo,
                    'estado'         => 'pagado',
                    'monto'          => $aprobado->monto ?? $MONTO_MENSUAL,
                    'vence'          => $vence->toDateString(),
                    'comprobante_id' => $aprobado->id,
                    'aprobado_at'    => $aprobado->aprobado_at,
                ];
                $resumen['pagados']++;
                continue;
            }

            $estado = $hoy->gt($vence) ? 'atrasado' : 'pendiente';
            $items[] = [
                'periodo'        => $periodo,
                'estado'         => $estado,
                'monto'          => $MONTO_MENSUAL,
                'vence'          => $vence->toDateString(),
                'comprobante_id' => null,
                'aprobado_at'    => null,
            ];

            if ($estado === 'pendiente') $resumen['pendientes']++;
            if ($estado === 'atrasado') {
                $resumen['atrasados']++;
                $resumen['adeudado'] += $MONTO_MENSUAL;
            }
        }

        return response()->json([
            'ok'      => true,
            'socio'   => [
                'ci_usuario' => $ci,
                'nombre'     => $user->nombre ?? $user->name ?? null,
            ],
            'resumen' => $resumen,
            'items'   => array_reverse($items), // más nuevo primero
        ]);
    }
}