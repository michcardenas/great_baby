<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Anexo · Pendientes por módulo — GREAT BABY ERP</title>
    <style>
        @page { margin: 32mm 18mm 22mm 18mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5pt; line-height: 1.55; color: #111827; margin: 0; }
        .mt-header { position: fixed; top: -26mm; left: -8mm; right: -8mm; height: 22mm; background: #fff; border-bottom: 2pt solid #2563eb; padding: 4pt 18pt; }
        .mt-header table { width: 100%; border-collapse: collapse; }
        .mt-header td { vertical-align: middle; padding: 0; }
        .mt-header .logo { width: 60pt; }
        .mt-header .logo img { height: 46pt; display: block; }
        .mt-header .brand { padding-left: 8pt; }
        .mt-header .brand .name { font-size: 15pt; font-weight: 800; color: #111827; letter-spacing: .5pt; line-height: 1; }
        .mt-header .brand .name .blue { color: #2563eb; }
        .mt-header .brand .tag { font-size: 8pt; color: #6b7280; margin-top: 3pt; font-style: italic; }
        .mt-header .right { text-align: right; font-size: 7.5pt; color: #6b7280; line-height: 1.4; }
        .mt-header .right .doc-title { font-weight: 700; color: #2563eb; font-size: 8pt; text-transform: uppercase; letter-spacing: 1pt; }
        .mt-footer { position: fixed; bottom: -16mm; left: -8mm; right: -8mm; height: 14mm; background: #2563eb; color: #fff; padding: 6pt 20pt; font-size: 8pt; text-align: center; letter-spacing: .3pt; }
        .mt-footer .row { line-height: 1.5; }
        .mt-footer .sep { color: #93c5fd; margin: 0 5pt; }
        h1 { font-size: 22pt; color: #b45309; margin: 0 0 6pt; }
        h2 { font-size: 15pt; color: #b45309; margin: 22pt 0 8pt; padding: 5pt 8pt; background: #fef3c7; border-left: 5pt solid #f59e0b; page-break-after: avoid; }
        h3 { font-size: 12pt; color: #374151; margin: 14pt 0 5pt; page-break-after: avoid; }
        p  { margin: 0 0 7pt; text-align: justify; }
        ul, ol { margin: 0 0 8pt; padding-left: 18pt; }
        li { margin: 2pt 0; }
        code, .mono { font-family: DejaVu Sans Mono, monospace; background: #f3f4f6; padding: 1pt 4pt; border-radius: 3pt; font-size: 9.5pt; }
        table.tabla { width: 100%; border-collapse: collapse; margin: 4pt 0 10pt; font-size: 9.5pt; }
        table.tabla th { background: #fef3c7; color: #78350f; text-align: left; padding: 5pt 7pt; border-bottom: 1pt solid #f59e0b; }
        table.tabla td { padding: 4pt 7pt; border-bottom: 1pt solid #e5e7eb; vertical-align: top; }
        .badge { display: inline-block; padding: 1pt 6pt; border-radius: 10pt; font-size: 8.5pt; font-weight: 700; }
        .badge-ok { background: #d1fae5; color: #065f46; }
        .badge-warn { background: #fef3c7; color: #78350f; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .callout { padding: 8pt 12pt; border-radius: 4pt; margin: 6pt 0; }
        .callout-danger { background: #fee2e2; border-left: 4pt solid #ef4444; }
        .callout-warn { background: #fef3c7; border-left: 4pt solid #f59e0b; }
        .callout-info { background: #eff6ff; border-left: 4pt solid #3b82f6; }
        .checklist { list-style: none; padding: 0; }
        .checklist li { padding: 3pt 0 3pt 22pt; text-indent: -18pt; }
        .checklist li::before { content: "☐"; margin-right: 8pt; color: #6b7280; font-weight: 700; }
        .checklist li.done::before { content: "☑"; color: #10b981; }
        hr { border: 0; border-top: 1pt solid #e5e7eb; margin: 12pt 0; }
        .pagebreak { page-break-before: always; }
    </style>
</head>
<body>

<div class="mt-header">
    <table>
        <tr>
            <td class="logo"><img src="{{ public_path('mytech/logo.jpg') }}" alt="MyTech Solutions"></td>
            <td class="brand" style="width:55%;">
                <div class="name"><span class="blue">MY</span> Tech Solutions</div>
                <div class="tag">Innovación y Tecnología para tu Empresa</div>
            </td>
            <td class="right">
                <div class="doc-title">Anexo · Pendientes</div>
                <div>GREAT BABY ERP</div>
                <div>Septiembre 2026</div>
            </td>
        </tr>
    </table>
</div>
<div class="mt-footer">
    <div class="row">
        📍 Bogotá, Colombia
        <span class="sep">|</span> 📞 +57 3 33 724 6403
        <span class="sep">|</span> ✉️ contacto@mytechsolutions.com
        <span class="sep">|</span> 🌐 www.mytechsolutionsco.com
    </div>
</div>

<div style="text-align:center;padding-top:40pt;padding-bottom:20pt;">
    <div style="font-size:10pt;color:#9ca3af;letter-spacing:6pt;text-transform:uppercase;">Anexo del manual</div>
    <div style="font-size:26pt;color:#b45309;font-weight:900;margin-top:6pt;">Pendientes por módulo</div>
    <div style="font-size:11pt;color:#6b7280;margin-top:4pt;">Qué falta, qué depende del cliente, qué viene después</div>
</div>

<hr>

<h2>Resumen general</h2>
<p>Del <strong>ERP contratado</strong>, ya está terminado y probado el <strong>~87%</strong>. Los pendientes se dividen en 3 grupos:</p>

<table class="tabla">
    <tr><th style="width:28%;">Grupo</th><th>Descripción</th><th>Responsable</th></tr>
    <tr>
        <td><span class="badge badge-danger">Bloqueantes cliente</span></td>
        <td>Credenciales y datos que solo Aracely/GREAT BABY pueden entregar. Sin esto, features listas no funcionan en producción.</td>
        <td>Cliente</td>
    </tr>
    <tr>
        <td><span class="badge badge-warn">Módulos por desarrollar</span></td>
        <td>M1 CRM+B2B+Comisiones, M7 Garantías, M8 Marketing, M9 GH, M10 Gerencia. Están en el roadmap del contrato pero no arrancados.</td>
        <td>MYTech Solutions</td>
    </tr>
    <tr>
        <td><span class="badge badge-info">Mejoras / features futuras</span></td>
        <td>Plantillas de factura WYSIWYG, multi-moneda, chatbot interno, app móvil solo-lectura, etc. No están en el contrato; se negocian aparte.</td>
        <td>A negociar</td>
    </tr>
</table>

{{-- ============ POR MÓDULO ============ --}}
<div class="pagebreak"></div>
<h2>Estado detallado por módulo</h2>

<h3>M2 · Compras <span class="badge badge-ok">100% código</span></h3>
<p><strong>Terminado</strong>: OC, recepciones con asientos contables, importaciones con liquidación de costos, manifiestos DIAN, dashboard.</p>
<p><strong>Pendiente del cliente:</strong></p>
<ul class="checklist">
    <li>Lista de proveedores (nacional + China) para carga inicial.</li>
    <li>Convenios comerciales (plazos de pago, descuentos por volumen).</li>
    <li>Códigos de incoterm y puertos que usan.</li>
    <li>Historia de OC 2026 para reconciliar.</li>
</ul>
<p><strong>Falta desarrollar (a futuro):</strong> nada pendiente del contrato.</p>

<h3>M3 · Inventario <span class="badge badge-ok">100% código</span></h3>
<p><strong>Terminado</strong>: traslados entre bodegas, toma física, alertas de stock bajo, reservas, picking con pistola, kardex por variante.</p>
<p><strong>Pendiente del cliente:</strong></p>
<ul class="checklist">
    <li>Definir <strong>bodegas físicas</strong> (nombres, direcciones, responsables).</li>
    <li>Definir <strong>ubicaciones</strong> dentro de cada bodega (pasillos, estantes).</li>
    <li><strong>Toma física inicial</strong> para cargar stock real (Excel).</li>
    <li>Definir <strong>puntos de reorden</strong> por variante (stock mínimo).</li>
</ul>

<h3>M4 · Cartera <span class="badge badge-ok">100% código</span></h3>
<p><strong>Terminado</strong>: facturas con Repeater de ítems, pagos con clasificación de diferencias (anticipos, descuentos, fletes), antigüedad de saldos, cobranza WhatsApp con escalamiento por tramo, reportes, workflow de excepciones de crédito, importación de facturas y pagos por Excel.</p>
<p><strong>Pendiente del cliente:</strong></p>
<ul class="checklist">
    <li><strong>Credenciales WhatsApp Cloud API</strong> (Meta) — hoy en modo mock.</li>
    <li>Plantilla WhatsApp <em>pedido_despachado</em> aprobada por Meta.</li>
    <li>Excel con <strong>clientes B2B</strong> actuales.</li>
    <li>Excel con <strong>condiciones de crédito</strong> por cliente (plazo, monto máximo).</li>
    <li>Historia de facturas 2026 para arranque.</li>
</ul>
<p><strong>Falta a futuro:</strong> integración con pasarela de pagos (Wompi/ePayco/Bold) para link de pago dentro del mensaje de cobranza.</p>

<h3>M5 · Contabilidad <span class="badge badge-ok">100% código</span></h3>
<p><strong>Terminado</strong>: panel contable, conciliación, pagos, asientos automáticos con partida doble, 9 reportes exportables (balance comprobación, mayor, diario, P&amp;L, balance general, antigüedad, top morosos, consignaciones, impuestos). Cuenta 2805 anticipos para sobrepagos.</p>
<p><strong>Pendiente del cliente:</strong></p>
<ul class="checklist">
    <li>Plan de cuentas <strong>personalizado</strong> si difiere del PUC estándar.</li>
    <li>Definir <strong>cuentas específicas</strong> para: descuentos comerciales, fletes asumidos, indemnizaciones a clientes, sanciones Dropi.</li>
    <li>Decidir política sobre <strong>notas crédito</strong> (procedimiento contable + cuenta a usar).</li>
    <li>Definir <strong>retenciones</strong> aplicables (retefuente, retenciva, reteica) y sus tasas.</li>
    <li>Confirmar <strong>periodicidad de cierre</strong>: mensual, quincenal.</li>
</ul>

<h3>M6 · Dropi <span class="badge badge-ok">100% código</span></h3>
<p><strong>Terminado</strong>: cortes semanales, wallet, sanciones detectadas, manifiestos, devoluciones, bitácora de estados, discrepancias.</p>
<p><strong>Pendiente del cliente:</strong></p>
<ul class="checklist">
    <li>Formato exacto del <strong>Excel/CSV de cortes</strong> que envía Dropi.</li>
    <li>Formato del <strong>reporte de wallet</strong>.</li>
    <li>Definir <strong>rangos aceptables</strong> de sanciones (más allá de los cuales se reclama).</li>
</ul>
<p><strong>Falta a futuro:</strong> integración vía <strong>API oficial de Dropi</strong> (§26 del diseño) — cuando publiquen documentación, se activa el sync automático cada 5 minutos.</p>

<h3>Catálogo <span class="badge badge-ok">100% código</span></h3>
<p><strong>Terminado</strong>: tablas maestras (marcas, categorías, colores, diseños, tallas, listas de precios, impuestos, unidades), productos con variantes, códigos de barras + QR, etiquetas masivas.</p>
<p><strong>Pendiente del cliente:</strong></p>
<ul class="checklist">
    <li>Excel <strong>catálogo completo</strong> de productos actuales.</li>
    <li><strong>Fotos</strong> por producto (idealmente 1 por variante — múltiples ángulos).</li>
    <li>Definir <strong>listas de precios</strong> (mayorista, retail, exportación) y qué cliente aplica cada una.</li>
    <li>Configurar <strong>impresora térmica</strong> de etiquetas (recomendado Zebra ZD230 o Xprinter).</li>
</ul>

<h3>SIIGO <span class="badge badge-ok">100% código</span></h3>
<p><strong>Terminado</strong>: cliente OAuth con reintentos, sync automático (productos, clientes, warehouses, price-lists, catálogos), emisión electrónica DIAN completa con QR Anexo 1.9, reintento con backoff exponencial ante fallas.</p>
<p><strong>Pendiente del cliente:</strong></p>
<ul class="checklist">
    <li><strong>Credenciales SIIGO API</strong> (Username, Access Key, Partner ID).</li>
    <li>Cuenta de <strong>ambiente sandbox</strong> para probar antes de ir a producción.</li>
    <li><strong>IDs numéricos</strong>: Tipo Documento (Factura), Seller default, Payment Type default.</li>
    <li>Resolución DIAN vigente (número, fechas, prefijo, rango).</li>
    <li>Firma digital DIAN (SIIGO la gestiona; validar que esté cargada).</li>
</ul>

<h3>Empaque + Torre + Modo TV <span class="badge badge-ok">100% código</span></h3>
<p><strong>Terminado</strong>: Estación con pistola + foto webcam + fallback file + multi-cantidad + ownership + voz TTS es-CO + confeti diario + fullscreen + toggle mute; Torre con KPIs, gráficas Chart.js, mapa Leaflet Colombia, comparativa mes, alarma sonora; Modo TV /tv/empaque con reloj gigante y ranking.</p>
<p><strong>Pendiente del cliente:</strong></p>
<ul class="checklist">
    <li>Comprar <strong>pistolas escáner USB</strong> (recomendado: Zebra DS2208 o similar, ~$150 USD c/u).</li>
    <li>Comprar <strong>tablets</strong> para cada estación de empaque (~$300 USD c/u).</li>
    <li>Comprar / conseguir <strong>Smart TV o pantalla</strong> para el modo TV en pared (mínimo 43").</li>
    <li>Definir <strong>umbrales de alertas</strong>: máximo pedidos pendientes (default 50), máxima tasa devolución (default 15%).</li>
</ul>

<h3>Empresa + Portal público <span class="badge badge-ok">100% código</span></h3>
<p><strong>Terminado</strong>: configuración de empresa 5 tabs (Identidad, Contacto, Resolución DIAN, Banco, PDF), portal público con token de 48 chars, envío de email al cliente al emitir DIAN, link WhatsApp con banda de aviso.</p>
<p><strong>Pendiente del cliente:</strong></p>
<ul class="checklist">
    <li>Todos los <strong>datos de la empresa</strong> (razón social exacta, NIT con DV, dirección fiscal, teléfono, email, régimen tributario).</li>
    <li>Documento de la <strong>Resolución DIAN</strong>.</li>
    <li>Datos <strong>bancarios</strong> para B2B.</li>
    <li><strong>Logo en alta resolución</strong>.</li>
    <li><strong>SMTP</strong> para envío de emails (Gmail Workspace / Mailgun / SES).</li>
</ul>

{{-- ============ MÓDULOS FALTANTES ============ --}}
<div class="pagebreak"></div>
<h2>Módulos por desarrollar (del contrato)</h2>

<h3>M1 · CRM + B2B + Comisiones <span class="badge badge-warn">0% · Alta prioridad</span></h3>
<p><strong>Estimado</strong>: 3-4 sesiones. <strong>Siguiente natural</strong> después de cerrar QA-C.</p>
<p><strong>Contenido</strong>:</p>
<ul>
    <li>Portal B2B para clientes (login propio, ver catálogo con sus precios, hacer pedidos online).</li>
    <li>CRM lite: log de llamadas, correos y visitas por cliente.</li>
    <li>Segmentación de clientes (VIP, En riesgo, Nuevo, Dormido).</li>
    <li>Cálculo automático de comisiones por vendedor (% sobre venta cobrada).</li>
    <li>Reporte de comisiones a pagar mensualmente.</li>
</ul>

<h3>M7 · Garantías postventa <span class="badge badge-warn">0% · Media prioridad</span></h3>
<p><strong>Estimado</strong>: 2 sesiones. Depende de M1.</p>
<p><strong>Contenido</strong>:</p>
<ul>
    <li>Registro de reclamos por producto defectuoso.</li>
    <li>Workflow: recibido → analizado → aprobado → reemplazado / reintegrado / rechazado.</li>
    <li>Estadísticas: variantes con más devoluciones (para mejorar calidad).</li>
    <li>Notificación al cliente por WhatsApp del estado de su garantía.</li>
</ul>

<h3>M8 · Marketing <span class="badge badge-warn">0% · Media prioridad</span></h3>
<p><strong>Estimado</strong>: 2-3 sesiones. Depende de M1.</p>
<p><strong>Contenido</strong>:</p>
<ul>
    <li>Segmentos automáticos (por antigüedad, ticket promedio, ciudad, categoría comprada).</li>
    <li>Campañas WhatsApp masivas con plantillas.</li>
    <li>Cupones de descuento.</li>
    <li>Programa de referidos.</li>
</ul>

<h3>M9 · Gestión Humana <span class="badge badge-warn">0% · Media prioridad</span></h3>
<p><strong>Estimado</strong>: 3-4 sesiones.</p>
<p><strong>Contenido</strong>:</p>
<ul>
    <li>Empleados, cargos, turnos.</li>
    <li>Nómina con provisiones legales (cesantías, prima, vacaciones).</li>
    <li>Bonificaciones por desempeño (link con ranking de operarios).</li>
    <li>Certificados laborales y desprendibles automáticos.</li>
</ul>

<h3>M10 · Gerencia (BI ejecutivo) <span class="badge badge-warn">0% · Media prioridad</span></h3>
<p><strong>Estimado</strong>: 2-3 sesiones. Depende de todos los módulos anteriores.</p>
<p><strong>Contenido</strong>:</p>
<ul>
    <li>Dashboard ejecutivo mensual (P&amp;L visual, margen bruto, ROI por línea).</li>
    <li>Proyección de cierre de mes basado en pace actual.</li>
    <li>Comparativa YoY (año contra año).</li>
    <li>Reporte de KPIs clave imprimible para Junta.</li>
</ul>

{{-- ============ MEJORAS FUTURAS ============ --}}
<div class="pagebreak"></div>
<h2>Mejoras y features futuras (no contratadas)</h2>

<p>Estas <strong>NO están en el contrato original</strong>. Son ideas para negociar en una segunda fase:</p>

<table class="tabla">
    <tr><th style="width:32%;">Feature</th><th>Beneficio</th><th>Estimado</th></tr>
    <tr>
        <td>Plantillas de factura WYSIWYG editables</td>
        <td>Formatos distintos por cliente (Mytheresa exportación vs nacional).</td>
        <td>2-3 sesiones</td>
    </tr>
    <tr>
        <td>Multi-moneda + TRM Banrep</td>
        <td>Facturar en USD/EUR para exportación con TRM automática.</td>
        <td>2 sesiones</td>
    </tr>
    <tr>
        <td>Link de pago (Wompi/ePayco/Bold)</td>
        <td>Cliente paga desde el WhatsApp con Nequi/PSE/tarjeta. La factura se marca sola.</td>
        <td>2 sesiones</td>
    </tr>
    <tr>
        <td>Bot WhatsApp interactivo</td>
        <td>Cliente escribe "1" y ve saldo, "2" pide extracto, "3" pide link de pago.</td>
        <td>3 sesiones</td>
    </tr>
    <tr>
        <td>App móvil (PWA instalable)</td>
        <td>Solo-lectura para Aracely en la calle: ver ventas, aprobar créditos, firmar.</td>
        <td>3-4 sesiones</td>
    </tr>
    <tr>
        <td>Chatbot interno "Preguntá al ERP"</td>
        <td>"¿Cuánto vendí ayer?" → responde con la cifra + link al reporte.</td>
        <td>4 sesiones</td>
    </tr>
    <tr>
        <td>Motor de proyección de compras</td>
        <td>Basado en velocidad de venta + lead time importación → alerta "necesitas comprar 200 unds de X".</td>
        <td>3 sesiones</td>
    </tr>
    <tr>
        <td>Widgets configurables por usuario</td>
        <td>Cada rol arma su propio dashboard con drag-and-drop.</td>
        <td>3 sesiones</td>
    </tr>
    <tr>
        <td>Backup remoto a S3</td>
        <td>Sync automático nocturno de backups a AWS/DigitalOcean. Recuperación ante desastre.</td>
        <td>1 sesión</td>
    </tr>
    <tr>
        <td>Auditoría de accesos</td>
        <td>Reporte de quién entró cuándo y qué modificó. Compliance.</td>
        <td>1 sesión</td>
    </tr>
</table>

{{-- ============ RESUMEN FINAL ============ --}}
<div class="pagebreak"></div>
<h2>Resumen ejecutivo</h2>

<div class="callout callout-info">
    <strong>📊 Estado global</strong>
    <p style="margin-top:5pt;">
        Del ERP contratado ya está terminado el <strong>87%</strong> del código (10 módulos operativos + infraestructura). El sistema pasó por <strong>8 auditorías</strong> especializadas (seguridad, UX, código, flujos) en 3 sprints QA consecutivos, con <strong>cero críticos residuales</strong>.
    </p>
</div>

<h3>Para arrancar en producción — orden sugerido</h3>
<ol>
    <li><strong>Semana 1</strong> — El cliente entrega: credenciales SIIGO + WhatsApp + SMTP + logo + datos empresa + Excel de clientes/productos/proveedores.</li>
    <li><strong>Semana 2</strong> — Se contrata servidor y se despliega el ERP. Carga inicial de datos maestros.</li>
    <li><strong>Semana 3</strong> — Toma física inicial + capacitación al equipo (Aracely, alistadores, gerente, contador).</li>
    <li><strong>Semana 4</strong> — Marcha blanca (facturas en sandbox, empaques reales). Ajustes finales.</li>
    <li><strong>Semana 5+</strong> — Producción real. Emisión DIAN activa. Cobranza WhatsApp activa.</li>
</ol>

<h3>Cronograma sugerido de módulos faltantes</h3>
<table class="tabla">
    <tr><th>Sprint</th><th>Módulo</th><th>Duración</th></tr>
    <tr><td>Fase 2 - Sprint 1</td><td>M1 CRM + Portal B2B + Comisiones</td><td>3-4 sesiones</td></tr>
    <tr><td>Fase 2 - Sprint 2</td><td>M7 Garantías postventa</td><td>2 sesiones</td></tr>
    <tr><td>Fase 2 - Sprint 3</td><td>M8 Marketing + WhatsApp masivo</td><td>2-3 sesiones</td></tr>
    <tr><td>Fase 3 - Sprint 1</td><td>M9 Gestión Humana + Nómina</td><td>3-4 sesiones</td></tr>
    <tr><td>Fase 3 - Sprint 2</td><td>M10 Gerencia (BI ejecutivo)</td><td>2-3 sesiones</td></tr>
</table>

<hr>

<p style="text-align:center;color:#9ca3af;font-size:9pt;margin-top:20pt;">
    Anexo al Manual v1.0 · GREAT BABY ERP · MYTech Solutions S.A.S.<br>
    Septiembre 2026 · Bucaramanga, Colombia
</p>

</body>
</html>
