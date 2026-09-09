<?php

namespace App\Modules\Compras\Models;

use App\Models\User;
use App\Modules\Dropi\Models\InventarioUbicacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class RecepcionCompra extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected $table = 'compras_recepciones';

    // Re-audit M2 R3 PATRÓN Q (DATOS-A3) · $guarded + guard post-confirmada.
    //   Antes $rc->update(['estado'=>'borrador']) revertía sin reversar el
    //   InventarioMovimiento ni el MovimientoContable → stock/asiento huérfanos.
    protected $guarded = ['id'];

    protected $casts = [
        'fecha_recepcion' => 'date',
        'confirmada_at' => 'datetime',
        'total_recibido' => 'decimal:2',
    ];

    /**
     * Re-audit M2 R3 PATRÓN Q · una vez confirmada, sólo `observaciones` puede
     *   cambiar. Sin esto un update revertía estado sin reversar kardex/contab.
     */
    protected static function booted(): void
    {
        static::saving(function (RecepcionCompra $rc) {
            if (! $rc->exists) return;
            if ($rc->getOriginal('estado') !== 'confirmada') return;

            $mutables = ['observaciones', 'updated_at'];
            foreach ($rc->getDirty() as $campo => $_) {
                if (! in_array($campo, $mutables, true)) {
                    throw new \RuntimeException(sprintf(
                        'Recepción %s (confirmada): campo "%s" es inmutable. Crea una nueva recepción o anula.',
                        $rc->getOriginal('numero'), $campo,
                    ));
                }
            }
        });
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class, 'orden_id');
    }

    public function bodega(): BelongsTo
    {
        return $this->belongsTo(InventarioUbicacion::class, 'bodega_id');
    }

    public function receptor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recibido_por');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RecepcionCompraItem::class, 'recepcion_id');
    }

    /**
     * Re-audit M2 PATRÓN C + I · consecutivo atómico en TZ Colombia.
     */
    public static function siguienteNumero(): string
    {
        $year = now('America/Bogota')->year;
        return \Illuminate\Support\Facades\DB::transaction(function () use ($year) {
            $ultimo = static::query()
                ->where('numero', 'like', "REC-{$year}-%")
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('numero');
            $seq = $ultimo ? ((int) substr($ultimo, -6)) + 1 : 1;
            return sprintf('REC-%d-%06d', $year, $seq);
        });
    }
}
