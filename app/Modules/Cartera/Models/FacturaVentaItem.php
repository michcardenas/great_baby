<?php

namespace App\Modules\Cartera\Models;

use App\Modules\Dropi\Models\ProductoVariante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaVentaItem extends Model
{
    protected $table = 'factura_venta_items';

    protected $fillable = [
        'factura_id', 'variante_id', 'producto_id', 'descripcion',
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

    // C-F-QA1 · Item polimórfico para facturación de agregados.
    public function producto(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Dropi\Models\Producto::class, 'producto_id');
    }

    public function esAgregado(): bool
    {
        return $this->variante_id === null && $this->producto_id !== null;
    }

    /**
     * Fix QA E2E re-audit · auto-poblar producto_id desde variante_id.
     *   Antes: callers que olvidaban setear producto_id creaban filas
     *   granulares con producto_id NULL → reportes SIIGO/DIAN por producto,
     *   notas crédito, y export a analítica quedaban sin trazabilidad.
     *   El backfill de la migración 100011 sólo cubrió filas existentes,
     *   no inserciones futuras. Este hook lo garantiza en tiempo real.
     */
    protected static function booted(): void
    {
        static::creating(function (self $item) {
            if ($item->producto_id === null && $item->variante_id !== null) {
                $item->producto_id = ProductoVariante::whereKey($item->variante_id)->value('producto_id');
            }
        });
    }
}
