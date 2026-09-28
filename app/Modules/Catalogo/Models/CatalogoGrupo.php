<?php

namespace App\Modules\Catalogo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoGrupo extends Model
{
    protected $table = 'catalogo_grupos';
    protected $fillable = ['linea_id', 'codigo', 'nombre', 'activa', 'siigo_id'];
    protected $casts = ['activa' => 'boolean'];

    public function linea(): BelongsTo { return $this->belongsTo(CatalogoLinea::class, 'linea_id'); }
    public function subgrupos(): HasMany { return $this->hasMany(CatalogoSubgrupo::class, 'grupo_id'); }
}
