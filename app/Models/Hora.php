<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hora extends Model
{
    protected $table = 'horas';

    // ← usa los nombres REALES de tu tabla
    protected $fillable = ['ci_usuario', 'fecha', 'cantidad', 'descripcion'];

    protected $casts = [
        'fecha'    => 'date',
        'cantidad' => 'integer',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'ci_usuario', 'ci_usuario');
    }
}