<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HorasController extends Controller
{
    /**
     * Socio: registrar horas
     * POST /api/horas
     */
    public function store(Request $r)
    {
        $u  = $r->user();
        $ci = $u->ci_usuario ?? $u->ci ?? null;
        if (!$ci) {
            return response()->json(['ok' => false, 'message' => 'Usuario inválido.'], 401);
        }

        $data = $r->validate([
            // ISO-8601 (YYYY-Www). Aceptamos otros strings pero recomendamos este formato.
            'semana' => ['required','string','max:20'],
            'horas'  => ['required','integer','min:0','max:100'],
            'motivo' => ['nullable','string','max:1000'], // requerido si horas < 21
        ]);

        $horas  = (int) $data['horas'];
        $motivo = trim((string) ($data['motivo'] ?? ''));

        if ($horas < 21 && $motivo === '') {
            return response()->json([
                'ok'      => false,
                'message' => 'Si las horas son menores a 21, debe indicar un motivo.',
            ], 422);
        }

        // --- Resolver columnas dinámicas ---
        if (!Schema::hasTable('horas_trabajo')) {
            return response()->json(['ok' => false, 'message' => 'Falta la tabla horas_trabajo.'], 500);
        }
        $colsHoras   = Schema::getColumnListing('horas_trabajo');
        $colCiHoras  = in_array('ci_usuario', $colsHoras, true) ? 'ci_usuario'
                      : (in_array('ci', $colsHoras, true) ? 'ci' : null);
        $hasDescCol  = in_array('descripcion', $colsHoras, true);

        if (!$colCiHoras) {
            return response()->json(['ok' => false, 'message' => 'Tabla horas_trabajo sin columna CI (ci_usuario/ci).'], 500);
        }

        // Upsert por (ci, semana) para evitar duplicados del mismo período
        $now            = now();
        $payloadUpdate  = [
            'horas'      => $horas,
            'estado'     => 'pendiente', // si reenvía, vuelve a pendiente
            'updated_at' => $now,
        ];
        if ($hasDescCol && $horas < 21) {
            $payloadUpdate['descripcion'] = $motivo; // guardamos motivo
        }

        $existsId = DB::table('horas_trabajo')
            ->where($colCiHoras, $ci)
            ->where('semana', $data['semana'])
            ->value('id');

        if ($existsId) {
            DB::table('horas_trabajo')->where('id', $existsId)->update($payloadUpdate);
            $id = $existsId;
        } else {
            $payloadInsert = [
                $colCiHoras => $ci,
                'semana'    => $data['semana'],
                'horas'     => $horas,
                'estado'    => 'pendiente',
                'created_at'=> $now,
                'updated_at'=> $now,
            ];
            if ($hasDescCol && $horas < 21) {
                $payloadInsert['descripcion'] = $motivo;
            }
            $id = DB::table('horas_trabajo')->insertGetId($payloadInsert);
        }

        // --- Manejo de exoneraciones ligado al mismo periodo ---
        $exoneracion = null;
        if (Schema::hasTable('exoneraciones')) {
            $colsExo  = Schema::getColumnListing('exoneraciones');
            $colCiExo = in_array('ci_usuario', $colsExo, true) ? 'ci_usuario'
                      : (in_array('ci', $colsExo, true) ? 'ci' : null);

            if ($colCiExo) {
                if ($horas < 21) {
                    // upsert exoneración a pendiente
                    $exoId = DB::table('exoneraciones')
                        ->where($colCiExo, $ci)
                        ->where('periodo', $data['semana'])
                        ->value('id');

                    if ($exoId) {
                        DB::table('exoneraciones')->where('id', $exoId)->update([
                            'motivo'     => $motivo,
                            'estado'     => 'pendiente',
                            'updated_at' => $now,
                        ]);
                        $exoneracion = ['id' => $exoId, 'estado' => 'pendiente', 'action' => 'updated'];
                    } else {
                        $exoId = DB::table('exoneraciones')->insertGetId([
                            $colCiExo   => $ci,
                            'periodo'   => $data['semana'],
                            'motivo'    => $motivo,
                            'estado'    => 'pendiente',
                            'created_at'=> $now,
                            'updated_at'=> $now,
                        ]);
                        $exoneracion = ['id' => $exoId, 'estado' => 'pendiente', 'action' => 'created'];
                    }
                } else {
                    // si ahora cumple 21+, cancelar exoneración pendiente del período
                    $aff = DB::table('exoneraciones')
                        ->where($colCiExo, $ci)
                        ->where('periodo', $data['semana'])
                        ->where('estado', 'pendiente')
                        ->update(['estado' => 'rechazado', 'updated_at' => $now]);

                    if ($aff) {
                        $exoneracion = ['estado' => 'rechazado', 'action' => 'auto_cancelled'];
                    }
                }
            }
        }

        return response()->json([
            'ok'          => true,
            'id'          => $id,
            'exoneracion' => $exoneracion, // null si no aplica
        ], $existsId ? 200 : 201);
    }

    /**
     * Socio: ver MIS horas
     * GET /api/horas   (bloque autenticado)
     */
    public function index(Request $r)
    {
        $u  = $r->user();
        $ci = $u->ci_usuario ?? $u->ci ?? null;
        if (!$ci) {
            return response()->json(['ok' => false, 'message' => 'Usuario inválido.'], 401);
        }

        $colsHoras  = Schema::getColumnListing('horas_trabajo');
        $colCiHoras = in_array('ci_usuario', $colsHoras, true)
            ? 'ci_usuario'
            : (in_array('ci', $colsHoras, true) ? 'ci' : null);

        if (!$colCiHoras) {
            return response()->json(['ok' => false, 'message' => 'Tabla horas_trabajo sin columna CI (ci_usuario/ci).'], 500);
        }

        $items = DB::table('horas_trabajo')
            ->where($colCiHoras, $ci)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json(['ok' => true, 'items' => $items]);
    }

    /**
     * Admin: listar horas por estado (default: pendientes)
     * GET /api/admin/horas?estado=pendiente|aprobado|rechazado|todos&ci=XXXX
     */
    public function adminIndex(Request $r)
    {
        $estado = strtolower($r->query('estado', 'pendiente'));
        $q = DB::table('horas_trabajo');

        if ($estado !== 'todos') {
            $q->whereRaw('LOWER(estado) = ?', [$estado]);
        }

        if ($ci = $r->query('ci')) {
            $ci = trim((string)$ci);
            $colsHoras  = Schema::getColumnListing('horas_trabajo');
            $colCiHoras = in_array('ci_usuario', $colsHoras, true)
                ? 'ci_usuario'
                : (in_array('ci', $colsHoras, true) ? 'ci' : null);
            if ($colCiHoras) {
                $q->where($colCiHoras, $ci);
            }
        }

        $rows = $q->orderByDesc('id')->get();

        return response()->json(['ok' => true, 'items' => $rows]);
    }

    /**
     * Admin: aprobar
     * PUT /api/admin/horas/{id}/validar
     */
    public function validar(int $id)
    {
        $aff = DB::table('horas_trabajo')->where('id', $id)->update([
            'estado'     => 'aprobado',
            'updated_at' => now(),
        ]);

        return $aff
            ? response()->json(['ok' => true])
            : response()->json(['ok' => false, 'message' => 'Registro no encontrado'], 404);
    }

    /**
     * Admin: rechazar
     * PUT /api/admin/horas/{id}/rechazar
     */
    public function rechazar(int $id, Request $r)
    {
        $motivo = (string)($r->input('motivo') ?? '');

        $hora = DB::table('horas_trabajo')->where('id', $id)->first();
        if (!$hora) {
            return response()->json(['ok' => false, 'message' => 'Registro no encontrado'], 404);
        }

        DB::table('horas_trabajo')->where('id', $id)->update([
            'estado'     => 'rechazado',
            'updated_at' => now(),
        ]);

        // Rechazar exoneración del mismo CI + periodo (si existe)
        $colsExo  = Schema::getColumnListing('exoneraciones');
        $colCiExo = in_array('ci_usuario', $colsExo, true)
            ? 'ci_usuario'
            : (in_array('ci', $colsExo, true) ? 'ci' : null);

        if ($colCiExo && isset($hora->semana)) {
            // Resolver columna CI en horas_trabajo para extraer valor correctamente
            $colsHoras  = Schema::getColumnListing('horas_trabajo');
            $colCiHoras = in_array('ci_usuario', $colsHoras, true)
                ? 'ci_usuario'
                : (in_array('ci', $colsHoras, true) ? 'ci' : null);

            $ciHora = $colCiHoras && isset($hora->{$colCiHoras}) ? $hora->{$colCiHoras} : null;

            if ($ciHora) {
                DB::table('exoneraciones')
                    ->where('periodo', $hora->semana)
                    ->where($colCiExo, $ciHora)
                    ->update([
                        'estado'     => 'rechazado',
                        'updated_at' => now(),
                    ]);
            }
        }

        return response()->json(['ok' => true]);
    }
}