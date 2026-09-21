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

    // C-F-QA1 · Item polimórfico: variante granular O producto agregado.
    public function producto(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Dropi\Models\Producto::class, 'producto_id');
    }

    public function esAgregado(): bool
    {
        return $this->variante_id === null && $this->producto_id !== null;
    }

    public function ubicacionAsignada(): BelongsTo
    {
        return $this->belongsTo(InventarioUbicacion::class, 'ubicacion_asignada_id');
    }

    public function subtotal(): float
    {
        return (float) $this->precio_proveedor_unit * $this->cantidad;
    }

    /**
     * Fix QA E2E re-audit · auto-poblar producto_id desde variante_id.
     *   SincronizarPedidosDropi hace updateOrCreate seteando sólo variante_id;
     *   sin este hook cada nuevo item importado desde Dropi entraba con
     *   producto_id NULL → ProcesarEscaneoEmpaque no matcheaba por producto,
     *   y los reportes de ventas por producto perdían filas.
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
