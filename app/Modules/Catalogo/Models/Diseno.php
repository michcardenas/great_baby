<?php

namespace App\Modules\Catalogo\Models;

use Illuminate\Database\Eloquent\Model;

class Diseno extends Model
{
    protected $table = 'disenos';
    protected $fillable = ['codigo', 'nombre', 'descripcion', 'activo'];
    protected $casts = ['activo' => 'boolean'];
}
