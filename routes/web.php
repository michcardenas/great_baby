<?php

use App\Http\Controllers\Cartera\EstadoCuentaController;
use App\Http\Controllers\Cartera\FacturaPdfController;
use App\Http\Controllers\Cartera\PlantillaContactosController;
use App\Http\Controllers\Catalogo\CodigoBarrasController;
use App\Http\Controllers\Catalogo\EtiquetasMasivasController;
use App\Http\Controllers\Compras\ImportacionPdfController;
use App\Http\Controllers\Compras\ImportOCController;
use App\Http\Controllers\Compras\OrdenCompraPdfController;
use App\Http\Controllers\Compras\PlantillaImportOCController;
use App\Http\Controllers\Compras\RecepcionPdfController;
use App\Http\Controllers\Dropi\EscanerController;
use App\Http\Controllers\Dropi\ManifiestoController;
use App\Http\Controllers\Dropi\PlantillaImportProductosController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/admin'));

// Fallback para middleware auth que redirige a route('login') → mandamos al login de Filament
Route::get('/login', fn () => redirect('/admin/login'))->name('login');

// Fix Livewire assets con hash bajo PHP built-in server (dev)
Route::get('/livewire-{hash}/livewire.js', function () {
    return response()->file(public_path('vendor/livewire/livewire.js'), ['Content-Type' => 'application/javascript']);
})->where('hash', '.*');
Route::get('/livewire-{hash}/livewire.min.js.map', function () {
    return response()->file(public_path('vendor/livewire/livewire.js.map'), ['Content-Type' => 'application/json']);
})->where('hash', '.*');

// Auto-login DEV — SOLO local + APP_DEBUG=true (evita "prod con APP_ENV=local mal copiado = admin gratis").
// En caso de duda, borralo. Nunca dejar activo en producción.
if (app()->environment('local') && config('app.debug') === true) {
    Route::get('/dev-login', function () {
        \Illuminate\Support\Facades\Auth::loginUsingId(1);
        return redirect('/admin');
    });
}

// Modo TV: pantalla de bodega (fullscreen, auto-refresh). REQUIERE auth ahora.
Route::get('/tv/empaque', [\App\Http\Controllers\Dropi\ModoTvController::class, 'empaque'])
    ->middleware(['web', 'auth'])
    ->name('tv.empaque');

// Foto de empaque (privada): sólo Aracely / Alistador con sesión.
Route::get('/empaques/foto/{registro}', [\App\Http\Controllers\Dropi\EmpaqueFotoController::class, 'ver'])
    ->middleware(['web', 'auth'])
    ->name('empaques.foto');

// Portal público: descarga de factura por token — rate-limit contra abuso.
Route::get('/factura/publica/{token}', [FacturaPdfController::class, 'publica'])
    ->middleware(['web', 'throttle:20,1'])
    ->where('token', '[A-Za-z0-9]+')
    ->name('cartera.factura.publica');

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/dropi/manifiesto/{corte}', [ManifiestoController::class, 'descargar'])
        ->name('dropi.manifiesto.descargar');

    Route::get('/dropi/plantilla/productos', [PlantillaImportProductosController::class, 'descargar'])
        ->name('dropi.plantilla.productos');

    Route::post('/dropi/escaner/analizar', [EscanerController::class, 'analizar'])->name('dropi.escaner.analizar');
    Route::post('/dropi/escaner/despachar', [EscanerController::class, 'despachar'])->name('dropi.escaner.despachar');

    Route::get('/cartera/estado-cuenta/{contacto}', [EstadoCuentaController::class, 'pdf'])
        ->name('cartera.estado-cuenta');
    Route::get('/cartera/plantilla/contactos', [PlantillaContactosController::class, 'descargar'])
        ->name('cartera.plantilla.contactos');
    Route::get('/cartera/factura/{factura}/pdf', [FacturaPdfController::class, 'pdf'])
        ->name('cartera.factura.pdf');

    Route::get('/catalogo/variante/{variante}/qr', [CodigoBarrasController::class, 'qr'])->name('catalogo.variante.qr');
    Route::get('/catalogo/variante/{variante}/barcode', [CodigoBarrasController::class, 'barcode'])->name('catalogo.variante.barcode');
    Route::get('/catalogo/variante/{variante}/etiqueta', [CodigoBarrasController::class, 'etiqueta'])->name('catalogo.variante.etiqueta');
    Route::get('/catalogo/etiquetas-lote', [EtiquetasMasivasController::class, 'pdf'])->name('catalogo.etiquetas.lote');

    // Compras: sólo Aracely/Gerencia (fix auditor #19 — antes cualquier autenticado veía costos de proveedor)
    Route::middleware(['auth', \App\Http\Middleware\SoloAracely::class])->group(function () {
        Route::get('/compras/orden/{orden}/pdf', [OrdenCompraPdfController::class, 'pdf'])->name('compras.orden.pdf');
        Route::get('/compras/recepcion/{recepcion}/pdf', [RecepcionPdfController::class, 'pdf'])->name('compras.recepcion.pdf');
        Route::get('/compras/importacion/{importacion}/pdf', [ImportacionPdfController::class, 'pdf'])->name('compras.importacion.pdf');
        Route::get('/compras/plantilla/oc', [PlantillaImportOCController::class, 'descargar'])->name('compras.plantilla.oc');
        Route::post('/compras/import/oc', [ImportOCController::class, 'importar'])->name('compras.import.oc');
    });
});
