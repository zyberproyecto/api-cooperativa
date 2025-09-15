<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UnidadesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = [];
        $cod = fn (int $n) => 'U-' . str_pad((string)$n, 3, '0', STR_PAD_LEFT);

        for ($i = 1; $i <= 12; $i++) {
            $dorm = ($i % 3) + 1;
            $m2   = match ($dorm) { 1 => 42.50, 2 => 55.75, 3 => 68.90 };

            $rows[] = [
                'codigo'        => $cod($i),
                'descripcion'   => "Unidad {$cod($i)} - {$dorm} dormitorios",
                'dormitorios'   => $dorm,
                'm2'            => $m2,
                'estado_unidad' => 'disponible',
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        DB::table('unidades')->insert($rows);
    }
}