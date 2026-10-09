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
        // Postman oficial SIIGO ("Product complete - Create") confirma que
        // `barcode`, `brand`, `tariff`, `model` van DENTRO de
        // `additional_fields` como OBJETO (no como campos raíz, aunque la doc
        // Apiary diga lo contrario). Verificado 2026-10-02.
        $additionalFields = array_filter([
            'barcode' => $this->trunc($p->barcode_padre, 50),
            'brand'   => $this->trunc(optional($p->marca)->siigo_brand_name ?: optional($p->marca)->nombre, 50),
            'tariff'  => $this->resolverTariff($p),
            'model'   => $this->trunc($p->modelo_siigo, 50),
        ], fn ($v) => $v !== null && $v !== '');

        // A2 FIX · camposSiigoExtra() antes se declaraba pero NUNCA se llamaba.
        //   Toda la metadata fiscal (línea/grupo/subgrupo/clase, NIIF, lotes,
        //   seriales, estadístico) se armaba pero jamás viajaba a SIIGO.
        //   Lo mergeamos acá · si Aracely mapeó custom_fields_map en settings,
        //   agrega los additional_fields ya con el id SIIGO correcto; sino
        //   los deja en `notes` como texto para que se vean en la ficha SIIGO.
        $extras = $this->camposSiigoExtra($p);
        if (! empty($extras['additional_fields'] ?? [])) {
            // Mergeamos el array de extras SIN pisar los 4 básicos del objeto raíz.
            //   SIIGO acepta additional_fields como ARRAY de {id,value} cuando
            //   custom_fields_map está configurado — en ese caso la rama anterior
            //   (objeto literal) la ignoramos a favor del array estructurado.
            $additionalFieldsFinal = $extras['additional_fields'];
        } else {
            $additionalFieldsFinal = $additionalFields;
        }

        return array_filter(array_merge([
            'code' => self::sanitizarCode($p->referencia, "producto {$p->id}"),
            'name' => $this->limpiarNombre($p->nombre ?? $p->referencia),
            'account_group' => $this->resolverAccountGroup($p),
            'type' => $p->tipo_siigo ?: 'Product',
            'stock_control' => (bool) ($p->stock_control ?? true),
            'active' => (bool) $p->activo,
            // FASE H5 fix · 3 campos SIIGO que estaban declarados en el Model
            //   pero el payload nunca los emitía. Los tests de paridad los
            //   exigen. SIIGO los acepta como raíz; si son null los dejamos
            //   fuera (array_filter de abajo).
            'available_for_sale' => isset($p->visible_en_facturas) ? (bool) $p->visible_en_facturas : null,
            'minimum_stock' => $p->stock_minimo !== null ? (float) $p->stock_minimo : null,
            'withholding_taxes' => $this->resolverRetencion($p) ?: null,
            'tax_classification' => $p->tax_classification ?: 'Taxed',
            'tax_included' => (bool) ($p->tax_included ?? false),
            'tax_consumption_value' => $p->tax_consumption_value !== null ? (float) $p->tax_consumption_value : null,
            'taxes' => $this->resolverTaxes($p),
            'prices' => $this->resolverPreciosAgregado($p),
            'unit' => $this->resolverUnit($p),
            'unit_label' => $this->trunc($p->unit_label ?: optional($p->unidadMedida)->nombre, 50) ?: 'Unidad',
            'reference' => $this->trunc($p->reference_fabrica ?: $p->referencia, 80),
            'description' => $this->limpiarDescripcion($p->descripcion),
            // A2 FIX · si camposSiigoExtra devolvió notes (texto legible como
            //   fallback cuando no hay custom_fields_map), lo anexamos a la
            //   descripción para que al menos quede visible en SIIGO.
            'notes' => $extras['notes'] ?? null,
            'additional_fields' => $additionalFieldsFinal ?: null,
        ]), fn ($v) => $v !== null && $v !== [] && $v !== '');
    }

    /**
     * Normaliza el código arancelario para el campo RAÍZ `tariff` de SIIGO:
     * strings numéricos de máximo 10 caracteres (el PUC colombiano usa 10
     * dígitos sin puntos, p. ej. 6111200000). El ERP permite el formato
     * con puntos (6111.20.00.00 = 12 chars) para legibilidad local.
     */
    private function resolverTariff(Producto $p): ?string
    {
        if (! $p->posicion_arancelaria) return null;
        return substr(str_replace(['.', ' '], '', (string) $p->posicion_arancelaria), 0, 10) ?: null;
    }

    /**
     * FASE F2.A15 · SIIGO acepta `images[]` como array de `{url, type}`. Las
     * imágenes locales viven en storage/public/productos/{id}/ — necesitan
     * ser accesibles públicamente desde SIIGO. En dev (localhost) SIIGO no
     * puede llegar; en prod la URL base debe ser pública (APP_URL).
     */
    private function resolverImagenes(Producto $p): array
    {
        // Limitación confirmada del sandbox SIIGO v1 (verificado 2026-10-01):
        //   - El campo `images` del POST /v1/products se acepta con HTTP 201
        //     pero no se persiste (ni `images[].url` ni `image.url`, con URL
        //     HTTPS pública o data URI base64 inline).
        //   - El endpoint dedicado `/v1/products/{id}/images` devuelve 404.
        //   - SIIGO Nube acepta imágenes solo desde su UI web, no vía API.
        // Las imágenes viven en el ERP y se muestran en el panel local.
        // Si en el futuro SIIGO habilita el campo, basta con reactivar la
        // construcción del array {url, type} aquí.
        return [];
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

        // SIIGO Postman oficial: `barcode`/`brand`/`tariff`/`model` van en
        // `additional_fields` como OBJETO (no raíz).
        $additionalFields = array_filter([
            'barcode' => $this->trunc($v->codigo_barras, 50),
            'brand'   => $this->trunc(optional($p->marca)->siigo_brand_name ?: optional($p->marca)->nombre, 50),
            'tariff'  => $this->resolverTariff($p),
            'model'   => $this->trunc($p->modelo_siigo, 50),
        ], fn ($x) => $x !== null && $x !== '');

        return array_filter(array_merge([
            'code' => self::sanitizarCode($v->codigo_barras, "variante {$v->id}"),
            'name' => $this->limpiarNombre(implode(' - ', $partesNombre)),
            'account_group' => $this->resolverAccountGroup($p),
            'type' => $p->tipo_siigo ?: 'Product',
            'stock_control' => (bool) ($p->stock_control ?? true),
            'active' => (bool) $p->activo,
            // FASE H5 fix · 3 campos SIIGO que faltaban en el payload (ver paraProducto).
            'available_for_sale' => isset($p->visible_en_facturas) ? (bool) $p->visible_en_facturas : null,
            'minimum_stock' => $p->stock_minimo !== null ? (float) $p->stock_minimo : null,
            'withholding_taxes' => $this->resolverRetencion($p) ?: null,
            'tax_classification' => $p->tax_classification ?: 'Taxed',
            'tax_included' => (bool) ($p->tax_included ?? false),
            'tax_consumption_value' => $p->tax_consumption_value ? (float) $p->tax_consumption_value : null,
            'taxes' => $this->resolverTaxes($p),
            'prices' => $this->resolverPreciosVariante($v),
            'unit' => $this->resolverUnit($p),
            'unit_label' => $this->trunc($p->unit_label ?: optional($p->unidadMedida)->nombre, 50) ?: 'Unidad',
            'reference' => $this->trunc($p->reference_fabrica ?: $p->referencia, 80),
            'description' => $this->limpiarDescripcion($p->descripcion),
            'additional_fields' => $additionalFields ?: null,
        ]), fn ($x) => $x !== null && $x !== [] && $x !== '');
    }

    /**
     * FASE H · Resuelve el array withholding_taxes[] a partir de retencion_siigo_id.
     * SIIGO espera [{"id": N}] donde N es el id del impuesto en SIIGO.
     */
    private function resolverRetencion(Producto $p): array
    {
        if (! $p->retencion_siigo_id) return [];
        $imp = $p->retencion;
        if (! $imp || ! $imp->siigo_id) return [];
        return [['id' => (int) $imp->siigo_id]];
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

        // `tariff` ya se envía como campo RAÍZ (ver resolverTariff / paraProducto),
        // conforme a la doc oficial de SIIGO. Aquí lo re-leemos solo para
        // seguir incluyéndolo en el bloque de `notes` fallback (visible como
        // texto en la ficha SIIGO cuando el tenant no tiene mapeo de
        // additional_fields configurado).
        $tariff = $this->resolverTariff($p);

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
        // caben en additional_fields sí quedan visibles. Incluimos también
        // `tariff` (posición arancelaria) si existe, para que al menos quede
        // registro visible en SIIGO aunque no esté mapeado en additional_fields.
        if (! empty($valores) || $tariff) {
            $todas = $valores;
            if ($tariff) $todas['tariff (posicion_arancelaria)'] = $tariff;
            $notas = collect($todas)
                ->map(fn ($v, $k) => str_replace('_', ' ', $k) . ': ' . $v)
                ->implode(' · ');
            $extras['notes'] = $this->trunc($notas, 500);
        }

        // `tariff` ya se envía como campo RAÍZ del payload (ver paraProducto /
        // paraVariante). SIIGO lo lee desde ahí · ya NO lo duplicamos dentro
        // de additional_fields ni customs_position (verificado 2026-10-01
        // contra la doc oficial de Apiary · esos campos se ignoraban en
        // silencio y pesaban el POST).

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
    /**
     * Codigo con el que un producto o variante existe en SIIGO.
     *
     * Es publico y estatico a proposito: cualquier payload que tenga que
     * REFERENCIAR un producto ya publicado —por ejemplo la linea de un
     * asiento de inventario— necesita exactamente este mismo codigo. Si cada
     * lado lo calculara a su manera, SIIGO responderia «Invalid code» y el
     * motivo seria invisible.
     */
    public static function sanitizarCode(?string $raw, string $ctx): string
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
    /**
     * SIIGO rechaza con `invalid_description` cuando `description` contiene
     * caracteres HTML-ish (`<`, `>`, `->`, `<-`, backticks, etc.). Limpia
     * esos y colapsa whitespace.
     */
    private function limpiarDescripcion(?string $raw): ?string
    {
        if ($raw === null) return null;
        $d = trim((string) $raw);
        if ($d === '') return null;

        $enc = mb_detect_encoding($d, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($enc && $enc !== 'UTF-8') $d = mb_convert_encoding($d, 'UTF-8', $enc);

        // Reemplazar flechas (unicode y ASCII), HTML tags y símbolos no
        // soportados por el regex de `description` de SIIGO. Verificado contra
        // los códigos `invalid_description` del API 2026-10-02.
        $d = strtr($d, [
            '->' => ' a ', '<-' => ' de ', '=>' => ' a ',
            '→' => ' a ', '←' => ' de ', '⇒' => ' a ', '⇨' => ' a ', '↣' => ' a ',
            '<' => ' ', '>' => ' ', '`' => "'", '"' => "'",
            '·' => '-', '•' => '-', '▪' => '-', '◦' => '-', '…' => '...',
            '«' => '"', '»' => '"', '“' => '"', '”' => '"', '‘' => "'", '’' => "'",
            "\r\n" => ' ', "\n" => ' ', "\r" => ' ', "\t" => ' ',
        ]);

        // Filtrar emojis.
        $d = preg_replace('/[\x{1F000}-\x{1FFFF}\x{2700}-\x{27BF}]/u', '', $d) ?? $d;
        // Colapsar whitespace.
        $d = trim(preg_replace('/\s+/u', ' ', $d) ?? $d);

        return $this->trunc($d, 2500) ?: null;
    }

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

        // SIIGO rechaza middle-dot (·), bullet (•) y otros puntuadores raros
        // con `invalid_name`. Normalizamos a guión para preservar legibilidad.
        $n = strtr($n, ['·' => '-', '•' => '-', '▪' => '-', '◦' => '-']);
        // Colapsar espacios múltiples y recortar.
        $n = trim(preg_replace('/\s+/u', ' ', $n) ?? $n);

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
        $fallback = (int) env('SIIGO_ACCOUNT_GROUP_DEFAULT', 0) ?: null;

        // FASE F2.A4 · prioridad al override del producto (si el user eligió
        // uno distinto en el form); si no, cae a la categoría; si no, al mapa.
        if (! empty($p->siigo_account_group_override)) {
            return (int) $p->siigo_account_group_override;
        }

        $cat = $p->categoriaMaestra;
        if ($cat && ! empty($cat->siigo_account_group_id)) {
            return (int) $cat->siigo_account_group_id;
        }

        // Vía 2 (compatibilidad) · match por nombre contra el catálogo cacheado.
        $nombreCat = optional($cat)->nombre ?? $p->categoria;
        if (! $nombreCat) return $fallback;

        $mapa = Cache::remember('siigo:catalog:account-groups', 3600, function () {
            return SiigoCatalogo::where('tipo', 'account-groups')
                ->get(['codigo', 'nombre'])
                ->mapWithKeys(fn ($c) => [mb_strtolower($c->nombre) => (int) $c->codigo])
                ->all();
        });

        return $mapa[mb_strtolower($nombreCat)] ?? $fallback;
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
        // FASE F1.C3 · recolectar ids extra (impuesto cargo dos del form SIIGO).
        $extraIds = collect([
            optional($p->impuestoCargoDos)->siigo_id,
        ])->filter()->map(fn ($x) => (int) $x);

        // Vía 1 (preferida) · tabla pivot producto_impuestos. Permite N impuestos
        // por producto (IVA + ReteFuente + ReteICA + …), como SIIGO acepta.
        if (\Schema::hasTable('producto_impuestos')) {
            $pivotIds = \DB::table('producto_impuestos')
                ->join('impuestos', 'impuestos.id', '=', 'producto_impuestos.impuesto_id')
                ->where('producto_impuestos.producto_id', $p->id)
                ->whereNotNull('impuestos.siigo_id')
                ->pluck('impuestos.siigo_id')
                ->filter()->unique()->values();
            if ($pivotIds->isNotEmpty()) {
                return $pivotIds->concat($extraIds)->unique()->map(fn ($id) => ['id' => (int) $id])->all();
            }
        }

        $imp = $p->impuesto;
        if (! $imp || $imp->porcentaje === null) {
            // Puede no haber impuesto cargo primario pero sí el "cargo dos" solo.
            return $extraIds->map(fn ($id) => ['id' => (int) $id])->all();
        }

        // Vía 2 · siigo_id directo en el impuesto local (nuevo tras migración).
        if (! empty($imp->siigo_id)) {
            return collect([(int) $imp->siigo_id])->concat($extraIds)->unique()->map(fn ($id) => ['id' => $id])->all();
        }

        // Vía 3 (compatibilidad) · match por porcentaje contra catálogo SIIGO.
        $target = (float) $imp->porcentaje;
        $mapa = Cache::remember('siigo:catalog:taxes-normalized', 3600, function () {
            $out = [];
            foreach (SiigoCatalogo::where('tipo', 'taxes')->get(['codigo', 'payload']) as $c) {
                $payload = $c->payload ?? [];
                $pct = isset($payload['percentage']) ? (float) $payload['percentage']
                    : (isset($payload['rate']) ? (float) $payload['rate'] : null);
                if ($pct !== null) {
                    $out[number_format($pct, 2, '.', '')] = (int) $c->codigo;
                }
            }
            return $out;
        });

        foreach ($mapa as $pctStr => $id) {
            if (abs((float) $pctStr - $target) < 0.01) {
                return [['id' => $id]];
            }
        }

        throw new InvalidArgumentException(
            "Producto {$p->id} declara impuesto {$target}% pero SIIGO no tiene una tasa que matchee · "
            ."sincronizar catálogo con `siigo:sync catalogos` o linkear impuesto por siigo_id."
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

        if ($rows->isEmpty()) {
            // Fallback · si la variante no tiene precios propios, hereda el
            // `precio_proveedor` del padre. Solo mandamos el precio si la
            // lista local predeterminada tiene `siigo_id` mapeado al tenant
            // real · adivinar el id (ej. del sandbox demo) corrompe el UI
            // SIIGO Nube aunque el POST se vea exitoso.
            $padre = $v->producto;
            if ($padre && (float) $padre->precio_proveedor > 0) {
                // SIIGO exige `position` (orden ordinal de la lista en el
                // tenant), no `id`. `listas_precios.siigo_id` guarda ese
                // position (1-8 según el orden de /v1/price-lists).
                // Preferencia · predeterminada local SI tiene siigo_id,
                // sino la primera lista mapeada. "Mayorista" es nuestra
                // interna · no bloquea el push cuando no está mapeada.
                $siigoPosition = \DB::table('listas_precios')
                    ->where('predeterminada', true)
                    ->whereNotNull('siigo_id')
                    ->value('siigo_id')
                    ?: \DB::table('listas_precios')
                        ->whereNotNull('siigo_id')
                        ->where('activa', true)
                        ->orderBy('siigo_id')
                        ->value('siigo_id');
                if ($siigoPosition) {
                    return [[
                        'currency_code' => 'COP',
                        'price_list' => [[
                            'position' => (int) $siigoPosition,
                            'value' => round((float) $padre->precio_proveedor, 2),
                        ]],
                    ]];
                }
                // Sin mapeo · no inventamos ID. Aracely debe mapear la lista
                // local → price-list SIIGO desde el panel admin tras el
                // onboarding del tenant.
            }
            return [];
        }

        return $this->armarPriceList($rows);
    }

    /** Empaqueta las rows de PrecioVariante en el formato SIIGO. */
    private function armarPriceList($rows): array
    {
        // Mapa lista_id local → siigo_id (que guarda la POSITION ordinal en
        // SIIGO, 1-N según el orden de /v1/price-lists). Si la lista local
        // no está mapeada al tenant, se omite esa fila · SIIGO rechaza
        // 400 "parameter_required position" cuando falta el campo.
        $mapa = \DB::table('listas_precios')
            ->whereIn('id', $rows->pluck('lista_id')->unique())
            ->whereNotNull('siigo_id')
            ->pluck('siigo_id', 'id');

        $priceList = $rows->map(function ($r) use ($mapa) {
            $siigoPosition = $mapa[$r->lista_id] ?? null;
            if (! $siigoPosition) return null;
            return [
                'position' => (int) $siigoPosition,
                'value' => round((float) $r->precio, 2),
            ];
        })->filter()->values()->all();

        if (empty($priceList)) return [];

        return [[
            'currency_code' => 'COP',
            'price_list' => $priceList,
        ]];
    }

    private function resolverUnit(Producto $p): string
    {
        $um = $p->unidadMedida;
        // Vía 1 (preferida) · codigo_unece directo en la unidad local (nuevo tras migración).
        if ($um && ! empty($um->codigo_unece)) {
            return (string) $um->codigo_unece;
        }
        // Vía 2 (compatibilidad) · mapeo fijo ERP → UN/ECE. '94' = Unit por default.
        $mapa = [
            'UND' => '94', 'UNIDAD' => '94', 'UN' => '94',
            'CAJ' => 'BX', 'CAJA' => 'BX', 'BOX' => 'BX',
            'DOC' => 'DZN', 'DOCENA' => 'DZN',
            'PACK' => 'PK', 'PK' => 'PK', 'PQT' => 'PK',
            'KG' => 'KGM', 'KGM' => 'KGM', 'GR' => 'GRM',
            'LT' => 'LTR', 'LTR' => 'LTR', 'ML' => 'MLT',
            'MT' => 'MTR', 'MTR' => 'MTR', 'CM' => 'CMT',
            'HR' => 'HUR', 'SRV' => 'E48',
        ];
        $codigo = strtoupper(trim((string) optional($um)->codigo));
        return $mapa[$codigo] ?? '94';
    }
}
