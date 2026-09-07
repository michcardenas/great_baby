<?php

namespace App\Modules\Dropi\Models;

use App\Modules\Dropi\Enums\EstadoPedidoDropi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class DropiPedido extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected $table = 'dropi_pedidos';

    protected $fillable = [
        'corte_id',
        'guia', 'dropi_orden_id', 'transportadora', 'tienda', 'tienda_id',
        'vendedor_nombre', 'vendedor_identificacion', 'requiere_factura_b2b',
        'cliente_nombre', 'cliente_doc', 'cliente_telefono',
        'cliente_direccion', 'cliente_ciudad', 'cliente_depto',
        'estado', 'despachado_at', 'entregado_at', 'devuelto_at', 'pagado_at',
        'monto_esperado_proveedor', 'monto_cliente_final',
        'ganancia_vendedor', 'flete_transportadora',
        'remision_interna_id', 'ari_factura_id', 'ari_enviado_at', 'nota_credito_id',
    ];

    protected $casts = [
        'estado' => EstadoPedidoDropi::class,
        'despachado_at' => 'datetime',
        'entregado_at' => 'datetime',
        'devuelto_at' => 'datetime',
        'pagado_at' => 'datetime',
        'ari_enviado_at' => 'datetime',
        'requiere_factura_b2b' => 'boolean',
        'monto_esperado_proveedor' => 'decimal:2',
        'monto_cliente_final' => 'decimal:2',
        'ganancia_vendedor' => 'decimal:2',
        'flete_transportadora' => 'decimal:2',
    ];

    public function corte(): BelongsTo
    {
        return $this->belongsTo(DropiCorte::class, 'corte_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DropiPedidoItem::class, 'pedido_id');
    }

    public function bitacoraEstados(): HasMany
    {
        return $this->hasMany(DropiEstadoBitacora::class, 'pedido_id');
    }

    public function devolucion(): HasOne
    {
        return $this->hasOne(DropiDevolucion::class, 'pedido_id');
    }

    public function remision(): HasOne
    {
        return $this->hasOne(DropiRemision::class, 'pedido_id');
    }

    public function pagoWallet(): HasOne
    {
        return $this->hasOne(DropiWalletMovimiento::class, 'pedido_id')
            ->where('tipo', 'pago_guia');
    }

    public function sanciones(): HasMany
    {
        return $this->hasMany(DropiSancion::class, 'pedido_id');
    }
}
