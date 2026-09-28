<?php

namespace App\Modules\Siigo\Services;

use App\Models\Contacto;
use App\Modules\Cartera\Enums\CategoriaUbicacion;
use App\Modules\Dropi\Models\InventarioUbicacion;
use App\Modules\Dropi\Models\Producto;
use App\Modules\Siigo\Clients\SiigoClient;
use App\Modules\Siigo\Models\SiigoCatalogo;
use App\Modules\Siigo\Models\SiigoConfig;
use App\Modules\Siigo\Models\SiigoSyncLog;
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

        SiigoConfig::current()->forceFill(['sync_catalogos_at' => now()])->save();

        $this->log('catalogos', 'exitoso', array_sum($resumen), 0, 0, $inicio, 'Catálogos SIIGO sincronizados', $resumen);

        return $resumen;
    }

    /**
     * Sincroniza productos desde SIIGO (paginado).
     * Cada producto SIIGO se mapea a un Producto local — la referencia es siigo_code.
     * Si el producto tiene variantes internas del catálogo GB, se preservan (no borra variantes locales).
     *
     * @param  int          $paginaInicial
     * @param  int          $tamPagina
     * @param  string|null  $updatedStart  Filtro incremental yyyy-MM-dd — si viene,
     *                                     solo pide productos actualizados desde esa fecha
     *                                     (respeta rate limit para sync cada 15 min).
     */
    public function sincronizarProductos(int $paginaInicial = 1, int $tamPagina = 100, ?string $updatedStart = null): array
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

    /** Mapea warehouses de SIIGO a inventario_ubicaciones (categoria=venta por defecto) */
    private function mapearWarehouses(): void
    {
        $catalogos = SiigoCatalogo::where('tipo', 'warehouses')->get();
        foreach ($catalogos as $c) {
            InventarioUbicacion::updateOrCreate(
                ['siigo_id' => $c->codigo],
                [
                    'codigo' => 'SIIGO-' . $c->codigo,
                    'nombre' => $c->nombre,
                    'categoria' => CategoriaUbicacion::Venta->value,
                    'disponible_para_venta' => true,
                    'activa' => true,
                ]
            );
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

                // Merge selectivo · SOLO campos que SIIGO controla.
                $existente->fill([
                    'siigo_code' => $p['code'] ?? $existente->siigo_code,
                    'nombre' => $p['name'] ?? $existente->nombre,
                    'descripcion' => $p['description'] ?? $existente->descripcion,
                    'categoria' => $p['account_group']['name'] ?? $existente->categoria,
                    'activo' => (bool) ($p['active'] ?? $existente->activo),
                    'siigo_sync_at' => now(),
                ])->save();
                return false;
            }

            // CREATE · primer sync, poblamos todo.
            Producto::create([
                'siigo_id' => $p['id'],
                'siigo_code' => $p['code'] ?? null,
                'referencia' => $p['reference'] ?? $p['code'] ?? $p['id'],
                'nombre' => $p['name'] ?? 'Producto sin nombre',
                'descripcion' => $p['description'] ?? null,
                'categoria' => $p['account_group']['name'] ?? null,
                'precio_proveedor' => (float) ($p['prices'][0]['price_list'][0]['value'] ?? 0),
                'activo' => (bool) ($p['active'] ?? true),
                'requiere_talla' => false,
                'es_set' => false,
                'siigo_sync_at' => now(),
            ]);
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
