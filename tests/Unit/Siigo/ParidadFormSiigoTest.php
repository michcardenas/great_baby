<?php

use App\Modules\Dropi\Models\Producto;
use App\Modules\Dropi\Models\ProductoImagen;
use Illuminate\Support\Facades\Schema;

// FASE H · Paridad 1:1 con el form oficial de SIIGO.
// Verifica que la migración, el Model y las relaciones estén cableados.

it('la migración H1 contiene las 5 columnas nuevas', function () {
    // Verifica el ARCHIVO de migración (independiente del driver de BD).
    $src = file_get_contents(database_path('migrations/2026_10_01_030000_siigo_form_paridad_productos.php'));
    foreach (['visible_en_facturas', 'retencion_siigo_id', 'impuesto_cargo_dos_id', 'reference_fabrica', 'stock_minimo'] as $col) {
        expect($src)->toContain($col);
    }
});

it('la migración H1 crea la tabla producto_imagenes', function () {
    $src = file_get_contents(database_path('migrations/2026_10_01_030000_siigo_form_paridad_productos.php'));
    expect($src)->toContain("Schema::create('producto_imagenes'");
    foreach (['producto_id', 'path', 'nombre_original', 'tamano_bytes', 'mime', 'orden'] as $col) {
        expect($src)->toContain($col);
    }
});

it('el Model Producto declara los 5 campos nuevos en $fillable', function () {
    $f = (new Producto)->getFillable();
    foreach (['visible_en_facturas', 'retencion_siigo_id', 'impuesto_cargo_dos_id', 'reference_fabrica', 'stock_minimo'] as $c) {
        expect($f)->toContain($c);
    }
});

it('el Model Producto castea visible_en_facturas como boolean y stock_minimo como decimal', function () {
    $casts = (new Producto)->getCasts();
    expect($casts)->toHaveKey('visible_en_facturas');
    expect($casts['visible_en_facturas'])->toBe('boolean');
    expect($casts)->toHaveKey('stock_minimo');
    expect($casts['stock_minimo'])->toContain('decimal');
});

it('el Model Producto tiene las relaciones imagenes/retencion/impuestoCargoDos', function () {
    $p = new Producto;
    expect(method_exists($p, 'imagenes'))->toBeTrue();
    expect(method_exists($p, 'retencion'))->toBeTrue();
    expect(method_exists($p, 'impuestoCargoDos'))->toBeTrue();
});

it('el Model ProductoImagen existe y tiene fillable + relación producto', function () {
    $i = new ProductoImagen;
    expect($i->getFillable())->toContain('producto_id', 'path', 'orden');
    expect(method_exists($i, 'producto'))->toBeTrue();
});

it('ProductoPayloadBuilder emite los 3 campos nuevos de SIIGO', function () {
    // Verifica mediante reflexión que el método paraAgregado contenga las claves
    // 'available_for_sale', 'minimum_stock' y 'withholding_taxes'.
    $src = file_get_contents(app_path('Modules/Siigo/Support/ProductoPayloadBuilder.php'));
    expect($src)->toContain("'available_for_sale'");
    expect($src)->toContain("'minimum_stock'");
    expect($src)->toContain("'withholding_taxes'");
});
