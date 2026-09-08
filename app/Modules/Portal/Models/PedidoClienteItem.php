<?php

namespace App\Modules\Portal\Models;

use App\Modules\Dropi\Models\ProductoVariante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoClienteItem extends Model
{
    protected $table = 'pedidos_cliente_items';

    protected $fillable = [
        'pedido_id', 'variante_id', 'sku_snapshot', 'descripcion_snapshot',
        'cantidad', 'precio_unitario', 'iva_porcentaje',
        'subtotal', 'iva_valor', 'total',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'precio_unitario' => 'decimal:2',
        'iva_porcentaje' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'iva_valor' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function pedido(): BelongsTo { return $this->belongsTo(PedidoCliente::class, 'pedido_id'); }
    public function variante(): BelongsTo { return $this->belongsTo(ProductoVariante::class, 'variante_id'); }
}
