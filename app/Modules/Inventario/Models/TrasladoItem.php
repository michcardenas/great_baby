<?php

namespace App\Modules\Inventario\Models;

use App\Modules\Dropi\Models\ProductoVariante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Re-audit M3 PATRÓN κ (DATOS-C4) · $guarded reemplaza $fillable.
 * Re-audit M3 PATRÓN ο (DATOS-C5) · Auditable para trazabilidad DIAN.
 * Re-audit M3 PATRÓN ξ · cantidades decimal(14,4).
 *
 * `cantidad_ejecutada` la escribe EjecutarTraslado::recibir()/anular(); no
 * debe editarse por form.
 */
class TrasladoItem extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'traslados_inventario_items';

    // `traslado_id` sí se acepta en create() desde Action/Controller (no viene
    //   de payload user). `cantidad_ejecutada` la escribe EjecutarTraslado
    //   mediante asignación directa, nunca por form.
    protected $guarded = ['id', 'cantidad_ejecutada', 'created_at', 'updated_at'];

    protected $casts = [
        'cantidad_solicitada' => 'decimal:4',
        'cantidad_ejecutada' => 'decimal:4',
    ];

    public function traslado(): BelongsTo
    {
        return $this->belongsTo(Traslado::class, 'traslado_id');
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }
}
