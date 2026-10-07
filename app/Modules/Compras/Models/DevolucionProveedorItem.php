<?php

namespace App\Modules\Compras\Models;

use App\Modules\Dropi\Models\Producto;
use App\Modules\Dropi\Models\ProductoVariante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevolucionProveedorItem extends Model
{
    protected $table = 'devoluciones_proveedor_items';

    protected $fillable = [
        'devolucion_id', 'variante_id', 'producto_id',
        'cantidad', 'costo_unit', 'iva_pct', 'subtotal', 'motivo_item',
    ];

    protected $casts = [
        'cantidad' => 'decimal:4',
        'costo_unit' => 'decimal:4',
        'iva_pct' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function devolucion(): BelongsTo { return $this->belongsTo(DevolucionProveedor::class, 'devolucion_id'); }
    public function variante(): BelongsTo { return $this->belongsTo(ProductoVariante::class, 'variante_id'); }
    public function producto(): BelongsTo { return $this->belongsTo(Producto::class, 'producto_id'); }
}
