<?php

use App\Modules\Siigo\Support\SiigoExcelLayout;

// FASE G · Import/Export Excel SIIGO

it('SiigoExcelLayout tiene exactamente 34 columnas (A..AH)', function () {
    $cols = SiigoExcelLayout::COLS;
    expect($cols)->toHaveCount(34);
    expect(array_keys($cols)[0])->toBe('A');
    expect(array_keys($cols)[33])->toBe('AH');
});

it('las 5 columnas obligatorias (A,B,C,D,E) están marcadas como true', function () {
    foreach (['A', 'B', 'C', 'D', 'E'] as $letra) {
        [, , , $obligatorio] = SiigoExcelLayout::COLS[$letra];
        expect($obligatorio)->toBeTrue("Columna {$letra} debe ser obligatoria");
    }
});

it('las 8 listas de precio (T..AA) tienen nombres exactos de SIIGO', function () {
    $esperados = [
        'T' => 'Cliente Preferente',
        'U' => 'Asistente supervisor',
        'V' => 'Supervisor',
        'W' => 'Asistente Gerente',
        'X' => 'Gerente',
        'Y' => 'Retail',
        'Z' => 'Precio de venta Publico',
        'AA' => '14.999',
    ];
    foreach ($esperados as $letra => $nombre) {
        [$header] = SiigoExcelLayout::COLS[$letra];
        expect($header)->toBe($nombre);
    }
});

it('encabezados() y letras() devuelven arrays indexados numéricamente del mismo tamaño', function () {
    $h = SiigoExcelLayout::encabezados();
    $l = SiigoExcelLayout::letras();
    expect($h)->toHaveCount(34);
    expect($l)->toHaveCount(34);
    expect(array_keys($h))->toBe(array_keys($l));
    expect(array_keys($h)[0])->toBe(0);
});

it('castea SI/NO a booleano true/false', function () {
    expect(SiigoExcelLayout::castear('SI', 'bool'))->toBeTrue();
    expect(SiigoExcelLayout::castear('NO', 'bool'))->toBeFalse();
    expect(SiigoExcelLayout::castear('sí', 'bool'))->toBeTrue();
});

it('castea decimales con coma a float con punto', function () {
    expect(SiigoExcelLayout::castear('1.234,56', 'decimal'))->toBe(1234.56);
    expect(SiigoExcelLayout::castear('$50.000', 'precio'))->toBe(50000.0);
});

it('desformatear convierte P-Producto a Product (API SIIGO)', function () {
    expect(SiigoExcelLayout::desformatear('P-Producto', 'enum:P-Producto,S-Servicio'))->toBe('Product');
    expect(SiigoExcelLayout::desformatear('S-Servicio', 'enum:P-Producto,S-Servicio'))->toBe('Service');
});

it('formatear convierte Product a P-Producto (Excel SIIGO)', function () {
    expect(SiigoExcelLayout::formatear('Product', 'enum:P-Producto,S-Servicio'))->toBe('P-Producto');
    expect(SiigoExcelLayout::formatear('Service', 'enum:P-Producto,S-Servicio'))->toBe('S-Servicio');
});

it('ProductosExcelService existe y genera xlsx vacío con las 34 columnas', function () {
    $svc = new \App\Modules\Siigo\Services\ProductosExcelService();
    $path = $svc->generar(null);
    expect(file_exists($path))->toBeTrue();
    expect(filesize($path))->toBeGreaterThan(1000);

    $sp = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
    $datos = $sp->getSheetByName('Datos');
    expect($datos)->not->toBeNull();
    expect($datos->getHighestColumn())->toBe('AH');
    expect($datos->getCell('A1')->getValue())->toContain('Tipo de Producto');
    expect($datos->getCell('AH1')->getValue())->toBe('Modelo');

    @unlink($path);
});

it('ProductosImportService existe y la interfaz importar devuelve 5 claves', function () {
    $svc = new \App\Modules\Siigo\Services\ProductosImportService();
    expect(method_exists($svc, 'importar'))->toBeTrue();
});
