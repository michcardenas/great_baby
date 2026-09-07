<?php

namespace App\Modules\Cartera\Models;

use App\Modules\Dropi\Models\ProductoVariante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaVentaItem extends Model
{
    protected $table = 'factura_venta_items';

    protected $fillable = [
        'factura_id', 'variante_id', 'descripcion',
        'cantidad', 'precio_unit', 'descuento_pct', 'impuesto_pct', 'subtotal',
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
        'precio_unit' => 'decimal:2',
        'descuento_pct' => 'decimal:2',
        'impuesto_pct' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function factura(): BelongsTo
    {
        return $this->belongsTo(FacturaVenta::class, 'factura_id');
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }
}
