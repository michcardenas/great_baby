<?php

namespace App\Modules\Inventario\Support;

use App\Modules\Dropi\Models\InventarioMovimiento;

/**
 * Costo promedio ponderado de lo que hay en una ubicación.
 *
 * Es el costo con el que sale la mercancía: se promedian las ENTRADAS con su
 * costo, ponderadas por cantidad. Las salidas no entran en el promedio porque
 * salen justamente a ese costo; si se incluyeran, cada venta movería el costo
 * de lo que queda en bodega, que es exactamente lo que el método promedio
 * busca evitar.
 *
 * La fórmula ya existía escrita a mano dentro de `DevolucionProveedorController`
 * y sólo servía para variantes. Acá vive una sola vez y entiende también los
 * productos agregados (los que no tienen variante).
 */
class CostoPromedio
{
    /**
     * Costos de varios sujetos en una ubicación, en una sola consulta.
     *
     * @param  list<array{variante_id:?int, producto_id:?int}>  $sujetos
     * @return array<string, float>  clave `v:{id}` o `p:{id}` => costo unitario
     */
    public static function porUbicacion(array $sujetos, int $ubicacionId): array
    {
        $variantes = array_values(array_filter(array_column($sujetos, 'variante_id')));
        // Un producto sólo cuenta como «agregado» si la línea no trae variante.
        $productos = [];
        foreach ($sujetos as $s) {
            if (empty($s['variante_id']) && ! empty($s['producto_id'])) {
                $productos[] = $s['producto_id'];
            }
        }

        $promedio = 'CASE WHEN SUM(CASE WHEN cantidad > 0 THEN cantidad ELSE 0 END) > 0 '
            .'THEN SUM(CASE WHEN cantidad > 0 THEN cantidad * COALESCE(costo_unit, 0) ELSE 0 END) '
            .'     / SUM(CASE WHEN cantidad > 0 THEN cantidad ELSE 0 END) '
            .'ELSE 0 END AS costo_prom';

        $out = [];

        if ($variantes) {
            InventarioMovimiento::query()
                ->whereIn('variante_id', $variantes)
                ->where('ubicacion_id', $ubicacionId)
                ->selectRaw("variante_id, {$promedio}")
                ->groupBy('variante_id')
                ->get()
                ->each(function ($r) use (&$out) {
                    $out['v:'.$r->variante_id] = round((float) $r->costo_prom, 2);
                });
        }

        if ($productos) {
            InventarioMovimiento::query()
                ->whereIn('producto_id', $productos)
                ->whereNull('variante_id')
                ->where('ubicacion_id', $ubicacionId)
                ->selectRaw("producto_id, {$promedio}")
                ->groupBy('producto_id')
                ->get()
                ->each(function ($r) use (&$out) {
                    $out['p:'.$r->producto_id] = round((float) $r->costo_prom, 2);
                });
        }

        return $out;
    }

    /** Clave con la que se busca un sujeto en el resultado de arriba. */
    public static function clave(?int $varianteId, ?int $productoId): string
    {
        return $varianteId ? "v:{$varianteId}" : "p:{$productoId}";
    }
}
