<?php

namespace App\Modules\Catalogo\Models;

use Illuminate\Database\Eloquent\Model;

class ListaPrecios extends Model
{
    protected $table = 'listas_precios';
    protected $fillable = ['codigo', 'nombre', 'canal', 'activa', 'predeterminada'];
    protected $casts = ['activa' => 'boolean', 'predeterminada' => 'boolean'];
}
