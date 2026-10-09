<?php

namespace App\Modules\Catalogo\Models;

use App\Modules\Dropi\Models\Producto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Precio de venta de un producto AGREGADO en una lista.
 *
 * El hermano de `PrecioVariante` para los productos que no se desglosan por
 * variante. Sin esto no se podían vender: el pedido descartaba la línea en
 * silencio porque no había de dónde sacarles un precio.
 */
class PrecioProducto extends Model
{
    protected $table = 'precios_producto';

    protected $fillable = ['producto_id', 'lista_id', 'precio', 'vigente_desde', 'vigente_hasta'];

    protected $casts = [
        'precio' => 'decimal:2',
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
    ];

    public function producto(): BelongsTo { return $this->belongsTo(Producto::class, 'producto_id'); }

    public function lista(): BelongsTo { return $this->belongsTo(ListaPrecios::class, 'lista_id'); }

    /** Sólo los precios que rigen hoy. */
    public function scopeVigentes($q)
    {
        return $q->where(fn ($w) => $w->whereNull('vigente_hasta')
            ->orWhere('vigente_hasta', '>=', now()->toDateString()));
    }
}
