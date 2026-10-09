<?php

use App\Modules\Cartera\Models\FacturaVenta;
use App\Modules\Cartera\Models\MovimientoContable;
use App\Modules\Dropi\Enums\CategoriaUbicacion;
use App\Modules\Dropi\Models\InventarioMovimiento;
use App\Modules\Dropi\Models\InventarioUbicacion;
use App\Modules\Dropi\Models\Producto;
use App\Modules\Dropi\Models\ProductoVariante;
use App\Modules\Inventario\Actions\CerrarTomaFisica;
use App\Modules\Inventario\Actions\EjecutarTraslado;
use App\Modules\Inventario\Actions\LiberarReserva;
use App\Modules\Inventario\Actions\PrepararTomaFisica;
use App\Modules\Inventario\Actions\ReservarStock;
use App\Modules\Inventario\Actions\VerificarAlertasStock;
use App\Modules\Inventario\Enums\EstadoTomaFisica;
use App\Modules\Inventario\Enums\EstadoTraslado;
use App\Modules\Inventario\Models\AlertaStockConfig;
use App\Modules\Inventario\Models\AlertaStockDisparada;
use App\Modules\Inventario\Models\ReservaInventario;
use App\Modules\Inventario\Models\TomaFisica;
use App\Modules\Inventario\Models\Traslado;
use App\Modules\Inventario\Models\TrasladoItem;
use App\Modules\Inventario\Services\StockService;

beforeEach(function () {
    /*
     * Varias acciones de inventario (traslados, conteos) exigen un usuario con
     * permiso: se llaman siempre desde una pantalla con sesión, nunca desde un
     * job. Las pruebas corrían sin autenticar y fallaban con «No autenticado»,
     * que no era lo que querían probar.
     */
    $this->seed(\Database\Seeders\PermisosSeeder::class);
    $rol = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Aracely', 'guard_name' => 'web']);
    $this->operador = \App\Models\User::factory()->create();
    $this->operador->assignRole($rol);
    $this->actingAs($this->operador);

    $this->bodegaA = InventarioUbicacion::create([
        'codigo' => 'BOD-A', 'nombre' => 'Bodega A',
        'categoria' => CategoriaUbicacion::Venta, 'activa' => true,
    ]);
    $this->bodegaB = InventarioUbicacion::create([
        'codigo' => 'BOD-B', 'nombre' => 'Bodega B',
        'categoria' => CategoriaUbicacion::Venta, 'activa' => true,
    ]);
    $this->producto = Producto::create([
        'referencia' => 'INV-01', 'nombre' => 'Chupón silicona', 'precio_proveedor' => 3000, 'activo' => true,
    ]);
    $this->variante = ProductoVariante::create([
        'producto_id' => $this->producto->id,
        'talla' => 'U', 'color_codigo' => 'AZ', 'color_nombre' => 'Azul',
    ]);

    // Seed 100 unidades en bodega A
    InventarioMovimiento::create([
        'variante_id' => $this->variante->id,
        'ubicacion_id' => $this->bodegaA->id,
        'tipo' => 'ingreso',
        'cantidad' => 100,
        'created_at' => now(),
    ]);
});

it('calcula saldo físico y disponible', function () {
    $stock = app(StockService::class);

    expect($stock->saldoFisico($this->variante->id, $this->bodegaA->id))->toBe(100)
        ->and($stock->saldoDisponible($this->variante->id, $this->bodegaA->id))->toBe(100);
});

it('reserva stock y baja el disponible', function () {
    $contacto = \App\Models\Contacto::create([
        'tipo_documento' => 'CC', 'numero_documento' => '12345', 'nombre_completo' => 'Cliente T', 'es_cliente' => true,
    ]);
    // La factura tiene que cuadrar (total = subtotal - descuento + impuestos):
    // el modelo lo valida y sin `subtotal` reventaba por «desbalanceada»,
    // tapando lo que la prueba realmente quería verificar.
    $factura = FacturaVenta::create([
        'numero' => 'FV-TEST-' . uniqid(), 'contacto_id' => $contacto->id, 'fecha_emision' => now(),
        'fecha_vencimiento' => now()->addDays(30),
        'subtotal' => 100000, 'total' => 100000, 'saldo' => 100000,
    ]);

    $reserva = ReservarStock::run(
        varianteId: $this->variante->id,
        ubicacionId: $this->bodegaA->id,
        cantidad: 30,
        origen: $factura,
    );

    $stock = app(StockService::class);
    expect($reserva->cantidad)->toBe(30)
        ->and($stock->saldoFisico($this->variante->id, $this->bodegaA->id))->toBe(100)
        ->and($stock->saldoReservado($this->variante->id, $this->bodegaA->id))->toBe(30)
        ->and($stock->saldoDisponible($this->variante->id, $this->bodegaA->id))->toBe(70);

    LiberarReserva::run($factura);
    expect($stock->saldoDisponible($this->variante->id, $this->bodegaA->id))->toBe(100);
});

it('rechaza reserva si no hay stock disponible', function () {
    $contacto = \App\Models\Contacto::create([
        'tipo_documento' => 'CC', 'numero_documento' => '12345', 'nombre_completo' => 'Cliente T', 'es_cliente' => true,
    ]);
    $factura = FacturaVenta::create([
        'numero' => 'FV-TEST-' . uniqid(), 'contacto_id' => $contacto->id, 'fecha_emision' => now(),
        'fecha_vencimiento' => now()->addDays(30),
        'subtotal' => 100000, 'total' => 100000, 'saldo' => 100000,
    ]);

    ReservarStock::run($this->variante->id, $this->bodegaA->id, 150, $factura);
})->throws(InvalidArgumentException::class, 'Stock insuficiente');

it('ejecuta traslado con dos patas y actualiza saldos', function () {
    $traslado = Traslado::create([
        'numero' => Traslado::siguienteNumero(),
        'origen_id' => $this->bodegaA->id,
        'destino_id' => $this->bodegaB->id,
        'fecha_solicitud' => now(),
        'estado' => EstadoTraslado::Borrador,
    ]);
    TrasladoItem::create([
        'traslado_id' => $traslado->id,
        'variante_id' => $this->variante->id,
        'cantidad_solicitada' => 40,
    ]);

    EjecutarTraslado::run($traslado);

    $stock = app(StockService::class);
    expect($stock->saldoFisico($this->variante->id, $this->bodegaA->id))->toBe(60)
        ->and($stock->saldoFisico($this->variante->id, $this->bodegaB->id))->toBe(40)
        ->and($traslado->fresh()->estado)->toBe(EstadoTraslado::Recibido);

    // Debe haber 2 movimientos referenciando el traslado
    expect(InventarioMovimiento::where('referencia_tipo', Traslado::class)
        ->where('referencia_id', $traslado->id)->count())->toBe(2);
});

it('rechaza traslado si stock origen insuficiente', function () {
    $traslado = Traslado::create([
        'numero' => Traslado::siguienteNumero(),
        'origen_id' => $this->bodegaA->id, 'destino_id' => $this->bodegaB->id,
        'fecha_solicitud' => now(), 'estado' => EstadoTraslado::Borrador,
    ]);
    TrasladoItem::create([
        'traslado_id' => $traslado->id,
        'variante_id' => $this->variante->id,
        'cantidad_solicitada' => 500,
    ]);

    EjecutarTraslado::run($traslado);
})->throws(InvalidArgumentException::class, 'Stock insuficiente');

it('cierra toma física con diferencias y genera asientos', function () {
    // `estado` explícito: la base tiene default 'borrador', pero el modelo
    // recién creado todavía no lo leyó y en memoria queda nulo. El código real
    // trae la toma con findOrFail, así que esto reproduce esa situación.
    $toma = TomaFisica::create([
        'numero' => TomaFisica::siguienteNumero(),
        'ubicacion_id' => $this->bodegaA->id,
        'fecha_conteo' => now(),
        'estado' => EstadoTomaFisica::Borrador,
    ]);
    PrepararTomaFisica::run($toma);

    $item = $toma->fresh()->items->firstWhere('variante_id', $this->variante->id);
    // Fix auditor #6: costo_unit debe poblarse desde precio_proveedor (3000), no null.
    // Las cantidades vienen con cast `decimal`, así que Eloquent las devuelve
    // como texto ('100.0000'): se comparan por valor, no por tipo.
    expect((float) $item->saldo_sistema)->toBe(100.0)
        ->and((float) $item->costo_unit)->toBe(3000.0);

    // Faltante: contado 90 (dif -10)
    $item->cantidad_contada = 90;
    $item->save();

    CerrarTomaFisica::run($toma->fresh());

    $toma = $toma->fresh();
    expect($toma->estado)->toBe(EstadoTomaFisica::Ajustada)
        ->and($toma->items_diferentes)->toBe(1)
        ->and((float) $toma->valor_ajuste)->toBe(-30000.0);

    // Kardex debe reflejar el ajuste
    // BUG-INV · tipo unificado con la whitelist del InventarioMovimientoObserver.
    expect((float) InventarioMovimiento::where('tipo', 'ajuste_toma_fisica')
        ->where('variante_id', $this->variante->id)->sum('cantidad'))->toBe(-10.0);

    // Asientos del faltante: 5299 debe 30k / 1435 haber 30k.
    // La prueba esperaba 5195, que es «transportes y fletes» y no tiene nada
    // que ver con una baja de inventario; el código ya se había corregido a
    // 5299 y la prueba se quedó con la cuenta vieja.
    $movs = MovimientoContable::where('origen_type', TomaFisica::class)->get();
    expect($movs)->toHaveCount(2)
        ->and((float) $movs->firstWhere('cuenta_puc', '5299')->debe)->toBe(30000.0)
        ->and((float) $movs->firstWhere('cuenta_puc', '1435')->haber)->toBe(30000.0);
});

it('dispara alertas cuando el saldo cruza el mínimo', function () {
    AlertaStockConfig::create([
        'variante_id' => $this->variante->id,
        'ubicacion_id' => $this->bodegaA->id,
        'stock_minimo' => 50,
        'punto_reorden' => 30,
        'cantidad_reorden' => 100,
        'activa' => true,
    ]);

    // `run()` devuelve un resumen ['disparadas','resueltas','procesadas'],
    // no un entero: la prueba comparaba el arreglo completo contra un número.
    expect(VerificarAlertasStock::run()['disparadas'])->toBe(0);

    // Bajamos a 40
    InventarioMovimiento::create([
        'variante_id' => $this->variante->id, 'ubicacion_id' => $this->bodegaA->id,
        'tipo' => 'egreso', 'cantidad' => -60, 'created_at' => now(),
    ]);

    $disparadas = VerificarAlertasStock::run()['disparadas'];
    expect($disparadas)->toBe(1)
        ->and(AlertaStockDisparada::where('tipo', 'minimo')->where('resuelta', false)->count())->toBe(1);

    // Correr de nuevo NO duplica
    expect(VerificarAlertasStock::run()['disparadas'])->toBe(0);

    // Baja a 20 → dispara reorden también
    InventarioMovimiento::create([
        'variante_id' => $this->variante->id, 'ubicacion_id' => $this->bodegaA->id,
        'tipo' => 'egreso', 'cantidad' => -20, 'created_at' => now(),
    ]);
    expect(VerificarAlertasStock::run()['disparadas'])->toBe(1)
        ->and(AlertaStockDisparada::where('tipo', 'reorden')->where('resuelta', false)->count())->toBe(1);
});
