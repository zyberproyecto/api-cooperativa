<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HoraTrabajo extends Model
{
    protected $table = 'horas_trabajo';

    protected $fillable = [
        'ci_usuario',
        'semana_inicio',
        'semana_fin',
        'horas_reportadas',
        'motivo',
        'estado',
    ];

    public const ESTADO_REPORTADO = 'reportado';
    public const ESTADO_APROBADO  = 'aprobado';
    public const ESTADO_RECHAZADO = 'rechazado';

    protected $casts = [
        'semana_inicio'    => 'date',
        'semana_fin'       => 'date',
        'horas_reportadas' => 'float',
    ];
}