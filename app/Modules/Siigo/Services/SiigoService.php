<?php

namespace App\Modules\Siigo\Services;

use App\Models\Contacto;
use App\Modules\Dropi\Enums\CategoriaUbicacion;
use App\Modules\Dropi\Models\InventarioUbicacion;
use App\Modules\Dropi\Models\Producto;
use App\Modules\Siigo\Clients\SiigoClient;
use App\Modules\Siigo\Models\SiigoCatalogo;
use App\Modules\Siigo\Models\SiigoConfig;
use App\Modules\Siigo\Models\SiigoSyncLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Capa de negocio SIIGO — sincronización bidireccional.
 *
 * Recursos que sincroniza:
 *   - Catálogos base (document-types, taxes, payment-types)
 *   - Warehouses / bodegas → mapea a inventario_ubicaciones
 *   - Products → mapea a productos (con siigo_id + siigo_code)
 *   - Customers → mapea a contactos
 *   - Stock (via warehouses[] en cada producto)
 *
 * Cada operación queda en siigo_sync_log con métricas.
 */
class SiigoService
{
    public function __construct(private readonly SiigoClient $client) {}

    /** Sincroniza catálogos base + warehouses + price-lists + account-groups */
    public function sincronizarCatalogos(): array
    {
        $inicio = microtime(true);
        $mapa = [
            'document-types' => '/v1/document-types',
            'taxes' => '/v1/taxes',
            'payment-types' => '/v1/payment-types',
            'warehouses' => '/v1/warehouses',
            'account-groups' => '/v1/account-groups',
            'price-lists' => '/v1/price-lists',
        ];

        $resumen = [];
        foreach ($mapa as $tipo => $path) {
            try {
                $response = $this->client->request('GET', $path);
                if ($response->failed()) { $resumen[$tipo] = 0; continue; }
                $items = $response->json();
                if (! is_array($items)) { $resumen[$tipo] = 0; continue; }
                $resumen[$tipo] = $this->guardarCatalogo($tipo, $items);
            } catch (\Throwable $e) {
                Log::channel('siigo')->error('Error sincronizando catálogo', ['tipo' => $tipo, 'error' => $e->getMessage()]);
                $resumen[$tipo] = 0;
            }
        }

        // Mapear warehouses → inventario_ubicaciones
        $this->mapearWarehouses();

        // UBIC-4 · extraer de los document-types las resoluciones de FACTURA
        // VENTA (type=FV) a su propio catálogo. El modal de Ubicaciones lo
        // lee para poblar el dropdown "Resolución DIAN para facturar".
        $resumen['resolutions-fv'] = $this->extraerResolucionesFV();

        // Propagar los siigo_id a las tablas locales (impuestos, categorias,
        // listas_precios) para que los selectores del form y el PayloadBuilder
        // tengan matching directo sin depender de nombres/porcentajes.
        $resumen['propagados'] = $this->propagarSiigoIdsALocales();

        SiigoConfig::current()->forceFill(['sync_catalogos_at' => now()])->save();

        // Limpiar caches de PayloadBuilder para que los nuevos matchings apliquen de inmediato.
        Cache::forget('siigo:catalog:account-groups');
        Cache::forget('siigo:catalog:taxes-normalized');

        $this->log('catalogos', 'exitoso', array_sum(array_diff_key($resumen, ['propagados' => 0])), 0, 0, $inicio, 'Catálogos SIIGO sincronizados', $resumen);

        return $resumen;
    }

    /**
     * Propaga los `siigo_id` que SIIGO expone a las tablas locales del ERP
     * (impuestos, categorias, listas_precios). Al tener el id directo, el
     * PayloadBuilder deja de depender de matching por nombre/%.
     *
     * Matching:
     *   - taxes: por porcentaje (tolerancia 0.01).
     *   - account-groups: por nombre case-insensitive, trim.
     *   - price-lists: por nombre case-insensitive.
     *
     * @return array{taxes:int, account_groups:int, price_lists:int}
     */
    private function propagarSiigoIdsALocales(): array
    {
        $out = ['taxes' => 0, 'account_groups' => 0, 'price_lists' => 0];

        // ━━ TAXES ━━ impuestos.siigo_id ↔ SIIGO tax.id (match por %).
        if (\Schema::hasColumn('impuestos', 'siigo_id')) {
            $siigoTaxes = SiigoCatalogo::where('tipo', 'taxes')->get(['codigo', 'payload']);
            foreach (\DB::table('impuestos')->whereNotNull('porcentaje')->get(['id', 'porcentaje']) as $local) {
                $target = (float) $local->porcentaje;
                foreach ($siigoTaxes as $st) {
                    $payload = $st->payload ?? [];
                    $pct = isset($payload['percentage']) ? (float) $payload['percentage']
                        : (isset($payload['rate']) ? (float) $payload['rate'] : null);
                    if ($pct !== null && abs($pct - $target) < 0.01) {
                        \DB::table('impuestos')->where('id', $local->id)
                            ->update(['siigo_id' => (int) $st->codigo]);
                        $out['taxes']++;
                        break;
                    }
                }
            }
        }

        // ━━ ACCOUNT-GROUPS ━━ categorias.siigo_account_group_id ↔ SIIGO account_group.id (match por nombre).
        if (\Schema::hasTable('categorias') && \Schema::hasColumn('categorias', 'siigo_account_group_id')) {
            $siigoAg = SiigoCatalogo::where('tipo', 'account-groups')->get(['codigo', 'nombre']);
            $mapa = $siigoAg->mapWithKeys(fn ($c) => [mb_strtolower(trim($c->nombre)) => (int) $c->codigo]);
            foreach (\DB::table('categorias')->get(['id', 'nombre']) as $local) {
                $key = mb_strtolower(trim((string) $local->nombre));
                if (isset($mapa[$key])) {
                    \DB::table('categorias')->where('id', $local->id)
                        ->update(['siigo_account_group_id' => $mapa[$key]]);
                    $out['account_groups']++;
                }
            }
        }

        // ━━ PRICE-LISTS ━━ listas_precios.siigo_id ↔ SIIGO price-list.id (match por nombre).
        if (\Schema::hasTable('listas_precios') && \Schema::hasColumn('listas_precios', 'siigo_id')) {
            $siigoPl = SiigoCatalogo::where('tipo', 'price-lists')->get(['codigo', 'nombre']);
            $mapa = $siigoPl->mapWithKeys(fn ($c) => [mb_strtolower(trim($c->nombre)) => (int) $c->codigo]);
            foreach (\DB::table('listas_precios')->get(['id', 'nombre']) as $local) {
                $key = mb_strtolower(trim((string) $local->nombre));
                if (isset($mapa[$key])) {
                    \DB::table('listas_precios')->where('id', $local->id)
                        ->update(['siigo_id' => $mapa[$key]]);
                    $out['price_lists']++;
                }
            }
        }

        return $out;
    }

    /**
     * Sincroniza productos desde SIIGO (paginado).
     * Cada producto SIIGO se mapea a un Producto local — la referencia es siigo_code.
     * Si el producto tiene variantes internas del catálogo GB, se preservan (no borra variantes locales).
     *
     * @param  int          $paginaInicial
     * @param  int          $tamPagina  SIIGO cap es 25 para /v1/products · pedir
     *                                   más devuelve 25 y hace que `count===tam` sea
     *                                   false y el cursor marque completo tras 1 pág.
     * @param  string|null  $updatedStart  Filtro incremental yyyy-MM-dd — si viene,
     *                                     solo pide productos actualizados desde esa fecha
     *                                     (respeta rate limit para sync cada 15 min).
     */
    public function sincronizarProductos(int $paginaInicial = 1, int $tamPagina = 25, ?string $updatedStart = null): array
    {
        $inicio = microtime(true);
        $nuevos = 0; $actualizados = 0; $errores = 0;
        $pagina = $paginaInicial;
        $totalProcesados = 0;
        $paginacionCompleta = false;  // X3 · flag para no envenenar el cursor
        $capAlcanzado = false;

        do {
            try {
                $params = [
                    'page' => $pagina,
                    'page_size' => $tamPagina,
                ];
                if ($updatedStart) {
                    $params['updated_start'] = $updatedStart;
                }
                $response = $this->client->request('GET', '/v1/products', $params);

                if ($response->failed()) break;

                $data = $response->json();
                $items = $data['results'] ?? [];
                if (empty($items)) { $paginacionCompleta = true; break; }

                // B3-P3 · batch-fetch de todos los `siigo_id` de la página en 1 sola
                // query (antes: 1 SELECT por item = 100 queries/página · con 5 páginas
                // = 500 queries innecesarias). El `keyBy` deja lookup O(1).
                $siigoIds = array_column($items, 'id');
                $existentes = Producto::whereIn('siigo_id', $siigoIds)
                    ->get()
                    ->keyBy('siigo_id');

                foreach ($items as $p) {
                    try {
                        $creado = $this->guardarProducto($p, $existentes[$p['id']] ?? null);
                        $creado ? $nuevos++ : $actualizados++;
                        $totalProcesados++;
                    } catch (\Throwable $e) {
                        $errores++;
                        Log::channel('siigo')->error('Error guardando producto SIIGO', [
                            'code' => $p['code'] ?? '?', 'error' => $e->getMessage(),
                        ]);
                    }
                }

                $pagina++;
                $hayMas = count($items) === $tamPagina;
                if (! $hayMas) $paginacionCompleta = true;
            } catch (\Throwable $e) {
                Log::channel('siigo')->error('Error paginando productos SIIGO', ['error' => $e->getMessage()]);
                break;
            }
        } while ($hayMas && $pagina < 500);  // límite defensivo

        if ($pagina >= 500) {
            $capAlcanzado = true;
            Log::channel('siigo')->warning('Cap 500 páginas alcanzado en sync productos · algunos productos pueden no haberse traído', [
                'pagina' => $pagina, 'total' => $totalProcesados,
            ]);
        }

        // X3 · solo mover el cursor si (a) no hubo errores y (b) la paginación
        // terminó limpia. Si algo se rompió, el próximo tick reintenta desde
        // el mismo cursor (no perdemos datos).
        if ($errores === 0 && $paginacionCompleta && ! $capAlcanzado) {
            SiigoConfig::current()->forceFill([
                'sync_productos_at' => now(),
                'sync_stock_at' => now(),
            ])->save();
        }

        $r = [
            'nuevos' => $nuevos, 'actualizados' => $actualizados, 'errores' => $errores,
            'total' => $totalProcesados, 'cursor_avanzado' => $errores === 0 && $paginacionCompleta && ! $capAlcanzado,
        ];
        $this->log('productos', $errores === 0 ? 'exitoso' : 'parcial', $nuevos, $actualizados, $errores, $inicio,
            "Sync productos: {$nuevos} nuevos + {$actualizados} actualizados + {$errores} errores", $r);

        return $r;
    }

    /**
     * Reconciliación completa de productos: PULL SIIGO → ERP + detección de zombies.
     *
     * Cubre las 4 acciones SIIGO→ERP del CRUD bidireccional:
     *   1. CREAR  · productos nuevos en SIIGO que el ERP no tenía → se crean localmente.
     *   2. EDITAR · productos ya linkeados que cambiaron en SIIGO → se actualizan localmente.
     *   3. LINKEAR · productos locales sin siigo_id pero con `referencia = code SIIGO` → se linkean.
     *   4. ELIMINAR · productos locales con siigo_id que SIIGO ya no tiene → soft delete ERP.
     *
     * Pensado para correr cada 5 min como red de seguridad (cron) + bajo demanda
     * desde el botón "Reconciliar ahora" del panel. Soft delete siempre; nunca
     * hard delete por auto-sync para preservar histórico contable.
     *
     * @return array{nuevos:int, actualizados:int, linkeados:int, zombies:int, errores:int, total:int}
     */
    public function reconciliarProductos(int $tamPagina = 25): array
    {
        $inicio = microtime(true);
        $nuevos = 0; $actualizados = 0; $linkeados = 0; $errores = 0;
        $siigoIdsVistos = [];
        $pagina = 1;
        $linkeadosAntes = Producto::whereNull('siigo_id')->count();

        do {
            try {
                $response = $this->client->request('GET', '/v1/products', [
                    'page' => $pagina, 'page_size' => $tamPagina,
                ]);
                if ($response->failed()) { $errores++; break; }

                $data = $response->json();
                $items = $data['results'] ?? [];
                if (empty($items)) break;

                $pageIds = array_column($items, 'id');
                $siigoIdsVistos = array_merge($siigoIdsVistos, $pageIds);

                $existentes = Producto::whereIn('siigo_id', $pageIds)
                    ->get()->keyBy('siigo_id');

                foreach ($items as $p) {
                    try {
                        $creado = $this->guardarProducto($p, $existentes[$p['id']] ?? null);
                        $creado ? $nuevos++ : $actualizados++;
                    } catch (\Throwable $e) {
                        $errores++;
                        Log::channel('siigo')->error('reconciliar · guardarProducto', [
                            'code' => $p['code'] ?? '?', 'err' => $e->getMessage(),
                        ]);
                    }
                }

                $pagina++;
            } catch (\Throwable $e) {
                $errores++;
                Log::channel('siigo')->error('reconciliar · pagina', ['err' => $e->getMessage()]);
                break;
            }
        } while (count($items) === $tamPagina && $pagina < 500);

        // Zombies · productos locales con siigo_id que SIIGO ya no tiene.
        // Solo marcamos si descargamos al menos 1 página sin errores (sino, un
        // error de red hace soft-delete masivo accidentalmente).
        $zombies = 0;
        if ($errores === 0 && ! empty($siigoIdsVistos)) {
            $huerfanos = Producto::whereNotNull('siigo_id')
                ->where('activo', true)
                ->whereNotIn('siigo_id', $siigoIdsVistos)
                ->get();
            foreach ($huerfanos as $z) {
                Producto::withoutEvents(function () use ($z) {
                    $z->forceFill([
                        'activo' => false,
                        'siigo_sync_at' => now(),
                    ])->save();
                    $z->delete();  // soft delete · preserva histórico
                });
                $zombies++;
            }
        }

        // Linkeados = diferencia de productos sin siigo_id antes vs ahora.
        $linkeadosDespues = Producto::whereNull('siigo_id')->count();
        $linkeados = max(0, $linkeadosAntes - $linkeadosDespues - $nuevos);

        $r = compact('nuevos', 'actualizados', 'linkeados', 'zombies', 'errores')
            + ['total' => count($siigoIdsVistos)];

        $this->log('productos', $errores === 0 ? 'exitoso' : 'parcial',
            $nuevos, $actualizados + $linkeados + $zombies, $errores, $inicio,
            "Reconciliar: +{$nuevos} nuevos · {$actualizados} actualizados · {$linkeados} linkeados · {$zombies} marcados eliminados · {$errores} errores",
            $r);

        return $r;
    }

    /** Sincroniza clientes/terceros desde SIIGO */
    public function sincronizarClientes(int $paginaInicial = 1, int $tamPagina = 100): array
    {
        $inicio = microtime(true);
        $nuevos = 0; $actualizados = 0; $errores = 0;
        $pagina = $paginaInicial;

        do {
            try {
                $response = $this->client->request('GET', '/v1/customers', [
                    'page' => $pagina, 'page_size' => $tamPagina,
                ]);
                if ($response->failed()) break;

                $data = $response->json();
                $items = $data['results'] ?? [];
                if (empty($items)) break;

                foreach ($items as $c) {
                    try {
                        $creado = $this->guardarCliente($c);
                        $creado ? $nuevos++ : $actualizados++;
                    } catch (\Throwable $e) {
                        $errores++;
                        Log::channel('siigo')->error('Error guardando cliente SIIGO', ['id' => $c['id'] ?? '?', 'error' => $e->getMessage()]);
                    }
                }

                $pagina++;
                $hayMas = count($items) === $tamPagina;
            } catch (\Throwable $e) {
                break;
            }
        } while ($hayMas && $pagina < 200);

        SiigoConfig::current()->forceFill(['sync_clientes_at' => now()])->save();

        $r = ['nuevos' => $nuevos, 'actualizados' => $actualizados, 'errores' => $errores];
        $this->log('customers', $errores === 0 ? 'exitoso' : 'parcial', $nuevos, $actualizados, $errores, $inicio,
            "Sync clientes: {$nuevos} nuevos + {$actualizados} actualizados", $r);

        return $r;
    }

    /** @param array<int, array<string, mixed>> $items */
    private function guardarCatalogo(string $tipo, array $items): int
    {
        return DB::transaction(function () use ($tipo, $items): int {
            SiigoCatalogo::where('tipo', $tipo)->delete();

            $total = 0;
            foreach ($items as $item) {
                if (! is_array($item)) continue;
                $codigo = (string) ($item['id'] ?? $item['code'] ?? $item['name'] ?? '');
                if ($codigo === '') continue;

                SiigoCatalogo::updateOrCreate(
                    ['tipo' => $tipo, 'codigo' => $codigo],
                    ['nombre' => (string) ($item['name'] ?? $item['description'] ?? $codigo), 'payload' => $item],
                );
                $total++;
            }
            return $total;
        });
    }

    /**
     * UBIC-4 · De los document-types ya guardados, se filtran los `type == 'FV'`
     * (factura venta) y se re-guardan en `siigo_catalogos.tipo = 'resolutions-fv'`
     * con un payload compacto que es lo único que el modal de Ubicaciones
     * necesita (id/name/prefix/resolution_number/active/electronic).
     *
     * Esto evita que el front tenga que entender la jerarquía de document-types
     * para pintar un simple dropdown "elegí la resolución con la que vas a facturar".
     */
    private function extraerResolucionesFV(): int
    {
        $docTypes = SiigoCatalogo::where('tipo', 'document-types')->get();
        $total = 0;

        return DB::transaction(function () use ($docTypes, &$total): int {
            SiigoCatalogo::where('tipo', 'resolutions-fv')->delete();

            foreach ($docTypes as $dt) {
                $p = $dt->payload ?? [];
                $tipoDoc = (string) ($p['type'] ?? '');
                if (strtoupper($tipoDoc) !== 'FV') continue;
                if (array_key_exists('active', $p) && $p['active'] === false) continue;

                SiigoCatalogo::updateOrCreate(
                    ['tipo' => 'resolutions-fv', 'codigo' => (string) $dt->codigo],
                    [
                        'nombre' => (string) ($p['name'] ?? $dt->nombre),
                        'payload' => [
                            'id' => (int) $dt->codigo,
                            'name' => $p['name'] ?? null,
                            'prefix' => $p['consecutive']['prefix'] ?? ($p['prefix'] ?? null),
                            'resolution_number' => $p['resolution_number'] ?? null,
                            'electronic_type' => $p['electronic_type'] ?? null,
                            'active' => $p['active'] ?? true,
                        ],
                    ]
                );
                $total++;
            }
            return $total;
        });
    }

    /** Mapea warehouses de SIIGO a inventario_ubicaciones (categoria=venta por defecto) */
    private function mapearWarehouses(): void
    {
        $catalogos = SiigoCatalogo::where('tipo', 'warehouses')->get();
        foreach ($catalogos as $c) {
            try {
                $siigoCode = 'SIIGO-' . $c->codigo;
                // Si ya existe una ubicación con ese código (de un sync previo sin siigo_id),
                // solo le fijamos el siigo_id en vez de crear duplicada.
                $existente = InventarioUbicacion::where('codigo', $siigoCode)
                    ->orWhere('siigo_id', $c->codigo)->first();
                if ($existente) {
                    $existente->forceFill([
                        'siigo_id' => $c->codigo,
                        'nombre' => $c->nombre,
                    ])->save();
                    continue;
                }
                InventarioUbicacion::create([
                    'siigo_id' => $c->codigo,
                    'codigo' => $siigoCode,
                    'nombre' => $c->nombre,
                    'categoria' => CategoriaUbicacion::Venta->value,
                    'disponible_para_venta' => true,
                    'activa' => true,
                ]);
            } catch (\Throwable $e) {
                Log::channel('siigo')->warning('mapearWarehouses skip', [
                    'siigo_id' => $c->codigo, 'nombre' => $c->nombre, 'err' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Guarda un producto SIIGO. Retorna true si es nuevo, false si actualizado.
     *
     * X2 · Merge selectivo en updates + withoutEvents:
     *   - En CREATE: se poblan todos los campos (es el primer sync).
     *   - En UPDATE: solo sobrescribimos campos que SIIGO controla como fuente
     *     (nombre, descripcion, activo, categoria, sync_at). NO tocamos
     *     `precio_proveedor` ni `requiere_talla` ni `es_set` — esos son
     *     locales editables por Aracely. Además:
     *   - Comparación `updated_at local > siigo_sync_at`: si Aracely editó
     *     LOCALMENTE después del último sync, saltamos el update completo
     *     para no pisar sus cambios (el PUSH se encargará de subirlos).
     *   - `Producto::withoutEvents()` evita disparar el Observer y el
     *     loop de sync bidireccional (Pull → Observer → Push → Pull …).
     */
    private function guardarProducto(array $p, ?Producto $existente = null): bool
    {
        return Producto::withoutEvents(function () use ($p, $existente): bool {
            // B3-P3 · si el caller lo pre-cargó (batch), lo reutilizamos.
            $existente = $existente ?? Producto::where('siigo_id', $p['id'])->first();

            // Normalizar la referencia que usaremos localmente · si SIIGO no mandó
            // `reference` (viene '' o null), usamos code; si tampoco hay code,
            // fabricamos una con el UUID de SIIGO. Esto evita duplicate entry
            // en el UNIQUE constraint productos.referencia cuando varios productos
            // del sandbox vienen con reference en blanco.
            $refSiigo = isset($p['reference']) && trim((string) $p['reference']) !== ''
                ? trim((string) $p['reference']) : null;
            $referenciaFinal = $refSiigo
                ?? ($p['code'] ?? null)
                ?? ('SIIGO-' . substr((string) ($p['id'] ?? ''), 0, 8));

            // Anti-duplicados RAÍZ · buscamos por CUALQUIER identificador que
            // SIIGO manda (reference, code) contra productos locales sin siigo_id.
            // Si uno coincide, LINKEAMOS en vez de insertar un duplicado.
            if (! $existente) {
                $candidatos = array_values(array_unique(array_filter([
                    $refSiigo,
                    $p['code'] ?? null,
                    $referenciaFinal,
                ], fn ($v) => is_string($v) && $v !== '')));

                foreach ($candidatos as $cand) {
                    $existente = Producto::whereNull('siigo_id')
                        ->where('referencia', $cand)
                        ->first();
                    if ($existente) {
                        $existente->forceFill(['siigo_id' => $p['id']])->save();
                        break;
                    }
                }
            }

            $creado = ! $existente;

            if ($existente) {
                // ¿La copia local fue editada después del último sync?
                if ($existente->updated_at
                    && $existente->siigo_sync_at
                    && $existente->updated_at->gt($existente->siigo_sync_at)
                ) {
                    // Sí → conservamos lo local, solo tocamos sync_at para no
                    // reprocesar esta misma fila en el próximo tick.
                    $existente->forceFill(['siigo_sync_at' => now()])->save();
                    return false;
                }

                // Merge selectivo · campos que SIIGO controla y usamos localmente.
                // forceFill porque siigo_code/sync_at están fuera del $fillable.
                $precioSiigo = (float) ($p['prices'][0]['price_list'][0]['value'] ?? 0);
                $existente->forceFill([
                    'siigo_code' => $p['code'] ?? $existente->siigo_code,
                    'nombre' => $p['name'] ?? $existente->nombre,
                    'descripcion' => $p['description'] ?? $existente->descripcion,
                    'categoria' => $p['account_group']['name'] ?? $existente->categoria,
                    'activo' => (bool) ($p['active'] ?? $existente->activo),
                    // Precio solo si SIIGO devolvió uno válido (>0) · evita blanquear
                    // precios locales cuando el producto en SIIGO no tiene lista.
                    'precio_proveedor' => $precioSiigo > 0 ? $precioSiigo : $existente->precio_proveedor,
                    'siigo_sync_at' => now(),
                ])->save();
                return false;
            }

            // CREATE · primer sync, poblamos todo.
            // Última verificación anti-colisión · si la referenciaFinal YA existe
            // en la BD (ej. otro producto SIIGO ya la ocupó, o un local sin siigo_id
            // que el fallback no vio por race), buscamos una libre con sufijo.
            $refIntento = $referenciaFinal;
            $sufijo = 1;
            while (Producto::where('referencia', $refIntento)->exists()) {
                $refIntento = $referenciaFinal . '-' . substr((string) ($p['id'] ?? ''), 0, 6) . ($sufijo > 1 ? "-{$sufijo}" : '');
                $sufijo++;
                if ($sufijo > 5) {
                    $refIntento = 'SIIGO-' . substr((string) ($p['id'] ?? ''), 0, 12);
                    break;
                }
            }

            (new Producto())->forceFill([
                'siigo_id' => $p['id'],
                'siigo_code' => $p['code'] ?? null,
                'referencia' => $refIntento,
                'nombre' => $p['name'] ?? 'Producto sin nombre',
                'descripcion' => $p['description'] ?? null,
                'categoria' => $p['account_group']['name'] ?? null,
                'precio_proveedor' => (float) ($p['prices'][0]['price_list'][0]['value'] ?? 0),
                'activo' => (bool) ($p['active'] ?? true),
                'requiere_talla' => false,
                'es_set' => false,
                'siigo_sync_at' => now(),
            ])->save();
            return true;
        });
    }

    /** Guarda un cliente SIIGO. Retorna true si es nuevo. */
    private function guardarCliente(array $c): bool
    {
        $identificacion = $c['identification'] ?? '';
        if ($identificacion === '') return false;

        $existente = Contacto::where('siigo_id', $c['id'])
            ->orWhere('numero_documento', $identificacion)
            ->first();
        $creado = ! $existente;

        $nombre = trim(($c['name'][0] ?? '') . ' ' . ($c['name'][1] ?? '')) ?: ($c['commercial_name'] ?? 'Sin nombre');

        $datos = [
            'siigo_id' => $c['id'],
            'tipo_documento' => $c['id_type']['code'] ?? 'CC',
            'numero_documento' => $identificacion,
            'nombre_completo' => $nombre,
            'razon_social' => $c['commercial_name'] ?? null,
            'email' => $c['contacts'][0]['email'] ?? null,
            'telefono' => $c['phones'][0]['number'] ?? null,
            'direccion' => $c['address']['address'] ?? null,
            'ciudad' => $c['address']['city']['city_name'] ?? null,
            'departamento' => $c['address']['city']['state_name'] ?? null,
            'es_cliente' => true,
            'es_cliente_b2b' => ($c['person_type'] ?? '') === 'Company',
            'activo' => (bool) ($c['active'] ?? true),
            'siigo_sync_at' => now(),
        ];

        if ($existente) {
            $existente->fill($datos)->save();
        } else {
            Contacto::create($datos);
        }

        return $creado;
    }

    private function log(string $recurso, string $estado, int $nuevos, int $act, int $err, float $inicio, string $msg, array $detalle = []): void
    {
        SiigoSyncLog::create([
            'recurso' => $recurso, 'estado' => $estado,
            'nuevos' => $nuevos, 'actualizados' => $act, 'errores' => $err,
            'duracion_ms' => (int) ((microtime(true) - $inicio) * 1000),
            'mensaje' => $msg, 'detalle' => $detalle,
            'user_id' => auth()->id(),
        ]);
    }
}
