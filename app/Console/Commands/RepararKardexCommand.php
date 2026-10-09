<?php

namespace App\Console\Commands;

use App\Modules\Dropi\Models\InventarioMovimiento;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deja el kardex en condiciones de contabilizarse.
 *
 * Arregla tres cosas que se encontraron el 2026-10-08 midiendo por qué ninguno
 * de los 79 asientos había llegado a SIIGO:
 *
 *  1. 144 movimientos apuntaban a productos y variantes BORRADOS: 8.958
 *     unidades de stock fantasma. Vienen de la carga del Excel del cliente,
 *     que creó 134 productos que después alguien eliminó dejando el kardex
 *     colgando. No se pueden costear ni asentar y nadie sabe a qué
 *     correspondían, así que se borran.
 *  2. Movimientos con `producto_id` colgando pero con una variante que sí
 *     existe: ahí el sujeto real es la variante, se limpia el `producto_id`.
 *  3. Movimientos sin `costo_unit`. Sin costo no hay asiento de inventario y
 *     el push a SIIGO muere con «Movimiento kardex N sin costo_unit». Se toma
 *     el `precio_proveedor` del producto, que es el costo con el que se compró.
 *
 * Siempre conviene correrlo primero con --simular.
 *
 *   php artisan inventario:reparar-kardex --simular
 *   php artisan inventario:reparar-kardex
 */
class RepararKardexCommand extends Command
{
    protected $signature = 'inventario:reparar-kardex
                            {--simular : Muestra lo que haría sin tocar la base}';

    protected $description = 'Borra kardex huérfano, desengancha referencias rotas y completa costos faltantes';

    public function handle(): int
    {
        $simular = (bool) $this->option('simular');
        if ($simular) {
            $this->warn('MODO SIMULACIÓN · no se escribe nada.');
        }

        $this->line('');
        $borrados = $this->borrarHuerfanos($simular);
        $desenganchados = $this->desengancharProductoColgante($simular);
        $costeados = $this->completarCostos($simular);
        $this->line('');

        $this->table(
            ['Reparación', 'Movimientos'],
            [
                ['Huérfanos borrados (no apuntaban a nada)', $borrados],
                ['producto_id colgante limpiado', $desenganchados],
                ['Costo completado desde precio_proveedor', $costeados],
            ]
        );

        $sinCosto = InventarioMovimiento::whereNull('costo_unit')->orWhere('costo_unit', 0)->count();
        if ($sinCosto > 0) {
            $this->warn("Quedan {$sinCosto} movimiento(s) sin costo: su producto no tiene precio_proveedor.");
        } else {
            $this->info('Todo el kardex tiene costo.');
        }

        return self::SUCCESS;
    }

    /** Movimientos cuyo producto Y cuya variante fueron borrados. */
    private function borrarHuerfanos(bool $simular): int
    {
        $ids = DB::table('inventario_movimientos as m')
            ->leftJoin('productos as p', 'p.id', '=', 'm.producto_id')
            ->leftJoin('producto_variantes as v', 'v.id', '=', 'm.variante_id')
            ->whereNull('p.id')
            ->whereNull('v.id')
            ->pluck('m.id');

        if ($ids->isEmpty()) {
            $this->line('Sin movimientos huérfanos.');
            return 0;
        }

        $unidades = DB::table('inventario_movimientos')->whereIn('id', $ids)->sum('cantidad');
        $this->line("Huérfanos: {$ids->count()} movimiento(s), {$unidades} unidad(es) de stock fantasma.");

        if (! $simular) {
            DB::table('inventario_movimientos')->whereIn('id', $ids)->delete();
        }

        return $ids->count();
    }

    /**
     * Referencias rotas donde el OTRO sujeto sí existe.
     *
     * Se da en los dos sentidos: filas con una variante viva y el
     * `producto_id` apuntando a un producto borrado, y filas con un producto
     * vivo y el `variante_id` apuntando a una variante borrada (107 de estas
     * en la base del cliente). En ambos casos la fila sirve: se limpia la
     * referencia muerta y queda colgando del sujeto que sí existe.
     */
    private function desengancharProductoColgante(bool $simular): int
    {
        $productoRoto = DB::table('inventario_movimientos as m')
            ->leftJoin('productos as p', 'p.id', '=', 'm.producto_id')
            ->join('producto_variantes as v', 'v.id', '=', 'm.variante_id')
            ->whereNotNull('m.producto_id')
            ->whereNull('p.id')
            ->pluck('m.id');

        $varianteRota = DB::table('inventario_movimientos as m')
            ->join('productos as p', 'p.id', '=', 'm.producto_id')
            ->leftJoin('producto_variantes as v', 'v.id', '=', 'm.variante_id')
            ->whereNotNull('m.variante_id')
            ->whereNull('v.id')
            ->pluck('m.id');

        if ($productoRoto->isEmpty() && $varianteRota->isEmpty()) return 0;

        $this->line("Con variante válida y producto_id roto: {$productoRoto->count()}.");
        $this->line("Con producto válido y variante_id roto: {$varianteRota->count()}.");

        if (! $simular) {
            if ($productoRoto->isNotEmpty()) {
                DB::table('inventario_movimientos')->whereIn('id', $productoRoto)->update(['producto_id' => null]);
            }
            if ($varianteRota->isNotEmpty()) {
                DB::table('inventario_movimientos')->whereIn('id', $varianteRota)->update(['variante_id' => null]);
            }
        }

        return $productoRoto->count() + $varianteRota->count();
    }

    /** Costo desde el precio al que se le compra al proveedor. */
    private function completarCostos(bool $simular): int
    {
        $filas = DB::table('inventario_movimientos as m')
            ->leftJoin('producto_variantes as v', 'v.id', '=', 'm.variante_id')
            ->leftJoin('productos as p', 'p.id', '=', DB::raw('COALESCE(m.producto_id, v.producto_id)'))
            ->where(fn ($q) => $q->whereNull('m.costo_unit')->orWhere('m.costo_unit', 0))
            ->where('p.precio_proveedor', '>', 0)
            ->get(['m.id', 'p.precio_proveedor']);

        if ($filas->isEmpty()) return 0;

        $this->line("Sin costo y con precio_proveedor disponible: {$filas->count()}.");
        if (! $simular) {
            foreach ($filas->groupBy('precio_proveedor') as $precio => $grupo) {
                DB::table('inventario_movimientos')
                    ->whereIn('id', $grupo->pluck('id'))
                    ->update(['costo_unit' => $precio]);
            }
        }

        return $filas->count();
    }
}
