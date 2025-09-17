<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ComprobantesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('comprobantes')->insertOrIgnore([
        
            [
                'ci_usuario' => '22222222',
                'tipo'       => 'aporte_inicial',
                'periodo'    => null,
                'monto'      => 15000.00,
                'archivo'    => '/storage/comprobantes/22222222/aporte_inicial.pdf',
                'estado'     => 'aprobado',
                'nota_admin' => 'OK',
                'aprobado_por'=> 1,
                'aprobado_at'=> $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            
            [
                'ci_usuario' => '33333333',
                'tipo'       => 'aporte_inicial',
                'periodo'    => null,
                'monto'      => 15000.00,
                'archivo'    => '/storage/comprobantes/33333333/aporte_inicial.pdf',
                'estado'     => 'pendiente',
                'nota_admin' => null,
                'aprobado_por'=> null,
                'aprobado_at'=> null,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'ci_usuario' => '22222222',
                'tipo'       => 'aporte_mensual',
                'periodo'    => '2025-08',
                'monto'      => 3500.00,
                'archivo'    => '/storage/comprobantes/22222222/mensual_2025-08.pdf',
                'estado'     => 'aprobado',
                'nota_admin' => 'OK',
                'aprobado_por'=> 1,
                'aprobado_at'=> $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}