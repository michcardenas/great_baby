<?php

namespace App\Modules\Catalogo\Models;

use Illuminate\Database\Eloquent\Model;

class Talla extends Model
{
    protected $table = 'tallas';
    protected $fillable = ['codigo', 'nombre', 'orden', 'activa'];
    protected $casts = ['activa' => 'boolean', 'orden' => 'integer'];
}
