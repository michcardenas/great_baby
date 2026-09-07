<?php

namespace App\Modules\Catalogo\Models;

use Illuminate\Database\Eloquent\Model;

class Impuesto extends Model
{
    protected $table = 'impuestos';
    protected $fillable = ['codigo', 'nombre', 'tipo', 'porcentaje', 'cuenta_puc', 'activo'];
    protected $casts = ['activo' => 'boolean', 'porcentaje' => 'decimal:3'];
}
