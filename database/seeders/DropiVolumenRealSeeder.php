<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Dropi\Enums\CategoriaUbicacion;
use App\Modules\Dropi\Enums\DestinoDevolucion;
use App\Modules\Dropi\Enums\EstadoCorte;
use App\Modules\Dropi\Enums\EstadoPedidoDropi;
use App\Modules\Dropi\Enums\TipoMovimientoWallet;
use App\Modules\Dropi\Models\DropiCorte;
use App\Modules\Dropi\Models\DropiDevolucion;
use App\Modules\Dropi\Models\DropiEstadoBitacora;
use App\Modules\Dropi\Models\DropiPedido;
use App\Modules\Dropi\Models\DropiPedidoItem;
use App\Modules\Dropi\Models\DropiRemision;
use App\Modules\Dropi\Models\DropiSancion;
use App\Modules\Dropi\Models\DropiWalletMovimiento;
use App\Modules\Dropi\Models\GarantiaTicket;
use App\Modules\Dropi\Models\InventarioMovimiento;
use App\Modules\Dropi\Models\InventarioUbicacion;
use App\Modules\Dropi\Models\Producto;
use App\Modules\Dropi\Models\ProductoVariante;
use Carbon\Carbon;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Volumen real para demo del módulo Dropi:
 * - 20 productos base + 60 variantes
 * - 10 ubicaciones extra + stock inicial
 * - 14 cortes en 7 días con ~180 pedidos
 * - Bitácora, wallet, sanciones, devoluciones, garantías y remisiones ARI
 *
 * Extiende (no reemplaza) DropiCoreSeeder — se llama primero para asegurar la base.
 */
class DropiVolumenRealSeeder extends Seeder
{
    /** @var Generator */
    private $faker;

    private array $ciudades = [
        ['Bogotá', 'Cundinamarca'],
        ['Medellín', 'Antioquia'],
        ['Cali', 'Valle del Cauca'],
        ['Barranquilla', 'Atlántico'],
        ['Bucaramanga', 'Santander'],
        ['Cartagena', 'Bolívar'],
        ['Pereira', 'Risaralda'],
        ['Manizales', 'Caldas'],
        ['Ibagué', 'Tolima'],
        ['Cúcuta', 'Norte de Santander'],
    ];

    private array $transportadoras = ['Servientrega', 'Interrapidisimo', 'Envia', 'Coordinadora'];

    private array $tiendas = [
        'Baby Store Demo', 'Mundo Bebé', 'Pequeñines CO',
        'Tienda Estrella', 'Kidz Corner', 'Bebé Feliz',
    ];

    private array $vendedores = [
        ['Diana Marcela Ospina',  '1030594871', true],
        ['Julián Andrés Torres',  '80234901',   true],
        ['Camila Herrera',        '1020887654', false],
        ['Sebastián Ríos',        '1032475199', false],
        ['Laura Restrepo',        '1112345678', true],
    ];

    public function run(): void
    {
        // 1) Asegurar datos base (roles, admin, ubicaciones y productos del core)
        $this->call(DropiCoreSeeder::class);

        // Auditing desactivado durante la carga masiva (evita 500+ filas en audits)
        DropiPedido::disableAuditing();

        $this->faker = fake('es_CO');

        DB::transaction(function () {
            $admin = User::where('email', 'admin@greatbaby.co')->firstOrFail();

            $this->crearUbicacionesExtra();
            $variantes = $this->crearProductosYVariantes();
            $stockUbicaciones = InventarioUbicacion::where('categoria', CategoriaUbicacion::Venta->value)->get();
            $this->crearStockInicial($variantes, $stockUbicaciones, $admin);

            $cortes = $this->crearCortes();
            [$pedidosPagados, $todosPedidos] = $this->crearPedidos($cortes, $variantes, $admin);

            $this->refrescarContadoresCortes($cortes);

            $this->crearWallet($pedidosPagados);
            $this->crearSanciones($todosPedidos);
            $this->crearDevoluciones($todosPedidos, $admin);
            $this->crearGarantias($todosPedidos, $variantes, $admin);
            $this->crearRemisiones($pedidosPagados);
        });

        DropiPedido::enableAuditing();
    }

    // ---------------------------------------------------------------------
    // Ubicaciones extra (10 adicionales)
    // ---------------------------------------------------------------------
    private function crearUbicacionesExtra(): void
    {
        $extras = [
            ['R-C-01', 'Rack C · Nivel 01', CategoriaUbicacion::Venta],
            ['R-C-02', 'Rack C · Nivel 02', CategoriaUbicacion::Venta],
            ['R-C-03', 'Rack C · Nivel 03', CategoriaUbicacion::Venta],
            ['R-D-01', 'Rack D · Nivel 01', CategoriaUbicacion::Venta],
            ['R-D-02', 'Rack D · Nivel 02', CategoriaUbicacion::Venta],
            ['R-D-03', 'Rack D · Nivel 03', CategoriaUbicacion::Venta],
            ['AVR-03', 'Avería · Por reparar (zona B)', CategoriaUbicacion::AveriaReparar],
            ['AVR-04', 'Avería · Baja total (zona B)', CategoriaUbicacion::AveriaBaja],
            ['GAR-02', 'Reservado garantías (zona B)', CategoriaUbicacion::Garantia],
            ['RES-PROV-02', 'Reserva proveedor Kidzworld', CategoriaUbicacion::ReservaProveedor],
        ];

        foreach ($extras as [$codigo, $nombre, $cat]) {
            InventarioUbicacion::updateOrCreate(
                ['codigo' => $codigo],
                ['nombre' => $nombre, 'categoria' => $cat, 'activa' => true]
            );
        }
    }

    // ---------------------------------------------------------------------
    // Productos + variantes: 20 productos, 60 variantes
    // ---------------------------------------------------------------------
    /** @return \Illuminate\Support\Collection<int, ProductoVariante> */
    private function crearProductosYVariantes()
    {
        $catalogoRopa = [
            ['Body manga corta estampado',   ['ELF', 'elefante']],
            ['Body manga larga bordado',     ['LEÓ', 'león']],
            ['Enterizo osito polar',         ['OSO', 'oso']],
            ['Enterizo con pies',            ['CON', 'conejo']],
            ['Pijama dos piezas nube',       ['NUB', 'nube']],
            ['Pijama térmica invierno',      ['ZOR', 'zorro']],
            ['Vestido tejido flores',        ['FLR', 'flores']],
            ['Camiseta y short set',         ['DIN', 'dinosaurio']],
            ['Sudadera algodón peinado',     ['EST', 'estrellas']],
            ['Chaqueta corta abrigo',        ['LOB', 'lobo']],
            ['Overol jean stretch',          ['JEA', 'jean']],
            ['Traje bautizo blanco',         ['ANG', 'ángel']],
            ['Body pack x3 unisex',          ['MIX', 'surtido'], true],
            ['Kit ajuar 5 piezas',           ['AJU', 'ajuar'], true],
            ['Set 2 pijamas por unidad',     ['LUN', 'luna'], true],
        ];

        $catalogoAccesorio = [
            ['Chupo pico anatómico',           ['01', 'blanco']],
            ['Termo bebé 260 ml',              ['02', 'azul']],
            ['Set cubiertos silicona',         ['05', 'rosa']],
            ['Babero impermeable',             ['09', 'amarillo']],
            ['Manta polar suave',              ['12', 'gris']],
        ];

        $colores = [
            ['02', 'azul'], ['05', 'rosa'], ['03', 'verde'],
            ['09', 'amarillo'], ['12', 'gris'], ['11', 'crema'],
        ];

        $tallas = ['3M', '6M', '9M', '12M', '18M', '24M'];

        $todas = collect();
        $counterAnd = 2500;
        $counterBab = 4400;

        // 15 productos de ropa
        foreach ($catalogoRopa as $row) {
            $nombre = $row[0];
            [$disCod, $disNom] = $row[1];
            $esSet = $row[2] ?? false;

            $counterAnd += $this->faker->numberBetween(3, 12);
            $sufA = str_pad((string) $this->faker->numberBetween(10, 99), 2, '0', STR_PAD_LEFT);
            $sufB = str_pad((string) $this->faker->numberBetween(100, 999), 3, '0', STR_PAD_LEFT);
            $referencia = 'AND' . $counterAnd . '-' . $sufA . '/' . $sufB;

            $prod = Producto::updateOrCreate(
                ['referencia' => $referencia],
                [
                    'nombre' => $nombre,
                    'categoria' => 'ropa',
                    'precio_proveedor' => $this->faker->numberBetween(8, 85) * 1000,
                    'requiere_talla' => true,
                    'es_set' => $esSet,
                    'activo' => true,
                ]
            );

            // 3 variantes por producto (~45 subtotal)
            $usados = [];
            for ($i = 0; $i < 3; $i++) {
                do {
                    $color = $this->faker->randomElement($colores);
                    $talla = $this->faker->randomElement($tallas);
                    $key = $color[0] . '|' . $talla;
                } while (in_array($key, $usados, true));
                $usados[] = $key;

                $todas->push($this->guardarVariante($prod, $color, [$disCod, $disNom], $talla));
            }
        }

        // 5 productos accesorio (3 variantes = 15) → total 60
        foreach ($catalogoAccesorio as $row) {
            $nombre = $row[0];
            [$colCod, $colNom] = $row[1];

            $counterBab += $this->faker->numberBetween(3, 15);
            $suf = str_pad((string) $this->faker->numberBetween(10, 99), 2, '0', STR_PAD_LEFT);
            $referencia = 'BAB' . $counterBab . '-' . $suf;

            $prod = Producto::updateOrCreate(
                ['referencia' => $referencia],
                [
                    'nombre' => $nombre,
                    'categoria' => 'accesorio',
                    'precio_proveedor' => $this->faker->numberBetween(5, 30) * 1000,
                    'requiere_talla' => false,
                    'es_set' => false,
                    'activo' => true,
                ]
            );

            // 3 variantes de color (sin talla)
            $usados = [$colCod];
            $todas->push($this->guardarVariante($prod, [$colCod, $colNom], [null, null], null));

            for ($i = 0; $i < 2; $i++) {
                do {
                    $color = $this->faker->randomElement($colores);
                } while (in_array($color[0], $usados, true));
                $usados[] = $color[0];
                $todas->push($this->guardarVariante($prod, $color, [null, null], null));
            }
        }

        return $todas;
    }

    private function guardarVariante(Producto $prod, array $color, array $diseno, ?string $talla): ProductoVariante
    {
        $codigo = ProductoVariante::generarCodigoBarras(
            $prod->referencia, $color[0], $diseno[0], $talla
        );

        return ProductoVariante::updateOrCreate(
            ['codigo_barras' => $codigo],
            [
                'producto_id' => $prod->id,
                'color_codigo' => $color[0],
                'color_nombre' => $color[1],
                'diseno_codigo' => $diseno[0],
                'diseno_nombre' => $diseno[1],
                'talla' => $talla,
            ]
        );
    }

    // ---------------------------------------------------------------------
    // Stock inicial: ingreso a racks de venta
    // ---------------------------------------------------------------------
    private function crearStockInicial($variantes, $ubicacionesVenta, User $admin): void
    {
        $rows = [];
        $now = now()->subDays(10);

        foreach ($variantes as $v) {
            // Reparte cada variante en 2 ubicaciones distintas
            $picks = $ubicacionesVenta->random(min(2, $ubicacionesVenta->count()));
            foreach ($picks as $u) {
                $rows[] = [
                    'variante_id' => $v->id,
                    'ubicacion_id' => $u->id,
                    'tipo' => 'ingreso',
                    'cantidad' => $this->faker->numberBetween(15, 60),
                    'referencia_tipo' => 'inventario_fisico',
                    'referencia_id' => null,
                    'user_id' => $admin->id,
                    'notas' => 'Ingreso inicial demo',
                    'created_at' => $now,
                ];
            }
        }

        // Insert directo (modelo sin timestamps automáticos y sin observers relevantes)
        collect($rows)->chunk(200)->each(function ($chunk) {
            InventarioMovimiento::insert($chunk->toArray());
        });
    }

    // ---------------------------------------------------------------------
    // 14 cortes en 7 días (2 por día). Preserva el de hoy #1 del CoreSeeder.
    // ---------------------------------------------------------------------
    /** @return \Illuminate\Support\Collection<int, DropiCorte> */
    private function crearCortes()
    {
        $cortes = collect();
        for ($d = 6; $d >= 0; $d--) {
            $fecha = now()->subDays($d)->startOfDay();
            foreach ([1, 2] as $num) {
                $estado = $this->estadoDeCorte($d, $num);
                $corte = DropiCorte::firstOrCreate(
                    ['fecha' => $fecha->toDateString(), 'numero' => $num],
                    ['estado' => $estado, 'pedidos_totales' => 0]
                );
                // Si ya existía (ej. hoy #1) forzamos su estado si sigue congruente
                if (! $corte->wasRecentlyCreated) {
                    $corte->update(['estado' => $estado]);
                }
                $cortes->push($corte);
            }
        }

        return $cortes;
    }

    private function estadoDeCorte(int $diasAtras, int $numero): EstadoCorte
    {
        if ($diasAtras >= 2) {
            return EstadoCorte::Cerrado;
        }
        if ($diasAtras === 1) {
            return EstadoCorte::Abierto;
        }
        // hoy
        return EstadoCorte::Abierto;
    }

    // ---------------------------------------------------------------------
    // ~180 pedidos con distribución realista + bitácora + items
    // ---------------------------------------------------------------------
    /**
     * @return array{0: \Illuminate\Support\Collection<int, DropiPedido>, 1: \Illuminate\Support\Collection<int, DropiPedido>}
     */
    private function crearPedidos($cortes, $variantes, User $admin): array
    {
        // Distribución para 180 pedidos:
        // 108 pagado, 27 despachado, 14 pendiente_inv, 9 devuelto,
        // 9 cancelado_dropi, 4 entregado, 3 devolucion_en_camino,
        //  3 pending, 3 alistando
        $plantillaEstados = array_merge(
            array_fill(0, 108, EstadoPedidoDropi::Pagado),
            array_fill(0, 27,  EstadoPedidoDropi::Despachado),
            array_fill(0, 14,  EstadoPedidoDropi::PendienteInventario),
            array_fill(0, 9,   EstadoPedidoDropi::Devuelto),
            array_fill(0, 9,   EstadoPedidoDropi::CanceladoDropi),
            array_fill(0, 4,   EstadoPedidoDropi::Entregado),
            array_fill(0, 3,   EstadoPedidoDropi::DevolucionEnCamino),
            array_fill(0, 3,   EstadoPedidoDropi::Pending),
            array_fill(0, 3,   EstadoPedidoDropi::Alistando),
        );
        shuffle($plantillaEstados);

        $ubicVenta = InventarioUbicacion::where('categoria', CategoriaUbicacion::Venta->value)
            ->pluck('id')->all();

        $todos = collect();
        $pagados = collect();
        $guia = 200000;
        $ordenId = 200000;
        $i = 0;
        $totalPedidos = count($plantillaEstados);

        // Reparte pedidos entre cortes proporcionalmente
        $porCorte = intdiv($totalPedidos, $cortes->count());
        $sobrante = $totalPedidos - ($porCorte * $cortes->count());

        foreach ($cortes as $idxCorte => $corte) {
            $cantidad = $porCorte + ($idxCorte < $sobrante ? 1 : 0);
            $fechaBase = Carbon::parse($corte->fecha);

            for ($k = 0; $k < $cantidad && $i < $totalPedidos; $k++, $i++) {
                $estado = $plantillaEstados[$i];
                $guia++;
                $ordenId++;

                $ciudad = $this->faker->randomElement($this->ciudades);
                [$vNom, $vId, $vB2b] = $this->faker->randomElement($this->vendedores);
                $creadoAt = $fechaBase->copy()
                    ->addHours($this->faker->numberBetween(6, 20))
                    ->addMinutes($this->faker->numberBetween(0, 59));

                // Items del pedido (1-3) con variantes distintas
                $numItems = $this->faker->numberBetween(1, 3);
                $variantesPedido = $variantes->random($numItems);
                $itemsData = [];
                $montoProveedor = 0;
                foreach ($variantesPedido as $var) {
                    $cant = $this->faker->numberBetween(1, 2);
                    $precioUnit = (float) $var->producto->precio_proveedor;
                    $montoProveedor += $precioUnit * $cant;
                    $itemsData[] = [
                        'variante' => $var,
                        'cantidad' => $cant,
                        'precio_proveedor_unit' => $precioUnit,
                    ];
                }

                $montoCliente = round($montoProveedor * $this->faker->randomFloat(2, 1.5, 2.0), 0);
                $ganancia = round($montoCliente - $montoProveedor, 0);
                $flete = $this->faker->numberBetween(7500, 15000);

                [$despachadoAt, $entregadoAt, $devueltoAt, $pagadoAt] =
                    $this->fechasCicloVida($estado, $creadoAt);

                $pedido = DropiPedido::create([
                    'corte_id' => $corte->id,
                    'guia' => 'GUI-' . str_pad((string) $guia, 6, '0', STR_PAD_LEFT),
                    'dropi_orden_id' => 'DRP-' . str_pad((string) $ordenId, 6, '0', STR_PAD_LEFT),
                    'transportadora' => $this->faker->randomElement($this->transportadoras),
                    'tienda' => $this->faker->randomElement($this->tiendas),
                    'vendedor_nombre' => $vNom,
                    'vendedor_identificacion' => $vId,
                    'requiere_factura_b2b' => $vB2b,
                    'cliente_nombre' => $this->faker->name(),
                    'cliente_doc' => (string) $this->faker->numberBetween(10_000_000, 1_200_000_000),
                    'cliente_telefono' => '3' . $this->faker->numberBetween(10, 29) . $this->faker->numerify('#######'),
                    'cliente_direccion' => $this->faker->streetAddress(),
                    'cliente_ciudad' => $ciudad[0],
                    'cliente_depto' => $ciudad[1],
                    'estado' => $estado,
                    'despachado_at' => $despachadoAt,
                    'entregado_at' => $entregadoAt,
                    'devuelto_at' => $devueltoAt,
                    'pagado_at' => $pagadoAt,
                    'monto_esperado_proveedor' => $montoProveedor,
                    'monto_cliente_final' => $montoCliente,
                    'ganancia_vendedor' => $ganancia,
                    'flete_transportadora' => $flete,
                    'created_at' => $creadoAt,
                    'updated_at' => $creadoAt,
                ]);

                // Items
                foreach ($itemsData as $it) {
                    DropiPedidoItem::create([
                        'pedido_id' => $pedido->id,
                        'variante_id' => $it['variante']->id,
                        'sku_dropi' => $it['variante']->codigo_barras,
                        'cantidad' => $it['cantidad'],
                        'precio_proveedor_unit' => $it['precio_proveedor_unit'],
                        'ubicacion_asignada_id' => in_array($estado, [
                            EstadoPedidoDropi::Alistando,
                            EstadoPedidoDropi::Empacado,
                            EstadoPedidoDropi::Despachado,
                            EstadoPedidoDropi::Entregado,
                            EstadoPedidoDropi::Pagado,
                            EstadoPedidoDropi::Devuelto,
                            EstadoPedidoDropi::DevolucionEnCamino,
                        ], true) ? $this->faker->randomElement($ubicVenta) : null,
                        'despachado' => in_array($estado, [
                            EstadoPedidoDropi::Despachado,
                            EstadoPedidoDropi::Entregado,
                            EstadoPedidoDropi::Pagado,
                            EstadoPedidoDropi::Devuelto,
                            EstadoPedidoDropi::DevolucionEnCamino,
                        ], true),
                    ]);
                }

                $this->crearBitacora($pedido, $admin, $creadoAt);

                $todos->push($pedido);
                if ($estado === EstadoPedidoDropi::Pagado) {
                    $pagados->push($pedido);
                }
            }
        }

        return [$pagados, $todos];
    }

    /** @return array{0:?Carbon,1:?Carbon,2:?Carbon,3:?Carbon} */
    private function fechasCicloVida(EstadoPedidoDropi $estado, Carbon $creado): array
    {
        $despachado = $entregado = $devuelto = $pagado = null;

        switch ($estado) {
            case EstadoPedidoDropi::Pagado:
                $despachado = $creado->copy()->addHours($this->faker->numberBetween(4, 30));
                $entregado = $despachado->copy()->addDays($this->faker->numberBetween(1, 4));
                $pagado = $entregado->copy()->addDays($this->faker->numberBetween(1, 3));
                break;
            case EstadoPedidoDropi::Entregado:
                $despachado = $creado->copy()->addHours($this->faker->numberBetween(4, 30));
                $entregado = $despachado->copy()->addDays($this->faker->numberBetween(1, 4));
                break;
            case EstadoPedidoDropi::Despachado:
                $despachado = $creado->copy()->addHours($this->faker->numberBetween(4, 30));
                break;
            case EstadoPedidoDropi::Devuelto:
                $despachado = $creado->copy()->addHours($this->faker->numberBetween(4, 30));
                $devuelto = $despachado->copy()->addDays($this->faker->numberBetween(4, 10));
                break;
            case EstadoPedidoDropi::DevolucionEnCamino:
                $despachado = $creado->copy()->addHours($this->faker->numberBetween(4, 30));
                break;
            default:
                // pending, alistando, empacado, pendiente_inventario, cancelado_*
                break;
        }

        return [$despachado, $entregado, $devuelto, $pagado];
    }

    private function crearBitacora(DropiPedido $pedido, User $admin, Carbon $creado): void
    {
        $rows = [];
        $rows[] = ['estado_desde' => null, 'estado_hasta' => EstadoPedidoDropi::Pending->value, 'at' => $creado];

        $flujo = match ($pedido->estado) {
            EstadoPedidoDropi::Pending => [],
            EstadoPedidoDropi::Alistando => [EstadoPedidoDropi::Alistando],
            EstadoPedidoDropi::Empacado => [EstadoPedidoDropi::Alistando, EstadoPedidoDropi::Empacado],
            EstadoPedidoDropi::Despachado => [EstadoPedidoDropi::Alistando, EstadoPedidoDropi::Empacado, EstadoPedidoDropi::Despachado],
            EstadoPedidoDropi::Entregado => [EstadoPedidoDropi::Alistando, EstadoPedidoDropi::Empacado, EstadoPedidoDropi::Despachado, EstadoPedidoDropi::Entregado],
            EstadoPedidoDropi::Pagado => [EstadoPedidoDropi::Alistando, EstadoPedidoDropi::Empacado, EstadoPedidoDropi::Despachado, EstadoPedidoDropi::Entregado, EstadoPedidoDropi::Pagado],
            EstadoPedidoDropi::PendienteInventario => [EstadoPedidoDropi::PendienteInventario],
            EstadoPedidoDropi::DevolucionEnCamino => [EstadoPedidoDropi::Alistando, EstadoPedidoDropi::Empacado, EstadoPedidoDropi::Despachado, EstadoPedidoDropi::DevolucionEnCamino],
            EstadoPedidoDropi::Devuelto => [EstadoPedidoDropi::Alistando, EstadoPedidoDropi::Empacado, EstadoPedidoDropi::Despachado, EstadoPedidoDropi::DevolucionEnCamino, EstadoPedidoDropi::Devuelto],
            EstadoPedidoDropi::CanceladoDropi => [EstadoPedidoDropi::CanceladoDropi],
            EstadoPedidoDropi::CanceladoGb => [EstadoPedidoDropi::CanceladoGb],
        };

        $prev = EstadoPedidoDropi::Pending;
        $at = $creado->copy();
        foreach ($flujo as $siguiente) {
            $at = $at->copy()->addHours($this->faker->numberBetween(2, 26));
            $rows[] = [
                'estado_desde' => $prev->value,
                'estado_hasta' => $siguiente->value,
                'at' => $at,
            ];
            $prev = $siguiente;
        }

        foreach ($rows as $r) {
            DropiEstadoBitacora::insert([
                'pedido_id' => $pedido->id,
                'estado_desde' => $r['estado_desde'],
                'estado_hasta' => $r['estado_hasta'],
                'fuente' => 'sistema',
                'payload' => json_encode(['seed' => true]),
                'user_id' => $admin->id,
                'created_at' => $r['at'],
            ]);
        }
    }

    // ---------------------------------------------------------------------
    // Contadores agregados del corte
    // ---------------------------------------------------------------------
    private function refrescarContadoresCortes($cortes): void
    {
        foreach ($cortes as $corte) {
            $total = DropiPedido::where('corte_id', $corte->id)->count();
            $pendInv = DropiPedido::where('corte_id', $corte->id)
                ->where('estado', EstadoPedidoDropi::PendienteInventario->value)->count();
            $desp = DropiPedido::where('corte_id', $corte->id)
                ->whereIn('estado', [
                    EstadoPedidoDropi::Despachado->value,
                    EstadoPedidoDropi::Entregado->value,
                    EstadoPedidoDropi::Pagado->value,
                    EstadoPedidoDropi::DevolucionEnCamino->value,
                    EstadoPedidoDropi::Devuelto->value,
                ])->count();

            $corte->update([
                'pedidos_totales' => $total,
                'pedidos_pendientes_inv' => $pendInv,
                'pedidos_despachados' => $desp,
            ]);
        }
    }

    // ---------------------------------------------------------------------
    // Wallet Dropi: ~30 movimientos
    // ---------------------------------------------------------------------
    private function crearWallet($pedidosPagados): void
    {
        // 22 pagos de guía (subset de los pagados)
        $muestraPagos = $pedidosPagados->shuffle()->take(min(22, $pedidosPagados->count()));
        foreach ($muestraPagos as $pedido) {
            DropiWalletMovimiento::create([
                'fecha' => $pedido->pagado_at?->toDateString() ?? now()->toDateString(),
                'tipo' => TipoMovimientoWallet::PagoGuia,
                'monto' => $pedido->monto_esperado_proveedor,
                'pedido_id' => $pedido->id,
                'categoria' => 'ingreso_pedido',
                'fuente' => ['guia' => $pedido->guia, 'seed' => true],
            ]);
        }

        // 2 retiros a banco
        for ($i = 0; $i < 2; $i++) {
            DropiWalletMovimiento::create([
                'fecha' => now()->subDays($this->faker->numberBetween(1, 6))->toDateString(),
                'tipo' => TipoMovimientoWallet::RetiroBanco,
                'monto' => -1 * $this->faker->numberBetween(500_000, 2_000_000),
                'pedido_id' => null,
                'categoria' => 'retiro',
                'fuente' => ['banco' => 'Bancolombia', 'seed' => true],
            ]);
        }

        // 3 indemnizaciones (gasto)
        for ($i = 0; $i < 3; $i++) {
            DropiWalletMovimiento::create([
                'fecha' => now()->subDays($this->faker->numberBetween(1, 6))->toDateString(),
                'tipo' => TipoMovimientoWallet::Indemnizacion,
                'monto' => -1 * $this->faker->numberBetween(5_000, 25_000),
                'pedido_id' => null,
                'categoria' => 'indemnizacion_cliente',
                'fuente' => ['motivo' => 'reclamo', 'seed' => true],
            ]);
        }

        // 2 fletes de garantía
        for ($i = 0; $i < 2; $i++) {
            DropiWalletMovimiento::create([
                'fecha' => now()->subDays($this->faker->numberBetween(1, 6))->toDateString(),
                'tipo' => TipoMovimientoWallet::FleteGarantia,
                'monto' => -1 * $this->faker->numberBetween(8_500, 15_000),
                'pedido_id' => null,
                'categoria' => 'flete_reposicion',
                'fuente' => ['seed' => true],
            ]);
        }

        // 1 tarjeta
        DropiWalletMovimiento::create([
            'fecha' => now()->subDays(3)->toDateString(),
            'tipo' => TipoMovimientoWallet::Tarjeta,
            'monto' => -1 * $this->faker->numberBetween(50_000, 200_000),
            'pedido_id' => null,
            'categoria' => 'compra_insumos',
            'fuente' => ['seed' => true],
        ]);
    }

    // ---------------------------------------------------------------------
    // Sanciones: 5 (mezcla)
    // ---------------------------------------------------------------------
    private function crearSanciones($todos): void
    {
        $muestra = $todos->shuffle()->take(5);
        foreach ($muestra as $i => $pedido) {
            $tipo = $i % 2 === 0 ? 'categoria_explicita' : 'diferencia_precio';
            $esperado = (float) $pedido->monto_esperado_proveedor;
            if ($tipo === 'diferencia_precio') {
                $recibido = round($esperado * $this->faker->randomFloat(2, 0.5, 0.9), 0);
            } else {
                // categoría explícita: multa fija de ejemplo
                $recibido = max(0, $esperado - $this->faker->numberBetween(10_000, 40_000));
            }
            DropiSancion::create([
                'pedido_id' => $pedido->id,
                'tipo' => $tipo,
                'monto_esperado' => $esperado,
                'monto_recibido' => $recibido,
                'diferencia' => $esperado - $recibido,
                'detectada_at' => now()->subDays($this->faker->numberBetween(0, 6)),
            ]);
        }
    }

    // ---------------------------------------------------------------------
    // Devoluciones: 3 con distintos destinos
    // ---------------------------------------------------------------------
    private function crearDevoluciones($todos, User $admin): void
    {
        $devueltos = $todos->where('estado', EstadoPedidoDropi::Devuelto)->values();
        if ($devueltos->count() < 3) {
            return;
        }
        $destinos = [
            DestinoDevolucion::Reingreso,
            DestinoDevolucion::AveriaReparar,
            DestinoDevolucion::BajaTotal,
        ];
        foreach ($destinos as $idx => $destino) {
            $pedido = $devueltos[$idx];
            DropiDevolucion::create([
                'pedido_id' => $pedido->id,
                'recibido_at' => $pedido->devuelto_at ?? now()->subDay(),
                'destino_inventario' => $destino->value,
                'decision_por' => $admin->id,
                'genero_nota_credito' => $destino !== DestinoDevolucion::Reingreso,
                'nota_credito_ari_id' => $destino !== DestinoDevolucion::Reingreso
                    ? 'NC-' . str_pad((string) ($idx + 1), 5, '0', STR_PAD_LEFT)
                    : null,
                'notas' => 'Devolución demo · destino ' . $destino->label(),
            ]);
        }
    }

    // ---------------------------------------------------------------------
    // Garantías: 2 tickets en distintos estados
    // ---------------------------------------------------------------------
    private function crearGarantias($todos, $variantes, User $admin): void
    {
        $ubicGarantia = InventarioUbicacion::where('categoria', CategoriaUbicacion::Garantia->value)->first();
        $pedidosOK = $todos->whereIn('estado', [EstadoPedidoDropi::Entregado, EstadoPedidoDropi::Pagado])->values();

        $configs = [
            ['abierto', null, 'Cliente reporta cierre defectuoso — pendiente concepto.'],
            ['reposicion_enviada', $admin->id, 'Aprobado por Aracely; reposición enviada por Servientrega.'],
        ];

        foreach ($configs as $i => [$estado, $decisor, $desc]) {
            $pedido = $pedidosOK->count() > $i ? $pedidosOK[$i] : null;
            $var = $variantes->random();
            $created = now()->subDays($this->faker->numberBetween(1, 5));
            GarantiaTicket::create([
                'pedido_original_id' => $pedido?->id,
                'variante_id' => $var->id,
                'cantidad' => 1,
                'cliente_nombre' => $pedido?->cliente_nombre ?? $this->faker->name(),
                'cliente_telefono' => $pedido?->cliente_telefono ?? '3001234567',
                'descripcion_falla' => $desc,
                'estado' => $estado,
                'creado_por' => $admin->id,
                'decision_por' => $decisor,
                'plazo_concepto_at' => $created->copy()->addHours(72),
                'decision_at' => $decisor ? $created->copy()->addHours(20) : null,
                'ubicacion_reserva_id' => $ubicGarantia?->id,
                'closed_at' => null,
                'created_at' => $created,
                'updated_at' => $created,
            ]);
        }
    }

    // ---------------------------------------------------------------------
    // Remisiones ARI: 6 con consecutivo REM-DP-NNNNNN
    // ---------------------------------------------------------------------
    private function crearRemisiones($pagados): void
    {
        $muestra = $pagados->shuffle()->take(6);
        $consec = 100;
        $loteId = 'ARI-LOTE-' . now()->format('Ymd') . '-01';
        foreach ($muestra as $pedido) {
            $consec++;
            $remision = DropiRemision::create([
                'pedido_id' => $pedido->id,
                'consecutivo' => 'REM-DP-' . str_pad((string) $consec, 6, '0', STR_PAD_LEFT),
                'valor_proveedor' => $pedido->monto_esperado_proveedor,
                'ari_lote_id' => $loteId,
                'ari_factura_id' => 'ARI-F-' . str_pad((string) $consec, 6, '0', STR_PAD_LEFT),
                'enviada_ari_at' => $pedido->pagado_at ?? now()->subDay(),
                'cufe' => strtoupper(bin2hex(random_bytes(20))),
                'estado_dian' => 'aceptado',
            ]);

            $pedido->update([
                'remision_interna_id' => $remision->id,
                'ari_factura_id' => $remision->ari_factura_id,
                'ari_enviado_at' => $remision->enviada_ari_at,
            ]);
        }
    }
}
