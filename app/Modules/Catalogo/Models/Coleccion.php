<?php

namespace App\Modules\Catalogo\Models;

use Illuminate\Database\Eloquent\Model;

class Coleccion extends Model
{
    protected $table = 'colecciones';
    protected $fillable = ['nombre', 'fecha_lanzamiento', 'fecha_cierre', 'activa'];
    protected $casts = [
        'fecha_lanzamiento' => 'date', 'fecha_cierre' => 'date', 'activa' => 'boolean',
    ];
}
