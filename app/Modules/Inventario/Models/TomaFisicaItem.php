<?php

namespace App\Modules\Inventario\Models;

use App\Modules\Dropi\Models\ProductoVariante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Item de toma física.
 *
 * Re-audit M3 PATRÓN κ (DATOS-C4, SEG-C3) · $fillable con `saldo_sistema`,
 *   `cantidad_contada`, `costo_unit` permitía manipular el asiento contable
 *   editando el item vía Filament payload. Ahora `$guarded` protege columnas
 *   contables: sólo `cantidad_contada` y `notas` son user-input; el resto lo
 *   escribe PrepararTomaFisica y no debe cambiar.
 *
 * Re-audit M3 PATRÓN ο (FUNC-C5, DATOS-C5) · Auditable trait para DIAN.
 *
 * Re-audit M3 PATRÓN ξ · casts `decimal:4` para admitir fraccionarios.
 */
class TomaFisicaItem extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'tomas_fisicas_items';

    // Columnas EDITABLES por el operario: cantidad_contada y notas.
    // Todas las demás son escritas por PrepararTomaFisica en creación y no
    // deben moverse (protegen el asiento contable).
    // Columnas EDITABLES por usuario: cantidad_contada + notas.
    //   saldo_sistema, diferencia, costo_unit: SÓLO PrepararTomaFisica y el
    //   booted::saving los escriben, nunca vienen de payload. toma_id y
    //   variante_id sí se admiten por create() del Action.
    protected $guarded = ['id', 'saldo_sistema', 'diferencia', 'costo_unit', 'created_at', 'updated_at'];

    protected $casts = [
        'saldo_sistema' => 'decimal:4',
        'cantidad_contada' => 'decimal:4',
        'diferencia' => 'decimal:4',
        'costo_unit' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::saving(function (TomaFisicaItem $item) {
            if ($item->cantidad_contada !== null) {
                $item->diferencia = (float) $item->cantidad_contada - (float) $item->saldo_sistema;
            }
        });
    }

    public function toma(): BelongsTo
    {
        return $this->belongsTo(TomaFisica::class, 'toma_id');
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }
}
