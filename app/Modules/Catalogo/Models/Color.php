<?php

namespace App\Modules\Catalogo\Models;

use Illuminate\Database\Eloquent\Model;

class Color extends Model
{
    protected $table = 'colores';
    protected $fillable = ['codigo', 'nombre', 'hex', 'activo'];
    protected $casts = ['activo' => 'boolean'];
}
