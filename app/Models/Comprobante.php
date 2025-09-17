<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Comprobante extends Model {
  protected $table='comprobantes';
  protected $fillable=['ci_usuario',
  'tipo',
  'periodo',
  'monto',
  'archivo',
  'estado',
  'nota_admin',
  'aprobado_por',
  'aprobado_at'];

  public const ESTADO_PENDIENTE='pendiente'; 
  public const ESTADO_APROBADO='aprobado'; 
  public const ESTADO_RECHAZADO='rechazado';
  public const TIPO_APORTE_INICIAL='aporte_inicial'; 
  public const TIPO_APORTE_MENSUAL='aporte_mensual'; 
  public const TIPO_COMPENSATORIO='compensatorio';
  protected $casts=['aprobado_at'=>
  'datetime','monto'=>'decimal:2'];
}