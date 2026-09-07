<?php

namespace App\Modules\Catalogo\Models;

use App\Models\Contacto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Marca extends Model
{
    protected $table = 'marcas';
    protected $fillable = ['codigo', 'nombre', 'proveedor_id', 'activa'];
    protected $casts = ['activa' => 'boolean'];

    public function proveedor(): BelongsTo { return $this->belongsTo(Contacto::class, 'proveedor_id'); }
}
