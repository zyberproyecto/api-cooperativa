<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HorasTrabajoSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $semana1 = Carbon::now()->startOfWeek()->subWeek();
        $semana2 = Carbon::now()->startOfWeek();

        DB::table('horas_trabajo')->insertOrIgnore([
 
            [
                'ci_usuario'       => '22222222',
                'semana_inicio'    => $semana1->toDateString(),
                'semana_fin'       => $semana1->copy()->addDays(6)->toDateString(),
                'horas_reportadas' => 21,
                'motivo'           => null,
                'estado'           => 'aprobado',
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
         
            [
                'ci_usuario'       => '22222222',
                'semana_inicio'    => $semana2->toDateString(),
                'semana_fin'       => $semana2->copy()->addDays(6)->toDateString(),
                'horas_reportadas' => 18,
                'motivo'           => 'Enfermedad familiar',
                'estado'           => 'reportado',
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
        ]);
    }
}