<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'nombre',
        'apellido',
        'celular',
        'departamento',
        'ciudad',
        'direccion',
        'correo',
        'cantidad',
        'estado'
    ];
}