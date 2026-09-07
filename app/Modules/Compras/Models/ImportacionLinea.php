<?php

namespace App\Modules\Compras\Models;

use App\Modules\Dropi\Models\Producto;
use App\Modules\Dropi\Models\ProductoVariante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportacionLinea extends Model
{
    protected $table = 'compras_importacion_lineas';

    protected $fillable = [
        'importacion_id', 'orden_item_id', 'producto_id', 'variante_id',
        'cantidad', 'costo_fob_unit', 'costo_fob_total',
        'gasto_prorrateado', 'costo_final_unit', 'costo_final_total',
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
        'costo_fob_unit' => 'decimal:4',
        'costo_fob_total' => 'decimal:2',
        'gasto_prorrateado' => 'decimal:2',
        'costo_final_unit' => 'decimal:4',
        'costo_final_total' => 'decimal:2',
    ];

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(Importacion::class, 'importacion_id');
    }

    public function ordenItem(): BelongsTo
    {
        return $this->belongsTo(OrdenCompraItem::class, 'orden_item_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(ProductoVariante::class);
    }
}
