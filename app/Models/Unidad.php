<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unidad extends Model
{
    protected $table = 'unidades';
    protected $fillable = [
        'codigo','descripcion','dormitorios','m2','estado_unidad'
    ];
}