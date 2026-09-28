<?php

use App\Modules\Dropi\Models\Producto;
use App\Modules\Siigo\Support\ProductoPayloadBuilder;

// B-TESTS · builder puro · verifica sanitizaciones críticas SIN tocar BD.
// Usamos `Producto::make()` (no persistido) para respetar el type hint estricto.

beforeEach(function () {
    $this->builder = new ProductoPayloadBuilder();
});

// --- sanitizarCode via producto (proxy indirecto porque el método es privado) ---
it('sanitiza code · quita comillas y colapsa espacios a un guion', function () {
    $p = mockeriaProductoBasico(referencia: "REF ' 001  ");
    $payload = $this->builder->paraProducto($p);
    expect($payload['code'])->toBe('REF-001');
});

it('trunca code a 30 caracteres', function () {
    $p = mockeriaProductoBasico(referencia: str_repeat('X', 40));
    $payload = $this->builder->paraProducto($p);
    expect(mb_strlen($payload['code']))->toBeLessThanOrEqual(30);
});

it('lanza InvalidArgumentException si el code queda vacío tras sanitizar', function () {
    $p = mockeriaProductoBasico(referencia: "  '  ");
    $this->builder->paraProducto($p);
})->throws(InvalidArgumentException::class, 'SIIGO code vacío');

// --- limpiarNombre (proxy via paraProducto) ---
it('limpia el nombre y trunca a 100 chars', function () {
    $p = mockeriaProductoBasico(nombre: str_repeat('Nombre largo ', 20));
    $payload = $this->builder->paraProducto($p);
    expect(mb_strlen($payload['name']))->toBeLessThanOrEqual(100);
});

// --- Idempotencia del code (B2-A3 · protege contra duplicado por retry) ---
it('paraProducto es determinístico · misma entrada = mismo payload', function () {
    $p = mockeriaProductoBasico(referencia: 'REF-999');
    $a = $this->builder->paraProducto($p);
    $b = $this->builder->paraProducto($p);
    expect($a['code'])->toBe($b['code']);
    expect($a['name'])->toBe($b['name']);
});

/**
 * Helper · construye un Producto (sin persistir) con lo mínimo que necesita
 * el builder. No usa BD ni relaciones.
 */
function mockeriaProductoBasico(
    string $referencia = 'REF-001',
    ?string $nombre = 'Producto de prueba',
): Producto {
    $p = new Producto();
    $p->id = 1;
    $p->referencia = $referencia;
    $p->nombre = $nombre;
    $p->activo = true;
    $p->stock_control = true;
    $p->precio_proveedor = 0;
    return $p;
}
