<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class Usuario extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'usuarios';
    // Usamos el PK entero 'id' que creó tu migración (coincide con Sanctum).
    // Podrías omitir estas 3 líneas porque son los defaults de Laravel, pero las dejo explícitas.
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'ci_usuario',
        'primer_nombre',
        'segundo_nombre',
        'primer_apellido',
        'segundo_apellido',
        'nombre',
        'email',
        'telefono',
        'password',
        'estado_registro',
        'rol',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'estado_registro' => 'string',
        'rol'             => 'string',
    ];
}