<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Solicitud extends Model
{
    protected $table = 'solicitudes';
    protected $fillable = [
        'ci','nombre','email','telefono','menores_cargo','intereses','mensaje','estado',
        // (si te quedaron campos viejos, no molestan)
        'primer_nombre','segundo_nombre','primer_apellido','segundo_apellido','motivacion',
    ];
}