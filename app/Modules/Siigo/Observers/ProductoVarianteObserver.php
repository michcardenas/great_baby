<?php

namespace App\Modules\Siigo\Observers;

use App\Modules\Dropi\Models\ProductoVariante;
use App\Modules\Siigo\Jobs\PushProductoASiigo;
use Illuminate\Support\Facades\DB;

/**
 * Observer de ProductoVariante · dispara push a SIIGO cuando una variante
 * cambia, sin requerir cambio en el padre.
 *
 * Antes de este Observer, agregar/editar/borrar variantes desde el form
 * Vue (sync inline en ProductosController) NO disparaba ningún job porque:
 *   - `$producto->variantes()->create(...)` toca la tabla hija · el Observer
 *     de Producto no ve cambio en el padre.
 *   - Resultado: 50 variantes nuevas creadas, 0 jobs encolados, divergencia
 *     silenciosa ERP ↔ SIIGO.
 *
 * Diseño:
 *   - Siempre despachamos push del PADRE con acción `actualizar`, porque
 *     en modo granular `ActualizarProductoEnSiigo` recorre todas las
 *     variantes y crea las que no tengan `siigo_id`, actualiza las que
 *     cambiaron y desactiva las borradas.
 *   - Debounce: si Aracely edita 10 variantes en 3 segundos (ej. al guardar
 *     el form), despachamos UN solo job con la foto final, no 10.
 *   - afterCommit: si la transacción del controller hace rollback, no
 *     dejamos jobs huérfanos pusheando estado fantasma.
 */
class ProductoVarianteObserver
{
    public function created(ProductoVariante $v): void
    {
        $this->encolarPushPadre($v);
    }

    public function updated(ProductoVariante $v): void
    {
        // Solo si cambió un campo que SIIGO ve (code, name, barcode, stock_min).
        // Cambios internos (updated_at, campos de UI local) no gastan rate limit.
        $relevantes = ['codigo_barras', 'color_id', 'color_nombre', 'color_codigo',
                       'diseno_id', 'diseno_nombre', 'diseno_codigo',
                       'talla_id', 'talla', 'stock_minimo'];
        if (! $v->wasChanged($relevantes)) {
            return;
        }
        $this->encolarPushPadre($v);
    }

    public function deleted(ProductoVariante $v): void
    {
        // Borrar una variante = desactivar su producto SIIGO independiente.
        // `actualizar` del padre en modo granular detecta variantes faltantes
        // y las desactiva en SIIGO.
        $this->encolarPushPadre($v);
    }

    private function encolarPushPadre(ProductoVariante $v): void
    {
        if (! $v->producto_id) return;
        $productoId = $v->producto_id;
        DB::afterCommit(function () use ($productoId) {
            PushProductoASiigo::dispatchDebounced($productoId, 'actualizar');
        });
    }
}
