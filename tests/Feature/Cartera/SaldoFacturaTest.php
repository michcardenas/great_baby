<?php

/**
 * El saldo de una factura es lo que el cliente todavía debe.
 *
 * De ahí salen el semáforo de cartera, la antigüedad, el cupo de crédito y el
 * gate que retiene pedidos por mora. Si el saldo miente, el ERP retiene a quien
 * está al día o le fía a quien no. Hasta hoy Cartera no tenía un solo test, así
 * que `recalcular()` —que es quien decide todo eso— no estaba protegido.
 *
 * Lo que se verifica acá:
 *   · un pago baja el saldo y mueve el estado a abonada/pagada;
 *   · una nota crédito activa también lo baja;
 *   · una nota crédito rechazada o anulada NO lo baja;
 *   · el saldo nunca queda en negativo por sobrepago;
 *   · los estados que no son de cartera (borrador, anulada) NO se pisan, que es
 *     lo que impide que un pago mal cargado marque «pagada» una factura que ni
 *     siquiera se emitió;
 *   · recalcular dos veces da lo mismo (es idempotente).
 */

use App\Modules\Cartera\Enums\EstadoFactura;
use App\Modules\Cartera\Models\FacturaVenta;
use App\Modules\Cartera\Models\NotaCredito;
use App\Modules\Cartera\Models\PagoVenta;
use App\Models\Contacto;

beforeEach(function () {
    // Los observers encolan el push a SIIGO en cuanto nace una factura, una NC
    // o un pago. Acá se está probando el saldo, no la integración: sin esto los
    // jobs corren en línea y revientan pidiendo credenciales o el id de SIIGO
    // de una factura que en el test nunca se emitió.
    Illuminate\Support\Facades\Queue::fake();

    $this->cliente = Contacto::create([
        'numero_documento' => '900123456',
        'nombre_completo' => 'Distribuidora de Prueba SAS',
    ]);
});

/** Factura pendiente de $1.000.000 que vence dentro de 30 días. */
function facturaDe(Contacto $cliente, float $total = 1000000, array $extra = []): FacturaVenta
{
    return FacturaVenta::create(array_merge([
        'numero' => 'FV-TEST-'.fake()->unique()->numberBetween(1000, 999999),
        'contacto_id' => $cliente->id,
        'fecha_emision' => now()->toDateString(),
        'fecha_vencimiento' => now()->addDays(30)->toDateString(),
        'subtotal' => $total,
        'total' => $total,
        'saldo' => $total,
        'estado' => EstadoFactura::Pendiente,
    ], $extra));
}

function notaCreditoDe(FacturaVenta $f, float $valor, string $estado = 'emitida'): NotaCredito
{
    return NotaCredito::create([
        'factura_id' => $f->id,
        'prefijo' => 'NC',
        'numero' => fake()->unique()->numberBetween(1, 999999),
        'motivo' => 'Devolución parcial de mercancía',
        'valor' => $valor,
        'estado' => $estado,
    ]);
}

it('baja el saldo y marca abonada cuando entra un pago parcial', function () {
    $f = facturaDe($this->cliente, 1000000);

    PagoVenta::create([
        'factura_id' => $f->id,
        'contacto_id' => $this->cliente->id,
        'fecha' => now()->toDateString(),
        'monto_recibido' => 400000,
        'monto_aplicado' => 400000,
    ]);

    $f->refresh();
    expect((float) $f->saldo)->toBe(600000.0)
        ->and($f->estado)->toBe(EstadoFactura::Abonada);
});

it('marca pagada cuando el pago cubre el total', function () {
    $f = facturaDe($this->cliente, 1000000);

    PagoVenta::create([
        'factura_id' => $f->id,
        'contacto_id' => $this->cliente->id,
        'fecha' => now()->toDateString(),
        'monto_recibido' => 1000000,
        'monto_aplicado' => 1000000,
    ]);

    $f->refresh();
    expect((float) $f->saldo)->toBe(0.0)
        ->and($f->estado)->toBe(EstadoFactura::Pagada);
});

it('una nota crédito activa reduce el saldo', function () {
    $f = facturaDe($this->cliente, 1000000);
    notaCreditoDe($f, 250000);

    $f->recalcular();

    expect((float) $f->fresh()->saldo)->toBe(750000.0);
});

it('una nota crédito rechazada o anulada no toca el saldo', function (string $estado) {
    $f = facturaDe($this->cliente, 1000000);
    notaCreditoDe($f, 250000, $estado);

    $f->recalcular();

    expect((float) $f->fresh()->saldo)->toBe(1000000.0);
})->with(['rechazada', 'anulada']);

it('suma pagos y notas crédito en el mismo saldo', function () {
    $f = facturaDe($this->cliente, 1000000);
    notaCreditoDe($f, 200000);
    PagoVenta::create([
        'factura_id' => $f->id,
        'contacto_id' => $this->cliente->id,
        'fecha' => now()->toDateString(),
        'monto_recibido' => 300000,
        'monto_aplicado' => 300000,
    ]);

    $f->refresh();
    expect((float) $f->saldo)->toBe(500000.0)
        ->and($f->estado)->toBe(EstadoFactura::Abonada);
});

it('nunca deja el saldo en negativo aunque se pague de más', function () {
    $f = facturaDe($this->cliente, 1000000);
    notaCreditoDe($f, 400000);
    PagoVenta::create([
        'factura_id' => $f->id,
        'contacto_id' => $this->cliente->id,
        'fecha' => now()->toDateString(),
        'monto_recibido' => 900000,
        'monto_aplicado' => 900000,
    ]);

    $f->refresh();
    expect((float) $f->saldo)->toBe(0.0)
        ->and($f->estado)->toBe(EstadoFactura::Pagada);
});

it('marca vencida la factura sin pagos cuyo plazo ya pasó', function () {
    $f = facturaDe($this->cliente, 1000000, [
        'fecha_emision' => now()->subDays(60)->toDateString(),
        'fecha_vencimiento' => now()->subDays(30)->toDateString(),
    ]);

    $f->recalcular();

    expect($f->fresh()->estado)->toBe(EstadoFactura::Vencida);
});

it('no convierte en pagada una factura que ni siquiera se emitió', function () {
    // Un pago mal cargado sobre un borrador no puede «emitirlo» de rebote: el
    // documento no existe para la DIAN y marcarlo pagada lo saca de cartera.
    $f = facturaDe($this->cliente, 1000000, ['estado' => EstadoFactura::Borrador]);

    PagoVenta::create([
        'factura_id' => $f->id,
        'contacto_id' => $this->cliente->id,
        'fecha' => now()->toDateString(),
        'monto_recibido' => 1000000,
        'monto_aplicado' => 1000000,
    ]);

    $f->refresh();
    expect($f->estado)->toBe(EstadoFactura::Borrador)
        ->and((float) $f->saldo)->toBe(1000000.0);
});

it('no revive una factura anulada', function () {
    $f = facturaDe($this->cliente, 1000000, ['estado' => EstadoFactura::Anulada, 'saldo' => 0]);

    PagoVenta::create([
        'factura_id' => $f->id,
        'contacto_id' => $this->cliente->id,
        'fecha' => now()->toDateString(),
        'monto_recibido' => 500000,
        'monto_aplicado' => 500000,
    ]);

    $f->refresh();
    expect($f->estado)->toBe(EstadoFactura::Anulada)
        ->and((float) $f->saldo)->toBe(0.0);
});

it('recalcular dos veces da el mismo resultado', function () {
    $f = facturaDe($this->cliente, 1000000);
    notaCreditoDe($f, 150000);
    PagoVenta::create([
        'factura_id' => $f->id,
        'contacto_id' => $this->cliente->id,
        'fecha' => now()->toDateString(),
        'monto_recibido' => 250000,
        'monto_aplicado' => 250000,
    ]);

    $f->refresh();
    $primero = [(float) $f->saldo, $f->estado];
    $f->recalcular();
    $f->refresh();

    expect([(float) $f->saldo, $f->estado])->toBe($primero);
});

it('al borrar un pago el saldo vuelve a subir', function () {
    $f = facturaDe($this->cliente, 1000000);
    $pago = PagoVenta::create([
        'factura_id' => $f->id,
        'contacto_id' => $this->cliente->id,
        'fecha' => now()->toDateString(),
        'monto_recibido' => 1000000,
        'monto_aplicado' => 1000000,
    ]);
    expect($f->fresh()->estado)->toBe(EstadoFactura::Pagada);

    $pago->delete();

    $f->refresh();
    expect((float) $f->saldo)->toBe(1000000.0)
        ->and($f->estado)->toBe(EstadoFactura::Pendiente);
});

it('la cartera se entera sola cuando se emite una nota crédito', function () {
    // Lo hace `NotaCreditoObserver`. Si eso se cayera, el saldo sólo se
    // corregiría la próxima vez que algo llamara a `recalcular()` —en la
    // práctica, cuando entrara un pago—: mientras tanto cartera cobraría de
    // más, la antigüedad mentiría y el cupo del cliente quedaría consumido por
    // una plata que ya se le devolvió.
    $f = facturaDe($this->cliente, 1000000);

    notaCreditoDe($f, 300000);

    expect((float) $f->fresh()->saldo)->toBe(700000.0);
});

it('anular una nota crédito le devuelve la deuda a la factura', function () {
    $f = facturaDe($this->cliente, 1000000);
    $nc = notaCreditoDe($f, 300000);
    expect((float) $f->fresh()->saldo)->toBe(700000.0);

    $nc->update(['estado' => 'anulada']);

    expect((float) $f->fresh()->saldo)->toBe(1000000.0);
});

it('cambiarle el valor a una nota crédito ajusta el saldo', function () {
    $f = facturaDe($this->cliente, 1000000);
    $nc = notaCreditoDe($f, 300000);

    $nc->update(['valor' => 450000]);

    expect((float) $f->fresh()->saldo)->toBe(550000.0);
});
