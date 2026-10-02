<?php

namespace App\Modules\Siigo\Observers;

use App\Modules\Catalogo\Models\PrecioVariante;
use App\Modules\Dropi\Models\ProductoVariante;
use App\Modules\Siigo\Jobs\PushProductoASiigo;
use Illuminate\Support\Facades\DB;

/**
 * Observer de PrecioVariante · dispara push a SIIGO cuando una fila de
 * `precios_variante` cambia · insert, update del valor, o delete.
 *
 * Antes de este Observer, editar un precio por lista (desde el form Vue,
 * desde seed, desde Excel Import) actualizaba el ERP pero el payload que
 * se le enviaba a SIIGO seguía con los precios viejos · el badge marcaba
 * "sincronizado" pero SIIGO tenía valores obsoletos.
 *
 * Diseño:
 *   - Dispatch del PADRE con acción `actualizar` · igual que
 *     ProductoVarianteObserver · la Action ya recorre los precios.
 *   - Debounce + afterCommit · si Aracely edita 7 listas en 2 segundos,
 *     va UN solo job con el array completo.
 */
class PrecioVarianteObserver
{
    public function created(PrecioVariante $p): void
    {
        $this->encolarPushPadre($p);
    }

    public function updated(PrecioVariante $p): void
    {
        // Solo si cambió el valor o la vigencia · un re-save sin cambios
        // no gasta rate limit SIIGO.
        if (! $p->wasChanged(['precio', 'vigente_desde', 'vigente_hasta'])) {
            return;
        }
        $this->encolarPushPadre($p);
    }

    public function deleted(PrecioVariante $p): void
    {
        $this->encolarPushPadre($p);
    }

    private function encolarPushPadre(PrecioVariante $p): void
    {
        if (! $p->variante_id) return;
        // Resolvemos el producto_id SIN cargar relaciones · un sub-query
        // directo evita N+1 cuando se mueven lotes de precios desde el
        // importador Excel.
        $productoId = DB::table('producto_variantes')->where('id', $p->variante_id)->value('producto_id');
        if (! $productoId) return;
        DB::afterCommit(function () use ($productoId) {
            PushProductoASiigo::dispatchDebounced($productoId, 'actualizar');
        });
    }
}
