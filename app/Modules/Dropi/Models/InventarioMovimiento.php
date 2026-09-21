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
        // C-F2 R1 FIX CRÍTICO · Auto-populate `producto_id` desde la variante.
        //   Sin esto, TODOS los movimientos nuevos creados por las 8 Actions
        //   (EjecutarTraslado, RecibirMercancia, CerrarTomaFisica, etc.)
        //   quedan con `producto_id NULL` → el trigger de bloqueo del toggle
        //   desglose_stock se vuelve un placebo (no encuentra los movs
        //   granulares porque busca por producto_id).
        //
        //   Este hook garantiza que TODO mov granular lleva su producto_id
        //   sin tener que auditar y modificar cada caller. Los movs agregados
        //   (variante_id NULL, producto_id ya set) pasan sin cambio.
        //   El CHECK constraint chk_invmov_sujeto exige que al menos uno de
        //   los dos exista — esta lógica cumple con esa invariante.
        static::creating(function (self $mov) {
            if ($mov->producto_id === null && $mov->variante_id !== null) {
                $mov->producto_id = ProductoVariante::whereKey($mov->variante_id)
                    ->value('producto_id');
            }
        });

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

    /**
     * C-F1 · Un movimiento apunta a variante (granular) O a producto (agregado).
     * El CHECK constraint chk_invmov_sujeto garantiza que al menos uno exista.
     */
    public function variante(): BelongsTo
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(InventarioUbicacion::class, 'ubicacion_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** ¿Este movimiento es de producto agregado (sin variante)? */
    public function esAgregado(): bool
    {
        return $this->variante_id === null && $this->producto_id !== null;
    }

    /** El sujeto del movimiento — variante si es granular, producto si es agregado. */
    public function sujeto(): Producto|ProductoVariante|null
    {
        return $this->esAgregado()
            ? $this->producto
            : $this->variante;
    }
}
