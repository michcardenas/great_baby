<?php

namespace App\Modules\Dropi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Re-audit DR-β + η + ο (DATOS-C1, C2, C3, SEG-M1) ·
 *   - $guarded reemplaza $fillable: `cantidad` y `precio_proveedor_unit`
 *     ya no pueden mass-asignarse desde payload de Filament/form.
 *   - SoftDeletes por retención DIAN 5 años.
 *   - Auditable: cambios a cantidad/precio DESPUÉS de cierre de corte
 *     ya no quedan sin traza (el corte tiene manifiesto_hash pero antes
 *     el line-item se editaba sin registro).
 */
class DropiPedidoItem extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected $table = 'dropi_pedido_items';

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

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
