<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable {
  use HasApiTokens;
  protected $table='usuarios';
  protected $primaryKey='ci_usuario';
  public $incrementing=false; protected $keyType='string';
  protected $hidden=['password'];
}