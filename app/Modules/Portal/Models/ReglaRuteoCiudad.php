<?php

namespace App\Modules\Portal\Models;

use App\Modules\Dropi\Models\InventarioUbicacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LOG-J4 · Regla de ruteo pedido → bodega por ciudad del cliente.
 *
 * Reglas se ordenan por prioridad ascendente al evaluar un pedido:
 * la primera activa cuya ciudad matchee (case-insensitive) decide la
 * ubicación origen. Si ninguna matchea, cae a la regla con `ciudad='*'`
 * (catch-all) si existe; si tampoco, el pedido queda sin asignación y
 * logística decide a mano.
 */
class ReglaRuteoCiudad extends Model
{
    protected $table = 'reglas_ruteo_ciudad';

    protected $fillable = [
        'ciudad', 'ubicacion_id', 'prioridad', 'activa', 'notas',
    ];

    protected $casts = [
        'activa' => 'boolean',
        'prioridad' => 'integer',
    ];

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(InventarioUbicacion::class, 'ubicacion_id');
    }

    /**
     * Resuelve la ubicación origen para una ciudad de cliente.
     * Devuelve el id o null si no hay match (ni catch-all).
     */
    public static function resolver(?string $ciudad): ?int
    {
        $ciudadLow = strtolower(trim((string) $ciudad));

        // Catch-all siempre al final, ordenamos por prioridad ascendente.
        $reglas = static::query()->where('activa', true)
            ->orderBy('prioridad')->orderBy('id')
            ->get(['id', 'ciudad', 'ubicacion_id']);

        foreach ($reglas as $r) {
            if ($r->ciudad === '*') continue;
            if (strtolower(trim($r->ciudad)) === $ciudadLow) {
                return (int) $r->ubicacion_id;
            }
        }
        // Catch-all como fallback.
        $catchAll = $reglas->firstWhere('ciudad', '*');
        return $catchAll ? (int) $catchAll->ubicacion_id : null;
    }
}
