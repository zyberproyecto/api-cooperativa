<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UsuarioUnidadSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $unidad = DB::table('unidades')->where('estado_unidad', 'disponible')->first();
        if (!$unidad) return;

        DB::table('usuario_unidad')->insertOrIgnore([
            'ci_usuario'       => '22222222',
            'unidad_id'        => $unidad->id,
            'fecha_asignacion' => $now->toDateString(),
            'estado'           => 'activa',
            'nota_admin'       => 'Asignación inicial',
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        DB::table('unidades')->where('id', $unidad->id)->update([
            'estado_unidad' => 'asignada',
            'updated_at'    => $now,
        ]);
    }
}