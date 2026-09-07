<?php

namespace App\Modules\Compras\Models;

use App\Modules\Dropi\Models\Producto;
use App\Modules\Dropi\Models\ProductoVariante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdenCompraItem extends Model
{
    protected $table = 'compra_orden_items';

    protected $fillable = [
        'orden_id', 'producto_id', 'variante_id',
        'descripcion', 'cantidad', 'cantidad_recibida',
        'precio_unit', 'descuento_pct', 'iva_pct',
        'subtotal', 'iva', 'total', 'costo_prorrateado_unit',
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
        'cantidad_recibida' => 'decimal:3',
        'precio_unit' => 'decimal:4',
        'descuento_pct' => 'decimal:2',
        'iva_pct' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'iva' => 'decimal:2',
        'total' => 'decimal:2',
        'costo_prorrateado_unit' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::saving(function (OrdenCompraItem $item) {
            $bruto = (float) $item->cantidad * (float) $item->precio_unit;
            $descuento = $bruto * ((float) $item->descuento_pct / 100);
            $sub = round($bruto - $descuento, 2);
            $iva = round($sub * ((float) $item->iva_pct / 100), 2);

            $item->subtotal = $sub;
            $item->iva = $iva;
            $item->total = round($sub + $iva, 2);
        });
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class, 'orden_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(ProductoVariante::class);
    }

    public function pendiente(): float
    {
        return max(0, (float) $this->cantidad - (float) $this->cantidad_recibida);
    }
}
