<?php

namespace App\Modules\Dropi\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Kardex de inventario · APPEND-ONLY.
 *
 * Re-audit M3 PATRÓN ι (FUNC-C4, FUNC-C5, DATOS-C6) · el kardex es el asiento
 *   primario contable de inventario para DIAN. Antes: sin Auditable, sin
 *   SoftDeletes, sin `updated_at`, sin protecciones contra edición → cualquier
 *   escritura silenciosa dejaba el saldo mintiendo. Ahora:
 *
 *   - `use SoftDeletes` + `booted::deleting` para forzar soft (nunca físico
 *     por vía Eloquent salvo `forceDelete()`).
 *   - `use Auditable` + `AuditableContract` (OwenIt) para trazabilidad DIAN.
 *   - `booted::updating` bloquea CUALQUIER cambio a un movimiento existente:
 *     el kardex es append-only. Reversas se registran con NUEVO movimiento
 *     de signo contrario (ver traslado_reversa_* / egreso_reverso).
 *   - `$guarded=['id']` para consistencia con patrón η.
 */
class InventarioMovimiento extends Model implements AuditableContract
{
    use SoftDeletes, Auditable;

    protected $table = 'inventario_movimientos';

    // updated_at habilitado (antes false) para audit trail temporal.
    public $timestamps = true;

    protected $guarded = ['id'];

    protected $casts = [
        'cantidad' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // APPEND-ONLY: nunca se modifica un movimiento existente.
        // Reversas se hacen con nuevo movimiento de signo contrario.
        static::updating(function (self $mov) {
            throw new \DomainException(
                "Kardex append-only: no se puede modificar movimiento #{$mov->id}. ".
                "Registra un movimiento reverso en su lugar."
            );
        });

        // No forceDelete tampoco desde código regular; usar softDelete.
        // (Filament EscapeHatch de Aracely/Gerencia sí puede forceDelete si
        // políticas del panel lo permiten explícitamente.)
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(InventarioUbicacion::class, 'ubicacion_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
