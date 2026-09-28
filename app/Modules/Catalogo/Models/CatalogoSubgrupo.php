<?php

namespace App\Modules\Catalogo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoSubgrupo extends Model
{
    protected $table = 'catalogo_subgrupos';
    protected $fillable = ['grupo_id', 'codigo', 'nombre', 'activa'];
    protected $casts = ['activa' => 'boolean'];

    public function grupo(): BelongsTo { return $this->belongsTo(CatalogoGrupo::class, 'grupo_id'); }
    public function clases(): HasMany { return $this->hasMany(CatalogoClase::class, 'subgrupo_id'); }
}
