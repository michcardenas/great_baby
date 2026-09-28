<?php

namespace App\Modules\Catalogo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogoClase extends Model
{
    protected $table = 'catalogo_clases';
    protected $fillable = ['subgrupo_id', 'codigo', 'nombre', 'activa'];
    protected $casts = ['activa' => 'boolean'];

    public function subgrupo(): BelongsTo { return $this->belongsTo(CatalogoSubgrupo::class, 'subgrupo_id'); }
}
