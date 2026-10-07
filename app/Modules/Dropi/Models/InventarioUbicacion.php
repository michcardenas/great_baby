<?php

namespace App\Modules\Dropi\Models;

use App\Modules\Dropi\Enums\CategoriaUbicacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventarioUbicacion extends Model
{
    protected $table = 'inventario_ubicaciones';

    protected $fillable = [
        'codigo', 'nombre', 'categoria',
        'disponible_para_venta', 'activa', 'notas',
        // UBIC-3 · datos de operación / SIIGO por ubicación.
        'direccion', 'ciudad', 'responsable_user_id',
        'siigo_id', 'siigo_resolution_id', 'siigo_resolution_name', 'siigo_resolution_prefix', 'siigo_last_error',
        'cta_inventario', 'cta_costo',
    ];

    protected $casts = [
        'categoria' => CategoriaUbicacion::class,
        'disponible_para_venta' => 'boolean',
        'activa' => 'boolean',
        'siigo_resolution_id' => 'integer',
        'responsable_user_id' => 'integer',
    ];

    /**
     * UBIC-3 · Fallbacks a la configuración global cuando la ubicación no tiene
     * cuenta propia. Centralizar aquí evita tener que `?? setting()` en cada
     * call-site del SiigoEmisionService / RegistrarAsientoCompra.
     */
    public function ctaInventarioEfectiva(): string
    {
        return $this->cta_inventario ?: (string) setting('contable.cta_inventario_default', '1435');
    }

    public function ctaCostoEfectiva(): string
    {
        return $this->cta_costo ?: (string) setting('contable.cta_costo_default', '6135');
    }

    protected static function booted(): void
    {
        // Mantiene consistencia: la disponibilidad para venta la dicta la categoría.
        static::saving(function (InventarioUbicacion $u) {
            if ($u->categoria instanceof CategoriaUbicacion) {
                $u->disponible_para_venta = $u->categoria->disponibleParaVenta();
            }
        });
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(InventarioMovimiento::class, 'ubicacion_id');
    }
}
