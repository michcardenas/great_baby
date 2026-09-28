<?php

use App\Modules\Dropi\Models\Producto;
use App\Modules\Siigo\Jobs\PushProductoASiigo;
use App\Modules\Siigo\Observers\ProductoObserver;
use Illuminate\Support\Facades\Queue;

// B-TESTS · Observer sin BD · usamos Producto::make + wasChanged simulado
// via reflexión ligera. Verificamos la lógica de dispatch (o no) por campo.

beforeEach(function () {
    Queue::fake();
    config()->set('siigo.push_auto', true);
    $this->observer = new ProductoObserver();
});

it('created dispatcha crear', function () {
    $p = productoConSiigoId(null);
    $this->observer->created($p);
    Queue::assertPushed(PushProductoASiigo::class, function ($job) {
        return $job->accion === 'crear' && $job->manual === false;
    });
});

it('deleted sin siigo_id NO dispatcha nada', function () {
    $p = productoConSiigoId(null);
    $this->observer->deleted($p);
    Queue::assertNotPushed(PushProductoASiigo::class);
});

it('deleted con siigo_id dispatcha desactivar con siigoId al Job (para hard delete)', function () {
    $p = productoConSiigoId(siigoId: 987);
    $this->observer->deleted($p);
    Queue::assertPushed(PushProductoASiigo::class, function ($job) {
        return $job->accion === 'desactivar'
            && $job->manual === true       // dispatchManual → bypass kill-switch
            && $job->siigoId === 987;      // C3 · siigoId viaja
    });
});

it('restored dispatcha actualizar', function () {
    $p = productoConSiigoId(siigoId: 100);
    $this->observer->restored($p);
    Queue::assertPushed(PushProductoASiigo::class, function ($job) {
        return $job->accion === 'actualizar' && $job->manual === false;
    });
});

/**
 * Helper · Producto con siigo_id opcional. Sin BD.
 */
function productoConSiigoId(?int $siigoId = null): Producto {
    $p = new Producto();
    $p->id = 1;
    $p->referencia = 'REF-TEST';
    $p->nombre = 'Test';
    $p->activo = true;
    $p->siigo_id = $siigoId;
    return $p;
}
