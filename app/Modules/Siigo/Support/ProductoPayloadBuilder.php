<?php

namespace App\Modules\Siigo\Support;

use App\Modules\Catalogo\Models\PrecioVariante;
use App\Modules\Dropi\Models\Producto;
use App\Modules\Dropi\Models\ProductoVariante;
use App\Modules\Siigo\Models\SiigoCatalogo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Traduce un Producto/Variante local al formato del payload SIIGO API
 * (`POST/PUT /v1/products`). Encapsula la sanitización, defaults, y
 * lookup de catálogos (account_group, taxes, unit) para no repetir en
 * cada Action.
 *
 * Reglas clave:
 *   - `code` no admite comillas simples ni espacios (regex ^[^'\s]+$).
 *     Aquí se sanitiza reemplazando espacios por "-" y quitando `'`.
 *     Si tras sanitizar queda vacío, lanza excepción (evita silencio).
 *   - Si `desglose_stock = true` → cada variante es un producto Siigo
 *     independiente (`code = variante.codigo_barras`).
 *   - Si `desglose_stock = false` → solo el padre va (`code = productos.referencia`).
 *   - Los account_group/taxes/unit se resuelven por lookup en SiigoCatalogo
 *     con fallback a defaults seguros (primer activo, sin impuestos, "94").
 *   - UTF-8: forzamos a UTF-8 antes de regex de emojis para evitar corromper
 *     nombres con caracteres latin1 (bug C2 del auditor).
 */
class ProductoPayloadBuilder
{
    /**
     * Payload para un producto AGREGADO (padre, desglose_stock=false).
     * Un solo POST/PUT por producto.
     */
    public function paraProducto(Producto $p): array
    {
        return array_filter(array_merge([
            'code' => $this->sanitizarCode($p->referencia, "producto {$p->id}"),
            'name' => $this->limpiarNombre($p->nombre ?? $p->referencia),
            'account_group' => $this->resolverAccountGroup($p),
            'type' => 'Product',
            'stock_control' => (bool) ($p->stock_control ?? true),
            'active' => (bool) $p->activo,
            'tax_classification' => 'Taxed',
            'tax_included' => false,
            'taxes' => $this->resolverTaxes($p),
            'prices' => $this->resolverPreciosAgregado($p),
            'unit' => $this->resolverUnit($p),
            'unit_label' => 'Unidad',
            'reference' => $this->trunc($p->referencia, 80),
            'description' => $this->trunc($p->descripcion, 2500),
            'brand' => $this->trunc(optional($p->marca)->nombre, 50),
        ], $this->camposSiigoExtra($p)), fn ($v) => $v !== null && $v !== [] && $v !== '');
    }

    /**
     * Payload para una VARIANTE granular (desglose_stock=true).
     * Cada variante = 1 producto Siigo con code = codigo_barras.
     * A6 · el caller debe pre-cargar `$v->setRelation('producto', $p)`
     * para evitar N+1.
     */
    public function paraVariante(ProductoVariante $v): array
    {
        $p = $v->producto;
        $partesNombre = array_filter([
            $p->nombre ?? $p->referencia,
            $v->color_nombre,
            $v->talla ? "Talla {$v->talla}" : null,
        ]);

        return array_filter(array_merge([
            'code' => $this->sanitizarCode($v->codigo_barras, "variante {$v->id}"),
            'name' => $this->limpiarNombre(implode(' · ', $partesNombre)),
            'account_group' => $this->resolverAccountGroup($p),
            'type' => 'Product',
            'stock_control' => true,
            'active' => (bool) $p->activo,
            'tax_classification' => 'Taxed',
            'tax_included' => false,
            'taxes' => $this->resolverTaxes($p),
            'prices' => $this->resolverPreciosVariante($v),
            'unit' => $this->resolverUnit($p),
            'unit_label' => 'Unidad',
            'reference' => $this->trunc($p->referencia, 80),
            'description' => $this->trunc($p->descripcion, 2500),
            'barcode' => $this->trunc($v->codigo_barras, 50),
            'brand' => $this->trunc(optional($p->marca)->nombre, 50),
        ], $this->camposSiigoExtra($p)), fn ($v) => $v !== null && $v !== [] && $v !== '');
    }

    /**
     * Sprint 4 · G.4 · Campos SIIGO adicionales del Kardex Referencias.
     * Se agregan al payload solo si el producto los tiene definidos.
     *
     * Basado en doc oficial SIIGO Ilimitada:
     *   - customs_position (posición arancelaria)
     *   - additional_fields.linea/grupo/subgrupo/clase (jerarquía)
     *   - additional_fields.rentabilidad_pct (control de precio mínimo)
     *   - additional_fields.protected_price (bloquea cambio al facturar)
     *   - additional_fields.manages_lots / manages_serials
     *   - additional_fields.extended_description (descripción ampliada)
     *   - additional_fields.technical_sheet (ficha técnica)
     *   - additional_fields.niif_selling_expense / niif_net_realizable_value
     */
    private function camposSiigoExtra(Producto $p): array
    {
        $extras = [];

        if ($p->posicion_arancelaria) {
            $extras['customs_position'] = (string) $p->posicion_arancelaria;
        }

        // QA-FIX #8 · SIIGO's additional_fields schema es [{id, value}] con
        // IDs pre-registrados por Aracely en el portal SIIGO (Configuración →
        // Campos adicionales). Sin ese mapa, mandar nombres en español los
        // rechaza silenciosamente. Solo lo poblamos si Aracely configuró el
        // mapa en settings (`siigo.custom_fields_map`) — de lo contrario
        // guardamos los valores dentro de `metadata` para trazabilidad
        // local y `notes` para que sean visibles en SIIGO como texto.
        $mapa = (array) setting('siigo.custom_fields_map', []); // ej ['linea' => 12345, 'grupo' => 12346]

        $valores = array_filter([
            'linea' => optional($p->linea)->siigo_id ?: optional($p->linea)->codigo,
            'grupo' => optional($p->grupo)->siigo_id ?: optional($p->grupo)->codigo,
            'subgrupo' => optional($p->subgrupo)->codigo,
            'clase' => optional($p->clase)->codigo,
            'rentabilidad_pct' => $p->rentabilidad_pct ? (float) $p->rentabilidad_pct : null,
            'discount_default_pct' => $p->descuento_default_pct ? (float) $p->descuento_default_pct : null,
            'protected_price' => $p->proteger_precio ? 'sí' : null,
            'manages_lots' => $p->maneja_lotes ? 'sí' : null,
            'manages_serials' => $p->maneja_seriales ? 'sí' : null,
            'is_statistical' => $p->es_estadistico ? 'sí' : null,
            'niif_selling_expense' => $p->valor_gasto_venta_niif ? (float) $p->valor_gasto_venta_niif : null,
            'niif_net_realizable_value' => $p->valor_neto_realizable_niif ? (float) $p->valor_neto_realizable_niif : null,
            'reposicion_max_dias' => $p->reposicion_max_dias ?: null,
            'factor_conversion' => $p->factor_conversion ? (float) $p->factor_conversion : null,
        ], fn ($v) => $v !== null && $v !== '');

        // Vía A · si Aracely mapeó los ids en el portal → schema correcto.
        if (! empty($mapa)) {
            $additional = [];
            foreach ($valores as $llave => $val) {
                if (isset($mapa[$llave]) && (int) $mapa[$llave] > 0) {
                    $additional[] = ['id' => (int) $mapa[$llave], 'value' => (string) $val];
                }
            }
            if (! empty($additional)) {
                $extras['additional_fields'] = $additional;
            }
        }

        // Vía B (fallback) · anexamos los valores al campo `notes` como texto
        // legible; SIIGO los muestra en la ficha del producto. Los que no
        // caben en additional_fields sí quedan visibles.
        if (! empty($valores)) {
            $notas = collect($valores)
                ->map(fn ($v, $k) => str_replace('_', ' ', $k) . ': ' . $v)
                ->implode(' · ');
            $extras['notes'] = $this->trunc($notas, 500);
        }

        // Descripción ampliada + ficha técnica van directo al campo `description`
        // extendido de SIIGO (que sí soporta texto libre) — se concatenan aquí.
        $descExt = trim((string) $p->descripcion_ampliada);
        $ficha = trim((string) $p->ficha_tecnica);
        if ($descExt || $ficha) {
            $extras['description_extended'] = $this->trunc(trim($descExt . "\n" . $ficha), 2500);
        }

        return $extras;
    }

    /**
     * SIIGO code: alfanumérico sin espacios ni comilla simple, máx 30 chars.
     * Regex oficial: ^[^'\s]+$
     * C3 · si queda vacío, throw explícito para no dejar el POST silenciarse
     * con `parameter_required` de Siigo.
     */
    private function sanitizarCode(?string $raw, string $ctx): string
    {
        $c = trim((string) $raw);
        $c = str_replace(["'", ' '], ['', '-'], $c);
        $c = preg_replace('/-+/', '-', $c) ?? $c;
        $c = trim($c, '-');
        $c = mb_substr($c, 0, 30);
        if ($c === '') {
            throw new InvalidArgumentException("SIIGO code vacío para {$ctx} · valor original: '{$raw}'");
        }
        return $c;
    }

    /**
     * Sanea el nombre.
     *
     * B4-M2 · antes teníamos `mb_convert_encoding($n, 'UTF-8', 'UTF-8')` que
     * es un no-op (misma codificación de origen y destino). Ahora detectamos
     * la codificación real y convertimos desde ella. Cubre nombres importados
     * de Excel/CSV en Latin1/Windows-1252 que antes rompían la regex `/u`.
     */
    private function limpiarNombre(?string $raw): string
    {
        $n = trim((string) $raw);
        if ($n === '') return 'Producto sin nombre';

        $encodingDetectado = mb_detect_encoding($n, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encodingDetectado && $encodingDetectado !== 'UTF-8') {
            $n = mb_convert_encoding($n, 'UTF-8', $encodingDetectado);
        }

        // Filtrar emojis (rangos BMP alto + símbolos), preserva ñáéíóú (U+00C0-U+00FF).
        $filtered = preg_replace('/[\x{1F000}-\x{1FFFF}\x{2700}-\x{27BF}]/u', '', $n);
        $n = $filtered ?? $n;
        return $this->trunc($n, 100) ?: 'Producto sin nombre';
    }

    private function trunc(?string $raw, int $max): ?string
    {
        if ($raw === null) return null;
        $s = trim((string) $raw);
        return $s === '' ? null : mb_substr($s, 0, $max);
    }

    /**
     * Resuelve account_group buscando por nombre de categoría en SiigoCatalogo.
     * M1 · si no matchea, retorna null (mejor que un fallback contable incorrecto).
     * SIIGO devolverá parameter_required y quedará registro claro en log.
     */
    private function resolverAccountGroup(Producto $p): ?int
    {
        $nombreCat = optional($p->categoriaMaestra)->nombre ?? $p->categoria;
        if (! $nombreCat) return null;

        // B3-P2 · cache del catálogo (cambia ~1×/día vs 20k llamadas por sync).
        $mapa = Cache::remember('siigo:catalog:account-groups', 3600, function () {
            return SiigoCatalogo::where('tipo', 'account-groups')
                ->get(['codigo', 'nombre'])
                ->mapWithKeys(fn ($c) => [mb_strtolower($c->nombre) => (int) $c->codigo])
                ->all();
        });

        return $mapa[mb_strtolower($nombreCat)] ?? null;
    }

    /**
     * M2 · comparación tolerante de porcentaje (float vs "19.00" string en JSON).
     * Convertimos ambos a float con tolerancia epsilon.
     *
     * B2-A2 · si el producto declara impuesto pero SIIGO no tiene un tax que
     * matchee (catálogo local desactualizado, tasa nueva creada en el ERP),
     * en vez de devolver `[]` con `tax_classification='Taxed'` (que hace
     * reventar el POST con `parameter_required` sin explicación clara) lanzamos
     * excepción tipada. El caller decide (log claro en la Action).
     */
    private function resolverTaxes(Producto $p): array
    {
        $imp = $p->impuesto;
        if (! $imp || $imp->porcentaje === null) return [];

        $target = (float) $imp->porcentaje;

        // B3-P2 · cache del catálogo (pares [porcentaje→id]).
        $mapa = Cache::remember('siigo:catalog:taxes-normalized', 3600, function () {
            $out = [];
            foreach (SiigoCatalogo::where('tipo', 'taxes')->get(['codigo', 'payload']) as $c) {
                $payload = $c->payload ?? [];
                $pct = isset($payload['percentage']) ? (float) $payload['percentage']
                    : (isset($payload['rate']) ? (float) $payload['rate'] : null);
                if ($pct !== null) {
                    // Redondeamos a 2 decimales para la clave (evita drift float).
                    $out[number_format($pct, 2, '.', '')] = (int) $c->codigo;
                }
            }
            return $out;
        });

        // Match con tolerancia epsilon 0.01.
        foreach ($mapa as $pctStr => $id) {
            if (abs((float) $pctStr - $target) < 0.01) {
                return [['id' => $id]];
            }
        }

        throw new InvalidArgumentException(
            "Producto {$p->id} declara impuesto {$target}% pero SIIGO no tiene una tasa que matchee · "
            ."sincronizar catálogo con `siigo:sync catalogos` o corregir el impuesto local."
        );
    }

    private function resolverPreciosAgregado(Producto $p): array
    {
        $precio = (float) ($p->precio_proveedor ?? 0);
        if ($precio <= 0) return [];
        return [[
            'currency_code' => 'COP',
            'price_list' => [
                ['position' => 1, 'value' => round($precio, 2)],
            ],
        ]];
    }

    /**
     * M3 · si hay más de 12 listas, se toman las primeras 12 ordenadas por lista_id
     * y se registra warning en log (SIIGO máx 12).
     *
     * B3-P1 · si la variante trae `preciosVigentes` pre-cargados (eager load
     * desde el caller), evitamos las 2 queries (COUNT+SELECT) por variante.
     * Con 500 productos granulares × 20 variantes = 20k queries ahorradas.
     */
    private function resolverPreciosVariante(ProductoVariante $v): array
    {
        // Fast path: relación pre-cargada.
        if ($v->relationLoaded('preciosVigentes')) {
            $rows = $v->preciosVigentes;
            $total = $rows->count();
            if ($total > 12) {
                Log::channel('siigo')->warning('Variante con más de 12 listas de precio · SIIGO max 12', [
                    'variante_id' => $v->id, 'total_listas' => $total,
                ]);
                $rows = $rows->sortBy('lista_id')->take(12);
            }
            if ($rows->isEmpty()) return [];
            return $this->armarPriceList($rows);
        }

        // Fallback (path viejo, para llamadores sin eager).
        $rows = PrecioVariante::where('variante_id', $v->id)
            ->where(function ($q) {
                $q->whereNull('vigente_hasta')
                  ->orWhere('vigente_hasta', '>=', now()->toDateString());
            })
            ->orderBy('lista_id')
            ->limit(13)  // 13 en vez de 12 para detectar overflow sin COUNT separado
            ->get();

        $total = $rows->count();
        if ($total > 12) {
            Log::channel('siigo')->warning('Variante con más de 12 listas de precio · SIIGO max 12', [
                'variante_id' => $v->id, 'total_listas' => "$total+",
            ]);
            $rows = $rows->take(12);
        }

        if ($rows->isEmpty()) return [];

        return $this->armarPriceList($rows);
    }

    /** Empaqueta las rows de PrecioVariante en el formato SIIGO. */
    private function armarPriceList($rows): array
    {
        $priceList = $rows->map(function ($r) {
            // B4 · cast defensivo: si lista_id fuera string/UUID, evitar position=0.
            $pos = (int) $r->lista_id;
            if ($pos < 1 || $pos > 12) $pos = 1;
            return [
                'position' => $pos,
                'value' => round((float) $r->precio, 2),
            ];
        })->values()->all();

        return [[
            'currency_code' => 'COP',
            'price_list' => $priceList,
        ]];
    }

    private function resolverUnit(Producto $p): string
    {
        return optional($p->unidadMedida)->codigo ?: '94';
    }
}
