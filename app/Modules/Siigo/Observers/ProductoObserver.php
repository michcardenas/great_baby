<?php

namespace App\Modules\Siigo\Observers;

use App\Modules\Dropi\Models\Producto;
use App\Modules\Siigo\Jobs\PushProductoASiigo;
use Illuminate\Support\Facades\DB;

/**
 * Observer que dispara el push automático a Siigo cuando se crea, actualiza
 * o desactiva un Producto en el ERP.
 *
 * IMPORTANTE · Este archivo lo mantiene MyTech (módulo Siigo) pero
 * el REGISTRO del observer va en `app/Modules/Dropi/Models/Producto.php`
 * (booted() method) — es responsabilidad del compañero de Dropi
 * (tarea D2 del plan de sync). El Observer aquí simplemente existe;
 * mientras no esté registrado en el Model, no dispara nada.
 *
 * Flujo:
 *   1. Producto creado/actualizado/eliminado → hook del Observer
 *   2. `PushProductoASiigo::dispatchDebounced()` verifica:
 *        - `siigo.push_auto` (kill-switch global) → si false, no encola
 *        - debounce por (producto_id, accion) → si ya se encoló recientemente,
 *          skip (evita spam por saves repetidos)
 *   3. Si pasa los guards → despacha el Job a la cola `siigo`
 *   4. El Job ejecuta la Action correspondiente (con rate limit, lock, retry)
 *
 * `updated()` solo dispara si cambió un campo RELEVANTE para Siigo. Cambios
 * cosméticos internos (last_login, contadores, campos de UI) no gastan API.
 */
class ProductoObserver
{
    /**
     * Campos cuyo cambio requiere re-push a Siigo. Cualquier otro cambio
     * no dispara sync (evita gastar rate limit por columnas irrelevantes
     * como `updated_at` de contadores internos).
     */
    private const CAMPOS_RELEVANTES = [
        'nombre',
        'descripcion',
        'precio_proveedor',
        'activo',
        'categoria_id',
        'marca_id',
        'unidad_medida_id',
        'impuesto_id',
        'referencia',        // cambiar la referencia = cambio del `code` en Siigo
        'desglose_stock',    // toggle granular ↔ agregado cambia la topología
        'stock_directo',     // afecta stock inicial en Siigo (cuando es agregado)
        // Sprint 4 · G.1-G.4 · campos jerarquía y ficha técnica SIIGO.
        'linea_id',
        'grupo_id',
        'subgrupo_id',
        'clase_id',
        'proteger_precio',
        'posicion_arancelaria',
        'descripcion_ampliada',
        'ficha_tecnica',
        'descuento_default_pct',
        'valor_gasto_venta_niif',
        'valor_neto_realizable_niif',
        'maneja_lotes',
        'maneja_seriales',
        'es_estadistico',
        'rentabilidad_pct',
        'factor_conversion',
        'unidad_compra_id',
        'reposicion_max_dias',
    ];

    /**
     * C1 · Todos los dispatches se envuelven en `DB::afterCommit`. Si el save()
     * del producto ocurre dentro de una transacción y hace rollback, el job
     * NO se encola. Fuera de transacción, `afterCommit` ejecuta inmediato.
     * Sin esto, un rollback deja jobs huérfanos que pusheaban estado fantasma.
     */
    public function created(Producto $producto): void
    {
        DB::afterCommit(function () use ($producto) {
            PushProductoASiigo::dispatchDebounced($producto->id, 'crear');
        });
    }

    public function updated(Producto $producto): void
    {
        // Caso especial · si se desactivó, mandar 'desactivar' (más claro en logs).
        if ($producto->wasChanged('activo') && $producto->activo === false) {
            DB::afterCommit(function () use ($producto) {
                PushProductoASiigo::dispatchDebounced($producto->id, 'desactivar');
            });
            return;
        }

        // Solo dispara si cambió algún campo relevante para Siigo.
        if (! $producto->wasChanged(self::CAMPOS_RELEVANTES)) {
            return;
        }

        // Sin siigo_id → primer push → CREAR.
        $accion = $producto->siigo_id ? 'actualizar' : 'crear';
        DB::afterCommit(function () use ($producto, $accion) {
            PushProductoASiigo::dispatchDebounced($producto->id, $accion);
        });
    }

    public function deleted(Producto $producto): void
    {
        // Soft-delete en el ERP → soft-delete en Siigo (active:false).
        // C3 · pasamos siigoId al Job para que si el modelo se hard-deletea
        // antes del handle, igual pueda desactivar en SIIGO por siigo_id.
        if ($producto->siigo_id) {
            $siigoId = $producto->siigo_id;  // snapshot antes del rollback potencial
            $productoId = $producto->id;
            DB::afterCommit(function () use ($productoId, $siigoId) {
                // Usa dispatchManual (no manual real, sino para pasar siigoId).
                // Como el flag `manual` va true, bypasea kill-switch — hay
                // debate operativo aquí; para desactivación es correcto: si
                // Aracely borra un producto, siempre lo bajamos de SIIGO.
                PushProductoASiigo::dispatchManual($productoId, 'desactivar', $siigoId);
            });
        }
    }

    /**
     * Si el producto se restaura del soft-delete → re-activarlo en Siigo.
     */
    public function restored(Producto $producto): void
    {
        DB::afterCommit(function () use ($producto) {
            PushProductoASiigo::dispatchDebounced($producto->id, 'actualizar');
        });
    }
}
