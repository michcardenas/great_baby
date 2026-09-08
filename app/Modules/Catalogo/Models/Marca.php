<?php

namespace App\Modules\Catalogo\Models;

use App\Models\Contacto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Marca extends Model
{
    protected $table = 'marcas';
    protected $fillable = ['codigo', 'nombre', 'proveedor_id', 'activa'];
    protected $casts = ['activa' => 'boolean'];

    // QA-D Bloque3: invalidar cache del catálogo portal al crear/editar/eliminar marca.
    // Antes tenía TTL 300s sin invalidación → clientes veían marcas con hasta 5 min de lag.
    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('catalogo.marcas.activas'));
        static::deleted(fn () => Cache::forget('catalogo.marcas.activas'));
    }

    public function proveedor(): BelongsTo { return $this->belongsTo(Contacto::class, 'proveedor_id'); }
}
