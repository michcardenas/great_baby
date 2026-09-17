<?php

namespace App\Modules\Contabilidad\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cuenta del PLAN DE CUENTAS (PUC).
 *
 * clase, nivel y (si no se indica) naturaleza se derivan del código al guardar.
 * El vínculo padre→hijo se resuelve por prefijo con {@see self::vincularPadres()}
 * (se llama tras crear/editar o importar, cuando ya existen todas las cuentas).
 */
class PlanCuenta extends Model
{
    protected $table = 'plan_cuentas';

    protected $fillable = [
        'codigo', 'nombre', 'clase', 'nivel', 'naturaleza',
        'padre_id', 'permite_movimiento', 'siigo_cuenta_id', 'activa',
    ];

    protected $casts = [
        'nivel' => 'integer',
        'permite_movimiento' => 'boolean',
        'activa' => 'boolean',
    ];

    /** Nombre de cada clase PUC (primer dígito). */
    public const CLASES = [
        '1' => 'Activo',
        '2' => 'Pasivo',
        '3' => 'Patrimonio',
        '4' => 'Ingresos',
        '5' => 'Gastos',
        '6' => 'Costos de ventas',
        '7' => 'Costos de producción',
        '8' => 'Cuentas de orden deudoras',
        '9' => 'Cuentas de orden acreedoras',
    ];

    protected static function booted(): void
    {
        static::saving(function (PlanCuenta $c): void {
            // Normalizar: solo dígitos.
            $c->codigo = preg_replace('/\D/', '', (string) $c->codigo);
            $c->clase = $c->codigo !== '' ? substr($c->codigo, 0, 1) : null;
            $c->nivel = static::nivelPorLongitud(strlen((string) $c->codigo));
            if (empty($c->naturaleza)) {
                $c->naturaleza = static::naturalezaPorClase($c->clase);
            }
        });
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'padre_id');
    }

    public function hijos(): HasMany
    {
        return $this->hasMany(self::class, 'padre_id');
    }

    /** Nivel PUC por longitud del código. */
    public static function nivelPorLongitud(int $len): int
    {
        return match (true) {
            $len <= 1 => 1, // Clase
            $len <= 2 => 2, // Grupo
            $len <= 4 => 3, // Cuenta
            $len <= 6 => 4, // Subcuenta
            default => 5,   // Auxiliar
        };
    }

    /** Naturaleza contable por clase: 1,5,6,7,8 = débito; 2,3,4,9 = crédito. */
    public static function naturalezaPorClase(?string $clase): string
    {
        return in_array($clase, ['1', '5', '6', '7', '8'], true) ? 'debito' : 'credito';
    }

    public static function nombreClase(?string $clase): string
    {
        return self::CLASES[$clase] ?? '—';
    }

    public function nombreDeClase(): string
    {
        return self::nombreClase($this->clase);
    }

    /**
     * Recalcula padre_id de TODAS las cuentas por prefijo del código:
     * el padre es la cuenta existente cuyo código es el prefijo más largo.
     * Ej: 413505 → 4135 → 41 → 4.
     */
    public static function vincularPadres(): void
    {
        $todas = static::query()->orderBy('codigo')->get(['id', 'codigo', 'padre_id']);
        $porCodigo = $todas->keyBy('codigo');

        foreach ($todas as $c) {
            $padreId = null;
            for ($len = strlen((string) $c->codigo) - 1; $len >= 1; $len--) {
                $prefijo = substr((string) $c->codigo, 0, $len);
                if ($porCodigo->has($prefijo)) {
                    $padreId = $porCodigo->get($prefijo)->id;
                    break;
                }
            }
            if ($c->padre_id !== $padreId) {
                static::whereKey($c->id)->update(['padre_id' => $padreId]);
            }
        }
    }
}
