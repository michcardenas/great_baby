<?php

namespace App\Console\Commands;

use App\Modules\Dropi\Models\Producto;
use App\Modules\Siigo\Jobs\ReconciliarProductosDesdeSiigo;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A1 · Limpia productos basura traídos de SIIGO sandbox compartido.
 *
 * El sandbox de SIIGO es multi-cliente · una reconciliación trae productos
 * ajenos (ej. 19k productos de otros tenants). Este comando borra
 * (soft-delete) los productos que CUMPLEN TODAS estas condiciones:
 *
 *   1. Tienen siigo_id (= vinieron de SIIGO, no fueron creados en el ERP)
 *   2. Su `siigo_sync_at` cae dentro de la ventana --desde/--hasta
 *   3. NO tienen movimientos de kardex locales
 *   4. NO están referenciados en pedidos/facturas
 *
 * Si no se pasan --desde/--hasta, usa la ventana de la última reconciliación
 * guardada en cache (siigo:reconciliar:estado).
 *
 * Uso:
 *   # Audit (default · no borra)
 *   php artisan siigo:limpiar-sandbox
 *
 *   # Con ventana explícita
 *   php artisan siigo:limpiar-sandbox --desde="2026-10-01 12:13" --hasta="2026-10-01 12:28"
 *
 *   # Ejecutar de verdad
 *   php artisan siigo:limpiar-sandbox --confirmar
 */
class LimpiarSandboxSiigoCommand extends Command
{
    protected $signature = 'siigo:limpiar-sandbox
                            {--desde= : Fecha/hora inicio (YYYY-MM-DD HH:MM). Si falta, usa la última reconciliación}
                            {--hasta= : Fecha/hora fin. Si falta, usa la última reconciliación}
                            {--confirmar : Ejecuta el borrado. Sin este flag solo muestra el audit}';

    protected $description = 'Borra (soft-delete) productos SIIGO basura dentro de una ventana · dry-run por default';

    public function handle(): int
    {
        [$desde, $hasta, $origen] = $this->resolverVentana();
        if (! $desde || ! $hasta) {
            $this->error('No pude determinar la ventana. Pasa --desde y --hasta, o corre primero una reconciliación.');
            return self::FAILURE;
        }

        $this->info("Ventana: {$desde->format('Y-m-d H:i:s')}  →  {$hasta->format('Y-m-d H:i:s')}   ({$origen})");

        $base = Producto::whereNotNull('siigo_id')
            ->whereBetween('siigo_sync_at', [$desde, $hasta]);

        $total = (clone $base)->count();
        if ($total === 0) {
            $this->warn('No hay productos SIIGO en esa ventana. Nada que borrar.');
            return self::SUCCESS;
        }

        // Preservar: productos con movimientos locales, ventas, OC, variantes,
        // o editados por el usuario (updated_at > siigo_sync_at + 1 min).
        $elegibles = (clone $base)
            ->whereDoesntHave('variantes')
            ->where(function ($q) {
                $q->whereNull('updated_at')
                  ->orWhereRaw('TIMESTAMPDIFF(SECOND, siigo_sync_at, updated_at) < 60');
            });

        $elegibles = $this->filtrarSinMovimientos($elegibles);

        $borrables = (clone $elegibles)->count();
        $protegidos = $total - $borrables;

        $this->newLine();
        $this->table(['Categoría', 'Productos'], [
            ['Total SIIGO en ventana', $total],
            ['Protegidos (con mov/variantes/editados)', $protegidos],
            ['Elegibles para borrar', $borrables],
        ]);

        if ($borrables === 0) {
            $this->warn('Todos los productos de la ventana están protegidos. Nada que borrar.');
            return self::SUCCESS;
        }

        if (! $this->option('confirmar')) {
            $this->newLine();
            $this->warn('MODO AUDIT · no se borró nada.');
            $this->line('Para ejecutar realmente, repite el comando con --confirmar');
            $muestra = (clone $elegibles)->orderBy('id')->limit(5)->get(['id', 'referencia', 'nombre', 'siigo_id']);
            if ($muestra->isNotEmpty()) {
                $this->newLine();
                $this->line('Ejemplos (5 primeros):');
                $this->table(['ID', 'Referencia', 'Nombre', 'siigo_id'],
                    $muestra->map(fn ($p) => [$p->id, $p->referencia, $p->nombre, $p->siigo_id])->toArray());
            }
            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn("A punto de SOFT-DELETE a {$borrables} productos. Procediendo...");

        $t0 = microtime(true);
        $borrados = 0;

        (clone $elegibles)->select('id')->chunkById(500, function ($rows) use (&$borrados) {
            $ids = $rows->pluck('id')->all();
            $borrados += DB::table('productos')
                ->whereIn('id', $ids)
                ->update(['deleted_at' => now()]);
        });

        $ms = (int) round((microtime(true) - $t0) * 1000);
        $this->newLine();
        $this->info("✓ Borrados (soft) {$borrados} productos en {$ms}ms.");
        $this->line('Puedes revertir con: Producto::onlyTrashed()->whereBetween(\'deleted_at\', [...])->restore()');

        return self::SUCCESS;
    }

    /** @return array{0:?Carbon, 1:?Carbon, 2:string} */
    private function resolverVentana(): array
    {
        $desde = $this->option('desde');
        $hasta = $this->option('hasta');

        if ($desde && $hasta) {
            return [Carbon::parse($desde), Carbon::parse($hasta), 'flags'];
        }

        $estado = Cache::get(ReconciliarProductosDesdeSiigo::CACHE_KEY);
        if ($estado && isset($estado['inicio'], $estado['fin'])) {
            return [Carbon::parse($estado['inicio']), Carbon::parse($estado['fin']), 'última reconciliación en cache'];
        }

        return [null, null, 'no determinable'];
    }

    /**
     * Excluye productos referenciados en movimientos (kardex, pedidos, facturas, OC).
     * Solo chequeamos las tablas que existen · si una tabla no existe en este deploy
     * el método la salta en silencio.
     */
    private function filtrarSinMovimientos($query)
    {
        $tablas = [
            // tabla => columna FK a productos.id
            'kardex_movimientos' => 'producto_id',
            'oc_items' => 'producto_id',
            'factura_venta_items' => 'producto_id',
            'nota_credito_items' => 'producto_id',
            'dropi_pedido_items' => 'producto_id',
        ];

        foreach ($tablas as $t => $fk) {
            if (! Schema::hasTable($t) || ! Schema::hasColumn($t, $fk)) continue;
            $query->whereNotIn('id', fn ($q) => $q->from($t)->select($fk)->whereNotNull($fk));
        }

        return $query;
    }
}
