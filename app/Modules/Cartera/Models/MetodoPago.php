<?php

namespace App\Modules\Cartera\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Método de pago del maestro (efectivo, transferencia, consignación, cruce…).
 * Se referencia por `codigo` desde pagos_venta.medio_pago (string), así que
 * es retrocompatible con los valores viejos ('transferencia', 'efectivo'…).
 */
class MetodoPago extends Model
{
    protected $table = 'metodos_pago';

    protected $fillable = [
        'codigo', 'nombre', 'tipo', 'requiere_referencia', 'requiere_banco',
        'requiere_comprobante', 'cuenta_puc', 'activo', 'orden',
    ];

    protected $casts = [
        'requiere_referencia' => 'boolean',
        'requiere_banco' => 'boolean',
        'requiere_comprobante' => 'boolean',
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    public const TIPOS = [
        'efectivo' => 'Efectivo',
        'transferencia' => 'Transferencia',
        'consignacion' => 'Consignación',
        'cruce' => 'Cruce de cuentas',
        'nec' => 'NEC',
        'tarjeta' => 'Tarjeta',
        'billetera' => 'Billetera digital',
        'otro' => 'Otro',
    ];

    /** @return array<string,string> [codigo => nombre] de los métodos activos, para Selects. */
    public static function opciones(): array
    {
        return static::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->pluck('nombre', 'codigo')
            ->all();
    }

    public static function porCodigo(?string $codigo): ?self
    {
        if (! $codigo) {
            return null;
        }
        return static::query()->where('codigo', $codigo)->first();
    }
}
