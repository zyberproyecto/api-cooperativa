<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSolicitudRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SolicitudController extends Controller
{
    public function store(StoreSolicitudRequest $request)
    {
        $data     = method_exists($request, 'validated') ? $request->validated() : $request->all();
        $columns  = Schema::getColumnListing('solicitudes');

        $get = fn ($k, $alt = null) => $data[$k] ?? ($alt ? ($data[$alt] ?? null) : null);
        $has = fn ($col)             => in_array($col, $columns, true);
        $put = function (&$row, $col, $val) use ($has) {
            if ($val !== null && $has($col)) {
                $row[$col] = $val;
            }
        };

        $ci = trim((string)($get('ci') ?? $get('ci_usuario') ?? ''));
        if ($ci === '') {
            return response()->json(['ok' => false, 'message' => 'El campo CI es obligatorio'], 422);
        }

        $email = strtolower(trim((string)($get('email') ?? '')));
        if ($email === '') {
            return response()->json(['ok' => false, 'message' => 'El email es obligatorio'], 422);
        }

        $row = [];

        $put($row, 'ci',         $ci);
        $put($row, 'ci_usuario', $ci);

        $nombre   = trim((string)($get('nombre') ?? $get('nombre_completo') ?? ''));
        $telefono = trim((string)($get('telefono') ?? ''));

        $put($row, 'nombre',          $nombre ?: null);
        $put($row, 'nombre_completo', $nombre ?: null);
        $put($row, 'email',           $email);
        $put($row, 'telefono',        $telefono ?: null);

        $menoresRaw = $get('menores_a_cargo') ?? $get('menores_cargo');
        if ($menoresRaw !== null) {
            $bool    = filter_var($menoresRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $menores = $bool === null ? (int)$menoresRaw : ($bool ? 1 : 0);
        } else {
            $menores = 0;
        }
        $put($row, 'menores_a_cargo', $menores);

        $dormsRaw = $get('dormitorios') ?? $get('dormitorios_solicitados');
        $dorms    = $dormsRaw !== null ? max(1, min(3, (int)$dormsRaw)) : null;
        if ($dorms !== null) {
            $put($row, 'dormitorios', $dorms);
        }

        $coment = trim((string)($get('comentarios') ?? $get('mensaje') ?? ''));
        $put($row, 'comentarios', $coment ?: null);
        $put($row, 'mensaje',     $coment ?: null);

        $put($row, 'intereses', $get('intereses'));

        if ($has('estado'))           { $row['estado']           = 'pendiente'; }
        if ($has('estado_solicitud')) { $row['estado_solicitud'] = 'pendiente'; }
        if ($has('status'))           { $row['status']           = 'pendiente'; }

        $now    = now();
        $keyCol = $has('ci') ? 'ci' : ($has('ci_usuario') ? 'ci_usuario' : null);
        if (!$keyCol) {
            return response()->json(['ok' => false, 'message' => 'La tabla solicitudes no tiene columna ci ni ci_usuario'], 500);
        }

        try {
            $exists = DB::table('solicitudes')->where($keyCol, $ci)->exists();

            if ($exists) {
                $row['updated_at'] = $now;
                DB::table('solicitudes')->where($keyCol, $ci)->update($row);
                $id = DB::table('solicitudes')->where($keyCol, $ci)->value('id');

                return response()->json([
                    'ok'      => true,
                    'id'      => $id,
                    'message' => 'Solicitud actualizada',
                ], 200);
            } else {
                $row['created_at'] = $now;
                $row['updated_at'] = $now;
                $id = DB::table('solicitudes')->insertGetId($row);

                return response()->json([
                    'ok'      => true,
                    'id'      => $id,
                    'message' => 'Solicitud creada',
                ], 201);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'No se pudo guardar la solicitud',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

   
    public function index(Request $request)
    {
        $columns = Schema::getColumnListing('solicitudes');
        $has     = fn ($col) => in_array($col, $columns, true);

        $q = DB::table('solicitudes')->orderByDesc('created_at');

        if ($estado = $request->query('estado')) {
            $estadoNorm = strtolower(trim((string)$estado)); // ej. "pendiente"
            if     ($has('estado'))           { $q->whereRaw('LOWER(estado) = ?', [$estadoNorm]); }
            elseif ($has('estado_solicitud')) { $q->whereRaw('LOWER(estado_solicitud) = ?', [$estadoNorm]); }
            elseif ($has('status'))           { $q->whereRaw('LOWER(status) = ?', [$estadoNorm]); }
        }

        if ($ci = $request->query('ci')) {
            $ci = trim((string)$ci);
            if     ($has('ci'))         { $q->where('ci', $ci); }
            elseif ($has('ci_usuario')) { $q->where('ci_usuario', $ci); }
        }

        return response()->json([
            'ok'    => true,
            'items' => $q->get(),
        ]);
    }
}