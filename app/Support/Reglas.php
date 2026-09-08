<?php

namespace App\Support;

use App\Models\ReglaNegocio;

class Reglas
{
    /** Diccionario canónico de reglas (defaults + metadatos para la UI). */
    public const DEFAULTS = [
        // ============ EMPAQUE ============
        'empaque.min_foto_bytes' => ['grupo' => 'empaque', 'tipo' => 'int', 'valor' => 30000, 'etiqueta' => 'Tamaño mínimo de foto de empaque (bytes)', 'descripcion' => 'Evita fotos vacías/negras; 30 KB por defecto'],
        'empaque.max_foto_bytes' => ['grupo' => 'empaque', 'tipo' => 'int', 'valor' => 3_000_000, 'etiqueta' => 'Tamaño máximo de foto (bytes)', 'descripcion' => '3 MB por defecto'],
        'empaque.foto_obligatoria' => ['grupo' => 'empaque', 'tipo' => 'bool', 'valor' => '1', 'etiqueta' => 'Foto obligatoria para confirmar empaque', 'descripcion' => 'Bloquea el confirmar si no hay foto'],
        'empaque.tiempo_objetivo_seg' => ['grupo' => 'empaque', 'tipo' => 'int', 'valor' => 180, 'etiqueta' => 'Tiempo objetivo por empaque (segundos)', 'descripcion' => 'Base para semáforos y ranking'],
        'empaque.rate_limit_min' => ['grupo' => 'empaque', 'tipo' => 'int', 'valor' => 120, 'etiqueta' => 'Escaneos máximos por minuto', 'descripcion' => 'Throttle en la estación'],
        'empaque.retention_foto_dias' => ['grupo' => 'empaque', 'tipo' => 'int', 'valor' => 180, 'etiqueta' => 'Días retención foto empaque', 'descripcion' => 'Purga automática de fotos más antiguas que N días post-empaque (Habeas Data)'],

        // ============ DASHBOARD (alertas) ============
        'dashboard.alerta_pendientes' => ['grupo' => 'dashboard', 'tipo' => 'int', 'valor' => 50, 'etiqueta' => 'Alerta si pendientes por empacar >', 'descripcion' => 'Dispara alerta en Torre de Control'],
        'dashboard.alerta_tasa_devolucion' => ['grupo' => 'dashboard', 'tipo' => 'float', 'valor' => '15', 'etiqueta' => 'Alerta si tasa devolución % >', 'descripcion' => 'Umbral crítico de devolución del día'],
        'dashboard.refresh_seg' => ['grupo' => 'dashboard', 'tipo' => 'int', 'valor' => 30, 'etiqueta' => 'Auto-refresh dashboard (segundos)', 'descripcion' => ''],

        // ============ CARTERA · semáforo ============
        'cartera.bucket_1_dias' => ['grupo' => 'cartera', 'tipo' => 'int', 'valor' => 30, 'etiqueta' => 'Semáforo verde hasta N días', 'descripcion' => ''],
        'cartera.bucket_2_dias' => ['grupo' => 'cartera', 'tipo' => 'int', 'valor' => 60, 'etiqueta' => 'Semáforo amarillo hasta N días', 'descripcion' => ''],
        'cartera.bucket_3_dias' => ['grupo' => 'cartera', 'tipo' => 'int', 'valor' => 90, 'etiqueta' => 'Semáforo rojo hasta N días', 'descripcion' => 'Después de esto: crítico'],
        'cartera.match_dias_tolerancia' => ['grupo' => 'cartera', 'tipo' => 'int', 'valor' => 3, 'etiqueta' => 'Tolerancia días para match bancario', 'descripcion' => 'Fecha del pago ± N días'],
        'cartera.match_monto_tolerancia' => ['grupo' => 'cartera', 'tipo' => 'float', 'valor' => '0', 'etiqueta' => 'Tolerancia monto para match bancario (COP)', 'descripcion' => '0 = exacto'],

        // ============ DROPI ============
        'dropi.dias_retorno_mercancia' => ['grupo' => 'dropi', 'tipo' => 'int', 'valor' => 20, 'etiqueta' => 'Días máx. mercancía en tránsito antes de sospechosa', 'descripcion' => 'Al superar: alertar Aracely y marcar sospechoso'],
        'dropi.dias_tolerancia_devolucion' => ['grupo' => 'dropi', 'tipo' => 'int', 'valor' => 7, 'etiqueta' => 'Días tolerancia devolución física post-marca', 'descripcion' => 'Si Dropi marca devuelto pero no llega en N días → mercancía fantasma'],
        'dropi.auto_nota_credito' => ['grupo' => 'dropi', 'tipo' => 'bool', 'valor' => '1', 'etiqueta' => 'Auto-generar Nota Crédito al devolver', 'descripcion' => 'Al marcar DropiPedido como Devuelto emite NC'],
        'dropi.auto_nc_envia_siigo' => ['grupo' => 'dropi', 'tipo' => 'bool', 'valor' => '1', 'etiqueta' => 'Auto-enviar NC a SIIGO', 'descripcion' => ''],

        // ============ CATÁLOGO ============
        'catalogo.preservar_codigo_china' => ['grupo' => 'catalogo', 'tipo' => 'bool', 'valor' => '1', 'etiqueta' => 'Preservar consecutivo China en re-import', 'descripcion' => 'Si el SKU existe reusa el código de barras; no lo pisa'],
        'catalogo.bloquear_cambio_codigo' => ['grupo' => 'catalogo', 'tipo' => 'bool', 'valor' => '1', 'etiqueta' => 'Bloquear cambio manual de código de barras', 'descripcion' => 'Evita duplicados accidentales'],

        // ============ CONTABILIDAD · cuentas por defecto ============
        'contable.cta_cxc_default' => ['grupo' => 'contable', 'tipo' => 'string', 'valor' => '1305', 'etiqueta' => 'Cuenta CxC por defecto (contrapartida)', 'descripcion' => 'PUC 1305 · usada como contrapartida al reversar ventas'],
        'contable.cta_ingreso_default' => ['grupo' => 'contable', 'tipo' => 'string', 'valor' => '4135', 'etiqueta' => 'Cuenta ingreso por defecto', 'descripcion' => 'Cta contable usada cuando el producto no la define (PUC Col: 4135)'],
        'contable.cta_iva_venta_default' => ['grupo' => 'contable', 'tipo' => 'string', 'valor' => '2408', 'etiqueta' => 'Cuenta IVA venta por defecto', 'descripcion' => ''],
        'contable.cta_costo_default' => ['grupo' => 'contable', 'tipo' => 'string', 'valor' => '6135', 'etiqueta' => 'Cuenta costo por defecto', 'descripcion' => ''],
        'contable.cta_inventario_default' => ['grupo' => 'contable', 'tipo' => 'string', 'valor' => '1435', 'etiqueta' => 'Cuenta inventario por defecto', 'descripcion' => ''],
        'contable.cta_devolucion_default' => ['grupo' => 'contable', 'tipo' => 'string', 'valor' => '4175', 'etiqueta' => 'Cuenta devolución en ventas por defecto', 'descripcion' => ''],
        'contable.cta_descuento_default' => ['grupo' => 'contable', 'tipo' => 'string', 'valor' => '5305', 'etiqueta' => 'Cuenta descuento comercial por defecto', 'descripcion' => ''],
        'contable.centro_costo_default' => ['grupo' => 'contable', 'tipo' => 'string', 'valor' => 'GB-01', 'etiqueta' => 'Centro de costo por defecto', 'descripcion' => 'Cuenta / centro de imputación por defecto para asientos'],

        // ============ CRM · segmentación ============
        'crm.vip_min_ventas' => ['grupo' => 'crm', 'tipo' => 'float', 'valor' => '5000000', 'etiqueta' => 'Ventas anuales mínimas para VIP (COP)', 'descripcion' => ''],
        'crm.dormido_dias' => ['grupo' => 'crm', 'tipo' => 'int', 'valor' => 90, 'etiqueta' => 'Días sin compra para marcar Dormido', 'descripcion' => ''],
        'crm.riesgo_min_moras' => ['grupo' => 'crm', 'tipo' => 'int', 'valor' => 2, 'etiqueta' => 'Moras mínimas para marcar Riesgo', 'descripcion' => ''],
        'crm.comision_porcentaje' => ['grupo' => 'crm', 'tipo' => 'float', 'valor' => '3', 'etiqueta' => 'Comisión vendedor por defecto (%)', 'descripcion' => 'Se puede sobrescribir por vendedor'],
    ];

    /** Memoization por-request para evitar N lecturas de cache en un mismo handler. */
    private static ?array $requestCache = null;

    public static function get(string $clave, mixed $default = null): mixed
    {
        if (self::$requestCache === null) {
            self::$requestCache = ReglaNegocio::todas();
        }
        if (array_key_exists($clave, self::$requestCache)) return self::$requestCache[$clave];

        // Fallback: retornar el default con el TIPO NATIVO PHP que espera el consumer
        // (paridad con ReglaNegocio::valorCasteado — Sec F8).
        $meta = self::DEFAULTS[$clave] ?? null;
        $raw = $default ?? ($meta['valor'] ?? null);
        if ($meta === null || $raw === null) return $raw;
        return match ($meta['tipo']) {
            'int' => (int) $raw,
            'float' => (float) $raw,
            'bool' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'json' => is_string($raw) ? json_decode($raw, true) : $raw,
            default => (string) $raw,
        };
    }

    public static function limpiarRequestCache(): void
    {
        self::$requestCache = null;
    }

    /**
     * Casteo estricto según DEFAULTS. Retorna [ok:bool, valor:mixed, error?:string].
     * Rechaza inputs inválidos (evita min_foto_bytes="false" → 0).
     */
    public static function castear(string $clave, mixed $input): array
    {
        $meta = self::DEFAULTS[$clave] ?? null;
        if (! $meta) return [false, null, "Clave desconocida"];

        switch ($meta['tipo']) {
            case 'int':
                if (is_int($input)) return [true, $input, null];
                if (is_string($input) && preg_match('/^-?\d+$/', trim($input))) return [true, (int) trim($input), null];
                if (is_numeric($input)) return [true, (int) $input, null];
                return [false, null, "Debe ser un número entero"];
            case 'float':
                if (is_float($input) || is_int($input)) return [true, (float) $input, null];
                if (is_string($input)) {
                    $s = trim(str_replace(',', '.', $input));
                    if (is_numeric($s)) return [true, (float) $s, null];
                }
                return [false, null, "Debe ser un número"];
            case 'bool':
                if (is_bool($input)) return [true, $input ? '1' : '0', null];
                if (in_array($input, [1, '1', 'true', 'on', 'yes'], true)) return [true, '1', null];
                if (in_array($input, [0, '0', 'false', 'off', 'no', '', null], true)) return [true, '0', null];
                return [false, null, "Debe ser sí/no"];
            case 'json':
                if (is_array($input) || is_object($input)) return [true, json_encode($input, JSON_UNESCAPED_UNICODE), null];
                if (is_string($input) && json_decode($input) !== null) return [true, $input, null];
                return [false, null, "Debe ser JSON válido"];
            case 'string':
            default:
                if (is_scalar($input) || $input === null) return [true, (string) ($input ?? ''), null];
                return [false, null, "Debe ser texto"];
        }
    }

    /**
     * Guarda una regla con validación estricta. Lanza InvalidArgumentException si:
     * - la clave no está en DEFAULTS
     * - el valor no pasa el cast del tipo
     */
    public static function set(string $clave, mixed $valor): void
    {
        $meta = self::DEFAULTS[$clave] ?? null;
        if (! $meta) throw new \InvalidArgumentException("Regla desconocida: {$clave}");

        [$ok, $val, $err] = self::castear($clave, $valor);
        if (! $ok) throw new \InvalidArgumentException("Regla {$clave}: {$err}");

        ReglaNegocio::updateOrCreate(
            ['clave' => $clave],
            [
                'grupo' => $meta['grupo'],
                'tipo' => $meta['tipo'],
                'etiqueta' => $meta['etiqueta'],
                'descripcion' => $meta['descripcion'] ?? '',
                'valor' => $val,
            ]
        );
        self::limpiarRequestCache();
    }

    /** Siembra en BD los defaults que aún no existen. Idempotente + concurrent-safe (firstOrCreate). */
    public static function seedDefaults(): int
    {
        $existentes = ReglaNegocio::pluck('clave')->flip(); // 1 query
        $creadas = 0;
        foreach (self::DEFAULTS as $clave => $meta) {
            if ($existentes->has($clave)) continue;
            // firstOrCreate para blindar race entre requests concurrentes.
            $r = ReglaNegocio::firstOrCreate(
                ['clave' => $clave],
                [
                    'grupo' => $meta['grupo'],
                    'tipo' => $meta['tipo'],
                    'etiqueta' => $meta['etiqueta'],
                    'descripcion' => $meta['descripcion'] ?? '',
                    'valor' => (string) $meta['valor'],
                ]
            );
            if ($r->wasRecentlyCreated) $creadas++;
        }
        return $creadas;
    }

    /** Devuelve todas las reglas agrupadas para la UI de admin. */
    public static function porGrupo(): array
    {
        $rows = ReglaNegocio::orderBy('grupo')->orderBy('clave')->get();
        return $rows->groupBy('grupo')->map(fn ($g) => $g->map(fn ($r) => [
            'id' => $r->id,
            'clave' => $r->clave,
            'tipo' => $r->tipo,
            'valor' => $r->valorCasteado(),
            'etiqueta' => $r->etiqueta,
            'descripcion' => $r->descripcion,
        ])->values()->all())->all();
    }
}
