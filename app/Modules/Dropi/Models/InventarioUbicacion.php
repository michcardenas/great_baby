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
    ];

    protected $casts = [
        'categoria' => CategoriaUbicacion::class,
        'disponible_para_venta' => 'boolean',
        'activa' => 'boolean',
    ];

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
