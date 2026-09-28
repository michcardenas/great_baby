<?php

namespace App\Modules\Catalogo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sprint 4 · G.1 · Jerarquía SIIGO Kardex Referencias · Línea (nivel 1).
 * https://ilimitada.portaldeclientes.siigo.com/archivos-kardex-referencias/
 */
class CatalogoLinea extends Model
{
    protected $table = 'catalogo_lineas';
    protected $fillable = ['codigo', 'nombre', 'activa', 'siigo_id'];
    protected $casts = ['activa' => 'boolean'];

    public function grupos(): HasMany { return $this->hasMany(CatalogoGrupo::class, 'linea_id'); }
}
