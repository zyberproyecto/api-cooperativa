<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ExoneracionesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $semana2 = Carbon::now()->startOfWeek();

        DB::table('exoneraciones')->insertOrIgnore([
            [
                'ci_usuario'    => '22222222',
                'semana_inicio' => $semana2->toDateString(),
                'motivo'        => 'Enfermedad familiar',
                'estado'        => 'pendiente',
                'resolucion_admin' => null,
                'archivo'       => null,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
        ]);
    }
}