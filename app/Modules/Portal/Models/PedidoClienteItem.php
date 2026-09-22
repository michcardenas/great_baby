<?php

namespace App\Modules\Portal\Models;

use App\Modules\Dropi\Models\Producto;
use App\Modules\Dropi\Models\ProductoVariante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoClienteItem extends Model
{
    protected $table = 'pedidos_cliente_items';

    protected $fillable = [
        'pedido_id', 'variante_id', 'producto_id',
        'sku_snapshot', 'descripcion_snapshot',
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
    // C-F6 · item apunta a variante (granular) O a producto (agregado).
    public function variante(): BelongsTo { return $this->belongsTo(ProductoVariante::class, 'variante_id'); }
    public function producto(): BelongsTo { return $this->belongsTo(Producto::class, 'producto_id'); }
    public function esAgregado(): bool { return $this->variante_id === null && $this->producto_id !== null; }
}
