<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Comprobante extends Model
{
    protected $table = 'comprobantes';

    // agrega todos los que uses en create()
    protected $fillable = [
        'ci_usuario','archivo_path','fecha','concepto','descripcion','estado'
    ];

    // (opcional) url calculada
    protected $appends = ['url'];
    public function getUrlAttribute(){ return asset('storage/'.$this->archivo_path); }
}