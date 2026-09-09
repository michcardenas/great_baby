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

Route::get('/', fn () => redirect('/app'));

// ============================================================
// NUEVO STACK · Laravel + Inertia + Vue 3 (bajo /app)
// El panel Filament sigue en /admin durante la migración gradual.
// ============================================================

// Auth (guest)
Route::middleware(['web', 'guest'])->prefix('app')->group(function () {
    Route::get('/login', [\App\Http\Controllers\App\AuthController::class, 'showLogin'])->name('app.login');
    Route::post('/login', [\App\Http\Controllers\App\AuthController::class, 'login'])->middleware('throttle:8,1');
});

// App (auth)
Route::middleware(['web', 'auth'])->prefix('app')->group(function () {
    Route::get('/', \App\Http\Controllers\App\DashboardController::class)->name('app.dashboard');
    // MEJORAS-A · ⌘K búsqueda global
    Route::get('/buscar', \App\Http\Controllers\App\BusquedaGlobalController::class)->name('app.buscar');
    Route::get('/cosas-del-dia', \App\Http\Controllers\App\CosasDelDiaController::class)->name('app.cosas');
    Route::post('/copiloto', [\App\Http\Controllers\App\CopilotoController::class, 'preguntar'])->name('app.copiloto');
    Route::get('/notificaciones', \App\Http\Controllers\App\NotificacionesController::class)->name('app.notificaciones');
    Route::get('/mapa-colombia', \App\Http\Controllers\App\MapaColombiaController::class)->name('app.mapa');
    Route::post('/logout', [\App\Http\Controllers\App\AuthController::class, 'logout'])->name('app.logout');

    // Reglas de Negocio (admin)
    Route::get('/reglas', [\App\Http\Controllers\App\ReglasController::class, 'index'])->name('app.reglas');
    Route::post('/reglas', [\App\Http\Controllers\App\ReglasController::class, 'guardar'])->name('app.reglas.guardar');

    // Cartera · Dashboard
    Route::get('/cartera', \App\Http\Controllers\App\CarteraDashboardController::class)->name('app.cartera.dashboard');

    // Cartera · Facturas
    Route::get('/facturas', [\App\Http\Controllers\App\FacturasController::class, 'index'])->name('app.facturas.index');
    Route::get('/facturas/{factura}', [\App\Http\Controllers\App\FacturasController::class, 'show'])->name('app.facturas.show');

    // Cartera · Contactos
    Route::get('/contactos', [\App\Http\Controllers\App\ContactosController::class, 'index'])->name('app.contactos.index');
    Route::get('/contactos/{contacto}', [\App\Http\Controllers\App\ContactosController::class, 'show'])->name('app.contactos.show');

    // Cartera · Pagos
    Route::get('/pagos', [\App\Http\Controllers\App\PagosController::class, 'index'])->name('app.pagos.index');

    // Cartera · Crédito y cobranza
    Route::get('/credito', [\App\Http\Controllers\App\CreditoController::class, 'index'])->name('app.credito.index');
    // FIL-C · Cartera extras
    Route::get('/cartera/reportes', [\App\Http\Controllers\App\CarteraExtrasController::class, 'reportes'])->name('app.cartera.reportes');
    Route::get('/cartera/cobranzas', [\App\Http\Controllers\App\CarteraExtrasController::class, 'cobranzasIndex'])->name('app.cartera.cobranzas');
    Route::post('/cartera/cobranzas', [\App\Http\Controllers\App\CarteraExtrasController::class, 'cobranzaCrear'])->name('app.cartera.cobranza.crear');
    Route::get('/cartera/solicitudes', [\App\Http\Controllers\App\CarteraExtrasController::class, 'solicitudesIndex'])->name('app.cartera.solicitudes');
    Route::post('/cartera/solicitudes/{solicitud}/resolver', [\App\Http\Controllers\App\CarteraExtrasController::class, 'solicitudResolver'])->name('app.cartera.solicitud.resolver');
    // Re-audit M5 R4 SEG-M4 · throttle en agregados pesados.
    Route::get('/cartera/movimientos', [\App\Http\Controllers\App\CarteraExtrasController::class, 'movimientos'])->middleware('throttle:30,1')->name('app.cartera.movimientos');

    // Compras / Inventario / Contabilidad
    Route::get('/compras', [\App\Http\Controllers\App\ComprasController::class, 'index'])->name('app.compras.index');
    // Re-audit M2 R3 PATRÓN R (SEG-A5) · throttle en agregados/mutaciones.
    Route::get('/compras/reporte', [\App\Http\Controllers\App\ComprasGestionController::class, 'reporte'])->middleware('throttle:30,1')->name('app.compras.reporte');
    Route::get('/compras/oc/nueva', [\App\Http\Controllers\App\ComprasGestionController::class, 'ocForm'])->name('app.compras.oc.nueva');
    Route::post('/compras/oc', [\App\Http\Controllers\App\ComprasGestionController::class, 'ocCrear'])->middleware('throttle:60,1')->name('app.compras.oc.crear');
    Route::get('/compras/oc/{orden}', [\App\Http\Controllers\App\ComprasGestionController::class, 'ocShow'])->name('app.compras.oc.show');
    Route::post('/compras/oc/{orden}/aprobar', [\App\Http\Controllers\App\ComprasGestionController::class, 'ocAprobar'])->middleware('throttle:60,1')->name('app.compras.oc.aprobar');
    Route::post('/compras/oc/{orden}/anular', [\App\Http\Controllers\App\ComprasGestionController::class, 'ocAnular'])->middleware('throttle:20,1')->name('app.compras.oc.anular');
    Route::get('/compras/recepcion/nueva', [\App\Http\Controllers\App\ComprasGestionController::class, 'recepcionForm'])->name('app.compras.recepcion.nueva');
    Route::post('/compras/recepcion', [\App\Http\Controllers\App\ComprasGestionController::class, 'recepcionCrear'])->middleware('throttle:60,1')->name('app.compras.recepcion.crear');
    Route::get('/compras/recepcion/{recepcion}', [\App\Http\Controllers\App\ComprasGestionController::class, 'recepcionShow'])->name('app.compras.recepcion.show');
    Route::get('/compras/importacion/nueva', [\App\Http\Controllers\App\ComprasGestionController::class, 'importacionForm'])->name('app.compras.importacion.nueva');
    Route::get('/compras/importacion/{importacion}', [\App\Http\Controllers\App\ComprasGestionController::class, 'importacionShow'])->name('app.compras.importacion.show');
    Route::post('/compras/importacion', [\App\Http\Controllers\App\ComprasGestionController::class, 'importacionCrear'])->middleware('throttle:60,1')->name('app.compras.importacion.crear');
    Route::post('/compras/importacion/{importacion}/gasto', [\App\Http\Controllers\App\ComprasGestionController::class, 'importacionGastoAgregar'])->middleware('throttle:60,1')->name('app.compras.importacion.gasto');
    Route::post('/compras/importacion/{importacion}/liquidar', [\App\Http\Controllers\App\ComprasGestionController::class, 'importacionLiquidar'])->middleware('throttle:20,1')->name('app.compras.importacion.liquidar');
    Route::get('/inventario', [\App\Http\Controllers\App\InventarioController::class, 'index'])->name('app.inventario.index');
    // Re-audit M3 PATRÓN θ (SEG-A3) · throttle en /app/inventario/* — antes sin
    //   límite; kardex agregaba 500 movs, reporte-stock hacía SUM group by sobre
    //   todo el kardex. Un actor autenticado saturaba la DB con requests.
    Route::get('/inventario/kardex', [\App\Http\Controllers\App\InventarioGestionController::class, 'kardex'])->middleware('throttle:30,1')->name('app.inventario.kardex');
    Route::get('/inventario/reporte-stock', [\App\Http\Controllers\App\InventarioGestionController::class, 'reporteStock'])->middleware('throttle:30,1')->name('app.inventario.reporte');
    Route::get('/inventario/conteos', [\App\Http\Controllers\App\InventarioGestionController::class, 'conteosIndex'])->middleware('throttle:60,1')->name('app.inventario.conteos');
    Route::post('/inventario/conteos', [\App\Http\Controllers\App\InventarioGestionController::class, 'conteoCrear'])->middleware('throttle:30,1')->name('app.inventario.conteo.crear');
    Route::get('/inventario/traslados', [\App\Http\Controllers\App\InventarioGestionController::class, 'trasladosIndex'])->middleware('throttle:60,1')->name('app.inventario.traslados');
    Route::post('/inventario/traslados', [\App\Http\Controllers\App\InventarioGestionController::class, 'trasladoCrear'])->middleware('throttle:30,1')->name('app.inventario.traslado.crear');
    // PATRÓN α · UI operativa traslados (Show + repeater items + state machine).
    Route::get('/inventario/traslados/{id}', [\App\Http\Controllers\App\InventarioGestionController::class, 'trasladoShow'])->middleware('throttle:60,1')->name('app.inventario.traslado.show');
    Route::post('/inventario/traslados/{id}/items', [\App\Http\Controllers\App\InventarioGestionController::class, 'trasladoItemGuardar'])->middleware('throttle:60,1')->name('app.inventario.traslado.item.guardar');
    Route::delete('/inventario/traslados/{id}/items/{itemId}', [\App\Http\Controllers\App\InventarioGestionController::class, 'trasladoItemEliminar'])->middleware('throttle:60,1')->name('app.inventario.traslado.item.eliminar');
    Route::post('/inventario/traslados/{id}/enviar', [\App\Http\Controllers\App\InventarioGestionController::class, 'trasladoEnviar'])->middleware('throttle:30,1')->name('app.inventario.traslado.enviar');
    Route::post('/inventario/traslados/{id}/recibir', [\App\Http\Controllers\App\InventarioGestionController::class, 'trasladoRecibir'])->middleware('throttle:30,1')->name('app.inventario.traslado.recibir');
    Route::post('/inventario/traslados/{id}/anular', [\App\Http\Controllers\App\InventarioGestionController::class, 'trasladoAnular'])->middleware('throttle:30,1')->name('app.inventario.traslado.anular');
    // PATRÓN α · UI captura conteo físico (Show + iniciar + guardar item + cerrar).
    Route::get('/inventario/conteos/{id}', [\App\Http\Controllers\App\InventarioGestionController::class, 'conteoShow'])->middleware('throttle:60,1')->name('app.inventario.conteo.show');
    Route::post('/inventario/conteos/{id}/iniciar', [\App\Http\Controllers\App\InventarioGestionController::class, 'conteoIniciar'])->middleware('throttle:20,1')->name('app.inventario.conteo.iniciar');
    Route::post('/inventario/conteos/{id}/items/{itemId}', [\App\Http\Controllers\App\InventarioGestionController::class, 'conteoItemGuardar'])->middleware('throttle:120,1')->name('app.inventario.conteo.item');
    Route::post('/inventario/conteos/{id}/cerrar', [\App\Http\Controllers\App\InventarioGestionController::class, 'conteoCerrar'])->middleware('throttle:20,1')->name('app.inventario.conteo.cerrar');
    Route::get('/inventario/alertas', [\App\Http\Controllers\App\InventarioGestionController::class, 'alertasIndex'])->middleware('throttle:60,1')->name('app.inventario.alertas');
    Route::post('/inventario/alertas', [\App\Http\Controllers\App\InventarioGestionController::class, 'alertaGuardar'])->middleware('throttle:30,1')->name('app.inventario.alerta.guardar');
    Route::delete('/inventario/alertas/{id}', [\App\Http\Controllers\App\InventarioGestionController::class, 'alertaEliminar'])->middleware('throttle:30,1')->name('app.inventario.alerta.eliminar');
    Route::get('/inventario/buscar-variantes', [\App\Http\Controllers\App\InventarioGestionController::class, 'buscarVariantes'])->middleware('throttle:60,1')->name('app.inventario.buscar.variantes');
    // Re-audit M5 SEG-A1 · throttle:30,1 en agregados pesados. Sin él, un
    // usuario autenticado puede tumbar la DB spammeando reportes con rangos
    // de años. `reportes` (landing estática) queda sin throttle porque no
    // consulta agregados.
    Route::get('/contabilidad', [\App\Http\Controllers\App\ContabilidadController::class, 'index'])->middleware('throttle:30,1')->name('app.contabilidad.index');
    Route::get('/contabilidad/panel', [\App\Http\Controllers\App\ContabilidadExtrasController::class, 'panel'])->middleware('throttle:30,1')->name('app.contabilidad.panel');
    Route::get('/contabilidad/reportes', [\App\Http\Controllers\App\ContabilidadExtrasController::class, 'reportes'])->name('app.contabilidad.reportes');
    Route::get('/contabilidad/reporte-detalle', [\App\Http\Controllers\App\ContabilidadExtrasController::class, 'reporteDetalle'])->middleware('throttle:60,1')->name('app.contabilidad.detalle');

    // Plantillas de documento WYSIWYG (Aracely)
    Route::get('/plantillas', [\App\Http\Controllers\App\PlantillasController::class, 'index'])->name('app.plantillas.index');
    Route::post('/plantillas', [\App\Http\Controllers\App\PlantillasController::class, 'guardar'])->name('app.plantillas.guardar');
    Route::delete('/plantillas/{plantilla}', [\App\Http\Controllers\App\PlantillasController::class, 'eliminar'])->name('app.plantillas.eliminar');
    Route::post('/plantillas/preview', [\App\Http\Controllers\App\PlantillasController::class, 'preview'])
        ->middleware('throttle:30,1')->name('app.plantillas.preview');

    // Dropi / Catálogo / Empresa / SIIGO
    Route::get('/dropi', [\App\Http\Controllers\App\DropiController::class, 'index'])->name('app.dropi.index');
    // Dropi · Operación (DRP-A: Alistador, Devolución, Escáner cámara, Discrepancias)
    Route::get('/dropi/alistador', [\App\Http\Controllers\App\DropiOperacionController::class, 'alistador'])->name('app.dropi.alistador');
    Route::post('/dropi/alistador/heartbeat', [\App\Http\Controllers\App\DropiOperacionController::class, 'heartbeat'])
        ->middleware('throttle:120,1')  // Re-audit N8/H5 · 2 req/s por IP tope
        ->name('app.dropi.alistador.heartbeat');
    Route::post('/dropi/alistador/pedido/{pedido}/tomar', [\App\Http\Controllers\App\DropiOperacionController::class, 'tomarPedido'])->name('app.dropi.alistador.tomar');
    Route::post('/dropi/alistador/pedido/{pedido}/empacar', [\App\Http\Controllers\App\DropiOperacionController::class, 'empacarPedido'])->name('app.dropi.alistador.empacar');
    Route::post('/dropi/alistador/pedido/{pedido}/despachar', [\App\Http\Controllers\App\DropiOperacionController::class, 'despacharPedido'])->name('app.dropi.alistador.despachar');
    Route::get('/dropi/devolucion/registrar', [\App\Http\Controllers\App\DropiOperacionController::class, 'devolucionForm'])->name('app.dropi.devolucion.registrar');
    Route::post('/dropi/devolucion/registrar', [\App\Http\Controllers\App\DropiOperacionController::class, 'devolucionGuardar'])->name('app.dropi.devolucion.guardar');
    Route::get('/dropi/escaner-camara', [\App\Http\Controllers\App\DropiOperacionController::class, 'escanerCamara'])->name('app.dropi.escaner-camara');
    Route::post('/dropi/escaner/buscar', [\App\Http\Controllers\App\DropiOperacionController::class, 'escanerBuscar'])->middleware('throttle:60,1')->name('app.dropi.escaner.buscar');
    Route::get('/dropi/discrepancias', [\App\Http\Controllers\App\DropiOperacionController::class, 'discrepancias'])->name('app.dropi.discrepancias');

    // Dropi · Gestión (DRP-B)
    Route::get('/dropi/pedidos/{pedido}/editar', [\App\Http\Controllers\App\DropiGestionController::class, 'pedidoEditar'])->name('app.dropi.pedido.editar');
    Route::match(['put', 'post'], '/dropi/pedidos/{pedido}/actualizar', [\App\Http\Controllers\App\DropiGestionController::class, 'pedidoActualizar'])->name('app.dropi.pedido.actualizar');
    Route::get('/dropi/cortes', [\App\Http\Controllers\App\DropiGestionController::class, 'cortesIndex'])->name('app.dropi.cortes');
    Route::post('/dropi/cortes', [\App\Http\Controllers\App\DropiGestionController::class, 'corteCrear'])->name('app.dropi.corte.crear');
    Route::post('/dropi/cortes/{corte}/cerrar', [\App\Http\Controllers\App\DropiGestionController::class, 'corteCerrar'])->name('app.dropi.corte.cerrar');
    Route::get('/dropi/wallet', [\App\Http\Controllers\App\DropiGestionController::class, 'walletIndex'])->name('app.dropi.wallet');
    Route::post('/dropi/wallet', [\App\Http\Controllers\App\DropiGestionController::class, 'walletCrear'])->name('app.dropi.wallet.crear');
    Route::get('/dropi/ubicaciones', [\App\Http\Controllers\App\DropiGestionController::class, 'ubicacionesIndex'])->name('app.dropi.ubicaciones');
    Route::post('/dropi/ubicaciones', [\App\Http\Controllers\App\DropiGestionController::class, 'ubicacionGuardar'])->name('app.dropi.ubicacion.guardar');
    Route::delete('/dropi/ubicaciones/{ubicacion}', [\App\Http\Controllers\App\DropiGestionController::class, 'ubicacionEliminar'])->name('app.dropi.ubicacion.eliminar');

    // Dropi · Reportes/Inventario/Import (DRP-C)
    Route::get('/dropi/inventario-en-vivo', [\App\Http\Controllers\App\DropiReportesController::class, 'inventarioEnVivo'])->name('app.dropi.inventario');
    Route::get('/dropi/reportes', [\App\Http\Controllers\App\DropiReportesController::class, 'reportes'])->name('app.dropi.reportes');
    Route::get('/dropi/productos/importar', [\App\Http\Controllers\App\DropiReportesController::class, 'importarForm'])->name('app.dropi.productos.importar');
    Route::post('/dropi/productos/importar', [\App\Http\Controllers\App\DropiReportesController::class, 'importarProcesar'])->name('app.dropi.productos.importar.procesar');
    Route::get('/catalogo', [\App\Http\Controllers\App\CatalogoController::class, 'index'])->name('app.catalogo.index');
    Route::get('/empresa', [\App\Http\Controllers\App\EmpresaController::class, 'index'])->name('app.empresa.index');
    Route::post('/empresa', [\App\Http\Controllers\App\EmpresaController::class, 'guardar'])->name('app.empresa.guardar');
    Route::get('/siigo', [\App\Http\Controllers\App\SiigoController::class, 'index'])->name('app.siigo.index');
    // FIL-E · Catálogo maestras + Bandeja
    Route::get('/catalogo/maestras', [\App\Http\Controllers\App\CatalogoMaestrasController::class, 'index'])->name('app.catalogo.maestras');
    Route::post('/catalogo/marca', [\App\Http\Controllers\App\CatalogoMaestrasController::class, 'marcaGuardar']);
    Route::delete('/catalogo/marca/{marca}', [\App\Http\Controllers\App\CatalogoMaestrasController::class, 'marcaEliminar']);
    Route::post('/catalogo/categoria', [\App\Http\Controllers\App\CatalogoMaestrasController::class, 'categoriaGuardar']);
    Route::delete('/catalogo/categoria/{categoria}', [\App\Http\Controllers\App\CatalogoMaestrasController::class, 'categoriaEliminar']);
    Route::post('/catalogo/color', [\App\Http\Controllers\App\CatalogoMaestrasController::class, 'colorGuardar']);
    Route::delete('/catalogo/color/{color}', [\App\Http\Controllers\App\CatalogoMaestrasController::class, 'colorEliminar']);
    Route::get('/bandeja-importaciones', [\App\Http\Controllers\App\BandejaImportacionesController::class, 'index'])->name('app.bandeja');

    // M10 · Gastos y reembolsos
    Route::get('/gastos', [\App\Http\Controllers\App\GastosController::class, 'index'])->name('app.gastos');
    Route::post('/gastos', [\App\Http\Controllers\App\GastosController::class, 'crear'])->name('app.gastos.crear');
    Route::post('/gastos/{gasto}/aprobar', [\App\Http\Controllers\App\GastosController::class, 'aprobar'])->name('app.gastos.aprobar');
    Route::post('/gastos/{gasto}/rechazar', [\App\Http\Controllers\App\GastosController::class, 'rechazar'])->name('app.gastos.rechazar');
    Route::post('/gastos/{gasto}/pagado', [\App\Http\Controllers\App\GastosController::class, 'marcarPagado'])->name('app.gastos.pagado');

    // M9 · Gestión Humana
    Route::get('/rrhh/vacantes', [\App\Http\Controllers\App\RrhhController::class, 'vacantesIndex'])->name('app.rrhh.vacantes');
    Route::post('/rrhh/vacantes', [\App\Http\Controllers\App\RrhhController::class, 'vacanteCrear'])->name('app.rrhh.vacante.crear');
    Route::get('/rrhh/vacantes/{vacante}', [\App\Http\Controllers\App\RrhhController::class, 'vacanteShow'])->name('app.rrhh.vacante.show');
    Route::post('/rrhh/vacantes/{vacante}/candidatos', [\App\Http\Controllers\App\RrhhController::class, 'candidatoCrear'])->name('app.rrhh.candidato.crear');
    Route::put('/rrhh/candidatos/{candidato}', [\App\Http\Controllers\App\RrhhController::class, 'candidatoActualizar'])->name('app.rrhh.candidato.actualizar');
    Route::get('/rrhh/empleados', [\App\Http\Controllers\App\RrhhController::class, 'empleadosIndex'])->name('app.rrhh.empleados');
    Route::post('/rrhh/empleados', [\App\Http\Controllers\App\RrhhController::class, 'empleadoCrear'])->name('app.rrhh.empleado.crear');
    Route::put('/rrhh/empleados/{empleado}/induccion', [\App\Http\Controllers\App\RrhhController::class, 'empleadoInduccion'])->name('app.rrhh.empleado.induccion');

    // M8 · Marketing
    Route::get('/marketing/parrilla', [\App\Http\Controllers\App\MarketingController::class, 'parrilla'])->name('app.marketing.parrilla');
    Route::post('/marketing/parrilla', [\App\Http\Controllers\App\MarketingController::class, 'postCrear'])->name('app.marketing.post.crear');
    Route::put('/marketing/parrilla/{post}', [\App\Http\Controllers\App\MarketingController::class, 'postActualizar'])->name('app.marketing.post.actualizar');
    Route::delete('/marketing/parrilla/{post}', [\App\Http\Controllers\App\MarketingController::class, 'postEliminar'])->name('app.marketing.post.eliminar');
    Route::get('/marketing/producto/{producto}/ficha', [\App\Http\Controllers\App\MarketingController::class, 'fichaEditar'])->name('app.marketing.ficha');
    Route::match(['put', 'post'], '/marketing/producto/{producto}/ficha', [\App\Http\Controllers\App\MarketingController::class, 'fichaGuardar'])->name('app.marketing.ficha.guardar');

    // M7 · Garantías
    Route::get('/garantias', [\App\Http\Controllers\App\GarantiasController::class, 'index'])->name('app.garantias.index');
    Route::get('/garantias/nueva', [\App\Http\Controllers\App\GarantiasController::class, 'form'])->name('app.garantias.nueva');
    Route::post('/garantias', [\App\Http\Controllers\App\GarantiasController::class, 'crear'])->name('app.garantias.crear');
    Route::get('/garantias/{ticket}', [\App\Http\Controllers\App\GarantiasController::class, 'show'])->name('app.garantias.show');
    Route::post('/garantias/{ticket}/decidir', [\App\Http\Controllers\App\GarantiasController::class, 'decidir'])->name('app.garantias.decidir');
    Route::post('/garantias/{ticket}/reposicion', [\App\Http\Controllers\App\GarantiasController::class, 'iniciarReposicion'])->name('app.garantias.reposicion');
    Route::post('/garantias/{ticket}/cerrar', [\App\Http\Controllers\App\GarantiasController::class, 'cerrar'])->name('app.garantias.cerrar');

    // Pedidos B2B (recibidos del portal)
    Route::get('/pedidos-b2b', [\App\Http\Controllers\App\PedidosB2BController::class, 'index'])->name('app.pedidos-b2b.index');
    Route::get('/pedidos-b2b/{pedido}', [\App\Http\Controllers\App\PedidosB2BController::class, 'show'])->name('app.pedidos-b2b.show');
    Route::post('/pedidos-b2b/{pedido}/aprobar', [\App\Http\Controllers\App\PedidosB2BController::class, 'aprobar'])->name('app.pedidos-b2b.aprobar');
    Route::post('/pedidos-b2b/{pedido}/rechazar', [\App\Http\Controllers\App\PedidosB2BController::class, 'rechazar'])->name('app.pedidos-b2b.rechazar');
    Route::post('/pedidos-b2b/{pedido}/facturar', [\App\Http\Controllers\App\PedidosB2BController::class, 'facturar'])->name('app.pedidos-b2b.facturar');

    // CRM
    Route::get('/crm', [\App\Http\Controllers\App\CrmController::class, 'index'])->name('app.crm.index');
    Route::post('/crm/interaccion', [\App\Http\Controllers\App\CrmController::class, 'crearInteraccion'])->name('app.crm.interaccion.crear');
    Route::post('/crm/segmentar', [\App\Http\Controllers\App\CrmController::class, 'segmentarAhora'])->name('app.crm.segmentar');
    // Re-audit SEG A3 · rate-limit anti-enumeración.
    Route::get('/api/contactos/buscar', [\App\Http\Controllers\App\ContactosController::class, 'buscarApi'])
        ->middleware('throttle:60,1')->name('app.contactos.buscar');

    // Estación de Empaque (escaneo con throttle generoso — pistola dispara ~30/min real)
    Route::get('/estacion-empaque', [\App\Http\Controllers\App\EstacionEmpaqueController::class, 'index'])->name('app.estacion');
    Route::middleware('throttle:120,1')->group(function () {
        Route::post('/estacion-empaque/escanear', [\App\Http\Controllers\App\EstacionEmpaqueController::class, 'escanear'])->name('app.estacion.escanear');
        Route::post('/estacion-empaque/foto', [\App\Http\Controllers\App\EstacionEmpaqueController::class, 'guardarFoto'])->name('app.estacion.foto');
        Route::post('/estacion-empaque/confirmar', [\App\Http\Controllers\App\EstacionEmpaqueController::class, 'confirmar'])->name('app.estacion.confirmar');
        Route::post('/estacion-empaque/cancelar', [\App\Http\Controllers\App\EstacionEmpaqueController::class, 'cancelar'])->name('app.estacion.cancelar');
    });
});
Route::post('/logout', [\App\Http\Controllers\App\AuthController::class, 'logout'])->middleware(['web', 'auth']);

// ============================================================
// PORTAL B2B · Clientes con guard 'cliente' bajo /portal
// ============================================================
Route::middleware(['web', 'guest:cliente'])->prefix('portal')->group(function () {
    Route::get('/login', [\App\Http\Controllers\Portal\PortalAuthController::class, 'showLogin'])->name('portal.login');
    // Re-audit SEG A2 · dos throttles combinados:
    //   - throttle:portal-login → 5 intentos por (IP + email documento) por minuto
    //   - throttle:portal-login-ip → 15 intentos por IP por 10 min (defensa distribuida)
    Route::post('/login', [\App\Http\Controllers\Portal\PortalAuthController::class, 'login'])
        ->middleware(['throttle:portal-login', 'throttle:portal-login-ip']);

    // H3 · reset password del portal (3 pasos: solicitar → form → submit).
    // Throttle: 5 solicitudes por hora por IP (anti-spam / anti-enumeración).
    Route::post('/password/olvide', [\App\Http\Controllers\Portal\PortalAuthController::class, 'olvidePassword'])
        ->middleware('throttle:5,60')->name('portal.password.olvide');
    Route::get('/password/reset', [\App\Http\Controllers\Portal\PortalAuthController::class, 'resetForm'])
        ->middleware('signed')->name('portal.password.reset.form');
    Route::post('/password/reset', [\App\Http\Controllers\Portal\PortalAuthController::class, 'resetSubmit'])
        ->middleware('throttle:10,60')->name('portal.password.reset.submit');
});

Route::middleware(['web', 'auth:cliente'])->prefix('portal')->group(function () {
    Route::get('/', \App\Http\Controllers\Portal\PortalHomeController::class)->name('portal.home');
    Route::post('/logout', [\App\Http\Controllers\Portal\PortalAuthController::class, 'logout'])->name('portal.logout');

    // Catálogo con precios del cliente
    Route::get('/catalogo', [\App\Http\Controllers\Portal\PortalCatalogoController::class, 'index'])->name('portal.catalogo');
    Route::get('/producto/{producto}', [\App\Http\Controllers\Portal\PortalCatalogoController::class, 'show'])->name('portal.producto');

    // Pedidos B2B
    Route::get('/carrito', [\App\Http\Controllers\Portal\PortalCarritoController::class, 'index'])->name('portal.carrito');
    Route::post('/carrito/confirmar', [\App\Http\Controllers\Portal\PortalCarritoController::class, 'confirmar'])
        ->middleware('throttle:15,1') // QA-D Bloque2: evita spam de pedidos
        ->name('portal.carrito.confirmar');
    Route::get('/pedidos', [\App\Http\Controllers\Portal\PortalPedidosController::class, 'index'])->name('portal.pedidos');
    Route::get('/pedidos/{pedido}', [\App\Http\Controllers\Portal\PortalPedidosController::class, 'show'])->name('portal.pedidos.show');

    // Facturas del cliente
    Route::get('/facturas', [\App\Http\Controllers\Portal\PortalFacturasController::class, 'index'])->name('portal.facturas');
    // H2 · detalle de factura (items + pagos + saldo) para el portal.
    Route::get('/facturas/{factura}', [\App\Http\Controllers\Portal\PortalFacturasController::class, 'show'])->name('portal.facturas.show');
    // Re-audit UX#1 · descarga PDF de la factura desde el portal.
    Route::get('/factura/{factura}/pdf', [\App\Http\Controllers\Portal\PortalFacturasController::class, 'pdf'])
        ->middleware('throttle:30,1')
        ->name('portal.factura.pdf');
});


// Fallback para middleware auth que redirige a route('login') → mandamos al login de Filament
Route::get('/login', fn () => redirect('/admin/login'))->name('login');

// Fix Livewire assets con hash bajo PHP built-in server (dev)
Route::get('/livewire-{hash}/livewire.js', function () {
    return response()->file(public_path('vendor/livewire/livewire.js'), ['Content-Type' => 'application/javascript']);
})->where('hash', '.*');
Route::get('/livewire-{hash}/livewire.min.js.map', function () {
    return response()->file(public_path('vendor/livewire/livewire.js.map'), ['Content-Type' => 'application/json']);
})->where('hash', '.*');

// Auto-login DEV — SOLO local + APP_DEBUG=true + IP localhost.
// Triple guard evita "prod con APP_ENV=local mal copiado = admin gratis".
if (app()->environment('local') && config('app.debug') === true) {
    Route::get('/dev-login', function () {
        // Usar REMOTE_ADDR directo (bypasea cualquier TrustProxies futuro que respete X-Forwarded-For).
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        abort_unless(in_array($ip, ['127.0.0.1', '::1'], true), 403, 'dev-login solo desde localhost');
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
    // P5 · manifiesto + plantilla productos: sólo equipo administrativo (Aracely/Gerencia).
    Route::middleware([\App\Http\Middleware\SoloAracely::class])->group(function () {
        Route::get('/dropi/manifiesto/{corte}', [ManifiestoController::class, 'descargar'])
            ->name('dropi.manifiesto.descargar');
        Route::get('/dropi/plantilla/productos', [PlantillaImportProductosController::class, 'descargar'])
            ->name('dropi.plantilla.productos');
    });

    // P9 · escáner con throttle para evitar despacho masivo o enumeración de guías.
    Route::post('/dropi/escaner/analizar', [EscanerController::class, 'analizar'])
        ->middleware('throttle:60,1')->name('dropi.escaner.analizar');
    Route::post('/dropi/escaner/despachar', [EscanerController::class, 'despachar'])
        ->middleware('throttle:30,1')->name('dropi.escaner.despachar');

    // Re-audit SEG C1 · rate-limit para bloquear enumeración de estados de cuenta.
    Route::get('/cartera/estado-cuenta/{contacto}', [EstadoCuentaController::class, 'pdf'])
        ->middleware('throttle:20,1')
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
        // Re-audit M2 R3 PATRÓN R (SEG-A6) · throttle en PDFs (DomPDF es CPU-heavy).
        Route::get('/compras/orden/{orden}/pdf', [OrdenCompraPdfController::class, 'pdf'])->middleware('throttle:20,1')->name('compras.orden.pdf');
        Route::get('/compras/recepcion/{recepcion}/pdf', [RecepcionPdfController::class, 'pdf'])->middleware('throttle:20,1')->name('compras.recepcion.pdf');
        Route::get('/compras/importacion/{importacion}/pdf', [ImportacionPdfController::class, 'pdf'])->middleware('throttle:20,1')->name('compras.importacion.pdf');
        Route::get('/compras/plantilla/oc', [PlantillaImportOCController::class, 'descargar'])->name('compras.plantilla.oc');
        Route::post('/compras/import/oc', [ImportOCController::class, 'importar'])->middleware('throttle:10,1')->name('compras.import.oc');
    });
});
