<?php

namespace App\Modules\Dropi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DropiPedidoItem extends Model
{
    protected $table = 'dropi_pedido_items';

    protected $fillable = [
        'pedido_id', 'variante_id', 'sku_dropi',
        'cantidad', 'precio_proveedor_unit',
        'ubicacion_asignada_id', 'despachado',
        'pickeado_at', 'pickeado_por', 'cantidad_pickeada',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'cantidad_pickeada' => 'integer',
        'precio_proveedor_unit' => 'decimal:2',
        'despachado' => 'boolean',
        'pickeado_at' => 'datetime',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(DropiPedido::class, 'pedido_id');
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }

    public function ubicacionAsignada(): BelongsTo
    {
        return $this->belongsTo(InventarioUbicacion::class, 'ubicacion_asignada_id');
    }

    public function subtotal(): float
    {
        return (float) $this->precio_proveedor_unit * $this->cantidad;
    }
}
