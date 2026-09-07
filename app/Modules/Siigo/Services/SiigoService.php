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
     */
    public function sincronizarProductos(int $paginaInicial = 1, int $tamPagina = 100): array
    {
        $inicio = microtime(true);
        $nuevos = 0; $actualizados = 0; $errores = 0;
        $pagina = $paginaInicial;
        $totalProcesados = 0;

        do {
            try {
                $response = $this->client->request('GET', '/v1/products', [
                    'page' => $pagina,
                    'page_size' => $tamPagina,
                ]);

                if ($response->failed()) break;

                $data = $response->json();
                $items = $data['results'] ?? [];
                if (empty($items)) break;

                foreach ($items as $p) {
                    try {
                        $creado = $this->guardarProducto($p);
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
            } catch (\Throwable $e) {
                Log::channel('siigo')->error('Error paginando productos SIIGO', ['error' => $e->getMessage()]);
                break;
            }
        } while ($hayMas && $pagina < 500);  // límite defensivo

        SiigoConfig::current()->forceFill(['sync_productos_at' => now(), 'sync_stock_at' => now()])->save();

        $r = ['nuevos' => $nuevos, 'actualizados' => $actualizados, 'errores' => $errores, 'total' => $totalProcesados];
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

    /** Guarda un producto SIIGO. Retorna true si es nuevo, false si actualizado. */
    private function guardarProducto(array $p): bool
    {
        $existente = Producto::where('siigo_id', $p['id'])->first();
        $creado = ! $existente;

        $datos = [
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
        ];

        if ($existente) {
            $existente->fill($datos)->save();
        } else {
            Producto::create($datos);
        }

        return $creado;
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
