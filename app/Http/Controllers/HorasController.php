<?php

namespace App\Http\Controllers;

use App\Models\HoraTrabajo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class HorasController extends Controller
{
    public function store(Request $r)
    {
        $user = $r->user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'No autenticado'], 401);
        }

        $ci = preg_replace('/\D/', '', (string)($user->ci_usuario ?? $user->ci ?? ''));
        if ($ci === '') {
            return response()->json(['ok' => false, 'message' => 'Usuario inválido.'], 401);
        }

   
        $data = $r->validate([
            'semana_inicio'    => ['required','date_format:Y-m-d'],
            'horas_reportadas' => ['required','numeric','min:0','max:21'],   
            'motivo'           => ['nullable','string','max:1000'],
        ]);

        if ($data['horas_reportadas'] < 21 && empty($data['motivo'])) {
            throw ValidationException::withMessages([
                'motivo' => 'Si las horas son menores a 21, debe indicar un motivo.',
            ]);
        }

        $semanaInicio = $data['semana_inicio'];
        $semanaFin    = Carbon::parse($semanaInicio)->addDays(6)->toDateString();

        $existeId = HoraTrabajo::where('ci_usuario', $ci)
            ->whereDate('semana_inicio', $semanaInicio)
            ->value('id');

        if ($existeId) {
            HoraTrabajo::where('id', $existeId)->update([
                'horas_reportadas' => $data['horas_reportadas'],  
                'motivo'           => $data['motivo'] ?: null,
                'estado'           => HoraTrabajo::ESTADO_REPORTADO,
                'semana_fin'       => $semanaFin,
                'updated_at'       => now(),
            ]);
            $id     = $existeId;
            $status = 200;
        } else {
            $nuevo = HoraTrabajo::create([
                'ci_usuario'       => $ci,
                'semana_inicio'    => $semanaInicio,
                'semana_fin'       => $semanaFin,
                'horas_reportadas' => $data['horas_reportadas'],   
                'motivo'           => $data['motivo'] ?? null,
                'estado'           => HoraTrabajo::ESTADO_REPORTADO,
            ]);
            $id     = $nuevo->id;
            $status = 201;
        }

        $exoneracion = null;
        if (Schema::hasTable('exoneraciones')) {
            if ($data['horas_reportadas'] < 21) {
                $exoId = DB::table('exoneraciones')
                    ->where('ci_usuario', $ci)
                    ->whereDate('semana_inicio', $semanaInicio)
                    ->value('id');

                if ($exoId) {
                    DB::table('exoneraciones')->where('id', $exoId)->update([
                        'motivo'     => $data['motivo'],
                        'estado'     => 'pendiente',
                        'updated_at' => now(),
                    ]);
                    $exoneracion = ['id' => $exoId, 'estado' => 'pendiente', 'action' => 'updated'];
                } else {
                    $exoId = DB::table('exoneraciones')->insertGetId([
                        'ci_usuario'    => $ci,
                        'semana_inicio' => $semanaInicio,
                        'motivo'        => $data['motivo'],
                        'estado'        => 'pendiente',
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                    $exoneracion = ['id' => $exoId, 'estado' => 'pendiente', 'action' => 'created'];
                }
            } else {
                $aff = DB::table('exoneraciones')
                    ->where('ci_usuario', $ci)
                    ->whereDate('semana_inicio', $semanaInicio)
                    ->where('estado', 'pendiente')
                    ->update(['estado' => 'rechazado', 'updated_at' => now()]);
                if ($aff) {
                    $exoneracion = ['estado' => 'rechazado', 'action' => 'auto_cancelled'];
                }
            }
        }

        return response()->json(['ok' => true, 'id' => $id, 'exoneracion' => $exoneracion], $status);
    }

    public function index(Request $r)
    {
        $user = $r->user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'No autenticado'], 401);
        }

        $ci = preg_replace('/\D/', '', (string)($user->ci_usuario ?? $user->ci ?? ''));
        if ($ci === '') {
            return response()->json(['ok' => false, 'message' => 'Usuario inválido.'], 401);
        }

        $items = HoraTrabajo::where('ci_usuario', $ci)
            ->orderByDesc('semana_inicio')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json(['ok' => true, 'items' => $items]);
    }

    public function adminIndex(Request $r)
    {
        $estado = strtolower($r->query('estado', 'pendiente'));
        $q = DB::table('horas_trabajo');

        if ($estado !== 'todos') {
            $q->whereRaw('LOWER(estado) = ?', [$estado]);
        }

        if ($ci = $r->query('ci')) {
            $ci = preg_replace('/\D/', '', (string)$ci);
            if ($ci !== '') {
                $q->where('ci_usuario', $ci);
            }
        }

        $rows = $q->orderByDesc('id')->get();
        return response()->json(['ok' => true, 'items' => $rows]);
    }

    public function validar(int $id)
    {
        $aff = HoraTrabajo::where('id', $id)->update([
            'estado'     => HoraTrabajo::ESTADO_APROBADO,
            'updated_at' => now(),
        ]);

        return $aff
            ? response()->json(['ok' => true])
            : response()->json(['ok' => false, 'message' => 'Registro no encontrado'], 404);
    }

    public function rechazar(int $id)
    {
        $hora = HoraTrabajo::find($id);
        if (!$hora) {
            return response()->json(['ok' => false, 'message' => 'Registro no encontrado'], 404);
        }

        $hora->update([
            'estado'     => HoraTrabajo::ESTADO_RECHAZADO,
            'updated_at' => now(),
        ]);

        if (Schema::hasTable('exoneraciones')) {
            DB::table('exoneraciones')
                ->where('ci_usuario', $hora->ci_usuario)
                ->whereDate('semana_inicio', $hora->semana_inicio)
                ->where('estado', 'pendiente')
                ->update(['estado' => 'rechazado', 'updated_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }
}