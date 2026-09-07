<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Manual de Usuario · GREAT BABY ERP</title>
    <style>
        @page { margin: 32mm 18mm 22mm 18mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5pt; line-height: 1.55; color: #111827; margin: 0; }

        /* =========== MEMBRETE MYTECH SOLUTIONS (con logo real) =========== */
        .mt-header {
            position: fixed;
            top: -26mm;
            left: -8mm;
            right: -8mm;
            height: 22mm;
            background: #ffffff;
            border-bottom: 2pt solid #2563eb;
            padding: 4pt 18pt;
        }
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

        .mt-footer {
            position: fixed;
            bottom: -16mm;
            left: -8mm;
            right: -8mm;
            height: 14mm;
            background: #2563eb;
            color: #ffffff;
            padding: 6pt 20pt;
            font-size: 8pt;
            text-align: center;
            letter-spacing: .3pt;
        }
        .mt-footer .row { line-height: 1.5; }
        .mt-footer .sep { color: #93c5fd; margin: 0 5pt; }
        h1 { font-size: 22pt; color: #b45309; margin: 0 0 6pt; page-break-after: avoid; }
        h2 { font-size: 15pt; color: #b45309; margin: 22pt 0 8pt; padding: 5pt 8pt; background: #fef3c7; border-left: 5pt solid #f59e0b; page-break-after: avoid; }
        h3 { font-size: 12pt; color: #374151; margin: 14pt 0 5pt; page-break-after: avoid; }
        h4 { font-size: 11pt; color: #6b7280; margin: 10pt 0 3pt; }
        p  { margin: 0 0 7pt; text-align: justify; }
        ul, ol { margin: 0 0 8pt; padding-left: 18pt; }
        li { margin: 2pt 0; }
        code, .mono { font-family: DejaVu Sans Mono, monospace; background: #f3f4f6; padding: 1pt 4pt; border-radius: 3pt; font-size: 9.5pt; }
        .caja { border: 1pt solid #e5e7eb; padding: 8pt 10pt; border-radius: 4pt; margin: 5pt 0 10pt; background: #f9fafb; }
        .callout { padding: 8pt 12pt; border-radius: 4pt; margin: 6pt 0; }
        .callout-info { background: #eff6ff; border-left: 4pt solid #3b82f6; }
        .callout-warn { background: #fef3c7; border-left: 4pt solid #f59e0b; }
        .callout-danger { background: #fee2e2; border-left: 4pt solid #ef4444; }
        .callout-success { background: #ecfdf5; border-left: 4pt solid #10b981; }
        .callout strong { display: block; margin-bottom: 3pt; }
        .grid-2 { width: 100%; }
        .grid-2 td { padding: 4pt; vertical-align: top; }
        .mockup { border: 1pt solid #d1d5db; border-radius: 4pt; margin: 5pt 0; overflow: hidden; background: #1e293b; }
        .mockup-header { background: linear-gradient(90deg, #b45309, #f59e0b); color: #fff; padding: 4pt 10pt; font-size: 9pt; font-weight: 700; }
        .mockup-body { padding: 10pt; color: #e5e7eb; font-size: 9pt; }
        .checklist { list-style: none; padding: 0; }
        .checklist li { padding: 3pt 0 3pt 22pt; text-indent: -18pt; }
        .checklist li::before { content: "☐"; margin-right: 8pt; color: #6b7280; font-weight: 700; }
        table.tabla { width: 100%; border-collapse: collapse; margin: 4pt 0 10pt; font-size: 9.5pt; }
        table.tabla th { background: #fef3c7; color: #78350f; text-align: left; padding: 5pt 7pt; border-bottom: 1pt solid #f59e0b; }
        table.tabla td { padding: 4pt 7pt; border-bottom: 1pt solid #e5e7eb; vertical-align: top; }
        .badge { display: inline-block; padding: 1pt 6pt; border-radius: 10pt; font-size: 8.5pt; font-weight: 700; }
        .badge-ok { background: #d1fae5; color: #065f46; }
        .badge-warn { background: #fef3c7; color: #78350f; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .pagebreak { page-break-before: always; }
        .footer-item { color: #9ca3af; font-size: 8pt; }
        .path { color: #6b21a8; font-family: DejaVu Sans Mono, monospace; }
        .kbd { display: inline-block; padding: 1pt 5pt; background: #374151; color: #fff; border-radius: 3pt; font-family: DejaVu Sans Mono, monospace; font-size: 9pt; }
        hr { border: 0; border-top: 1pt solid #e5e7eb; margin: 12pt 0; }
        .toc a { text-decoration: none; color: #b45309; }
        .toc td.n { color: #9ca3af; text-align: right; font-family: DejaVu Sans Mono, monospace; }
    </style>
</head>
<body>

{{-- ============ MEMBRETE MYTECH (aparece en cada página) ============ --}}
<div class="mt-header">
    <table>
        <tr>
            <td class="logo"><img src="{{ public_path('mytech/logo.jpg') }}" alt="MyTech Solutions"></td>
            <td class="brand" style="width:55%;">
                <div class="name"><span class="blue">MY</span> Tech Solutions</div>
                <div class="tag">Innovación y Tecnología para tu Empresa</div>
            </td>
            <td class="right">
                <div class="doc-title">Manual de Usuario</div>
                <div>GREAT BABY ERP · v1.0</div>
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

{{-- ============ PORTADA ============ --}}
<div style="text-align:center;padding-top:80pt;">
    <div style="font-size:11pt;color:#9ca3af;letter-spacing:6pt;text-transform:uppercase;margin-bottom:10pt;">Documento oficial</div>
    <div style="font-size:38pt;color:#b45309;font-weight:900;letter-spacing:-1pt;line-height:1;">GREAT BABY</div>
    <div style="font-size:14pt;color:#6b7280;margin-top:4pt;">Sistema de Gestión Empresarial · ERP v1.0</div>
    <div style="height:2pt;width:120pt;background:#f59e0b;margin:20pt auto;"></div>
    <div style="font-size:24pt;color:#111827;font-weight:700;margin-top:16pt;">Manual de Usuario</div>
    <div style="font-size:12pt;color:#6b7280;margin-top:6pt;font-style:italic;">Guía detallada para operar el sistema día a día</div>
</div>

<div style="text-align:center;margin-top:130pt;">
    <table style="margin:0 auto;font-size:10pt;color:#374151;">
        <tr><td style="text-align:right;padding:3pt 8pt;color:#9ca3af;">Cliente:</td><td style="padding:3pt 8pt;font-weight:700;">GREAT BABY S.A.S.</td></tr>
        <tr><td style="text-align:right;padding:3pt 8pt;color:#9ca3af;">Contacto:</td><td style="padding:3pt 8pt;">Aracely — Bucaramanga, Colombia</td></tr>
        <tr><td style="text-align:right;padding:3pt 8pt;color:#9ca3af;">Desarrollador:</td><td style="padding:3pt 8pt;font-weight:700;color:#2563eb;">MYTech Solutions — Innovación y Tecnología para tu Empresa</td></tr>
        <tr><td style="text-align:right;padding:3pt 8pt;color:#9ca3af;">Contacto MyTech:</td><td style="padding:3pt 8pt;">contacto@mytechsolutions.com · +57 333 724 6403 · Bogotá</td></tr>
        <tr><td style="text-align:right;padding:3pt 8pt;color:#9ca3af;">Fecha:</td><td style="padding:3pt 8pt;">Septiembre 2026</td></tr>
        <tr><td style="text-align:right;padding:3pt 8pt;color:#9ca3af;">Versión:</td><td style="padding:3pt 8pt;">1.0 (pre-lanzamiento)</td></tr>
    </table>
</div>

{{-- ============ ÍNDICE ============ --}}
<div class="pagebreak"></div>
<h1>Índice</h1>
<table class="toc" style="width:100%;font-size:11pt;line-height:2;">
    <tr><td><strong>1.</strong> <a href="#s1">Introducción y primeros pasos</a></td><td class="n">3</td></tr>
    <tr><td><strong>2.</strong> <a href="#s2">Roles, permisos y quién ve qué</a></td><td class="n">4</td></tr>
    <tr><td><strong>3.</strong> <a href="#s3">Estación de Empaque — la pantalla estrella de bodega</a></td><td class="n">5</td></tr>
    <tr><td><strong>4.</strong> <a href="#s4">Torre de Control — dashboard ejecutivo</a></td><td class="n">8</td></tr>
    <tr><td><strong>5.</strong> <a href="#s5">Modo TV Bodega — pantalla para pared</a></td><td class="n">10</td></tr>
    <tr><td><strong>6.</strong> <a href="#s6">Módulo Cartera — facturación y pagos</a></td><td class="n">11</td></tr>
    <tr><td><strong>7.</strong> <a href="#s7">Facturación Electrónica DIAN via SIIGO</a></td><td class="n">14</td></tr>
    <tr><td><strong>8.</strong> <a href="#s8">Módulo Compras — órdenes, recepciones, importaciones</a></td><td class="n">17</td></tr>
    <tr><td><strong>9.</strong> <a href="#s9">Módulo Inventario — traslados, toma física, kardex</a></td><td class="n">19</td></tr>
    <tr><td><strong>10.</strong> <a href="#s10">Módulo Contabilidad — panel + 9 reportes</a></td><td class="n">21</td></tr>
    <tr><td><strong>11.</strong> <a href="#s11">Módulo Dropi — cortes, wallet, sanciones</a></td><td class="n">23</td></tr>
    <tr><td><strong>12.</strong> <a href="#s12">Catálogo — productos, variantes, códigos de barras</a></td><td class="n">25</td></tr>
    <tr><td><strong>13.</strong> <a href="#s13">Herramientas transversales (Cmd+K, Bell, Bandeja)</a></td><td class="n">27</td></tr>
    <tr><td><strong>14.</strong> <a href="#s14">Configuración inicial · Checklist de arranque</a></td><td class="n">29</td></tr>
    <tr><td><strong>15.</strong> <a href="#s15">Pendientes del cliente · Qué necesitamos de vos</a></td><td class="n">31</td></tr>
    <tr><td><strong>16.</strong> <a href="#s16">Operación diaria · Rutina sugerida</a></td><td class="n">33</td></tr>
    <tr><td><strong>17.</strong> <a href="#s17">Backup y recuperación</a></td><td class="n">34</td></tr>
    <tr><td><strong>18.</strong> <a href="#s18">Solución de problemas frecuentes</a></td><td class="n">35</td></tr>
</table>

{{-- ============ 1. INTRODUCCIÓN ============ --}}
<div class="pagebreak"></div>
<a name="s1"></a>
<h2>1. Introducción y primeros pasos</h2>

<h3>¿Qué es el ERP de GREAT BABY?</h3>
<p>Es un sistema web que <strong>reemplaza los Excel, WhatsApp y cuadernos</strong> que hoy usa el equipo para vender, empacar, cobrar y contabilizar. Concentra en un solo lugar los pedidos de Dropi, la facturación DIAN, la cartera de clientes B2B, la contabilidad, el catálogo de productos y la operación de bodega con pistolas escáner.</p>

<h3>¿En qué dispositivo lo uso?</h3>
<ul>
    <li><strong>Computador de escritorio o laptop</strong> — es el uso principal. Chrome, Firefox o Edge actualizados.</li>
    <li><strong>Tablet</strong> — para bodega (estación de empaque, modo TV en pared).</li>
    <li><strong>Celular</strong> — para consultar dashboard y reportes en la calle.</li>
</ul>

<h3>Ingreso al sistema</h3>
<ol>
    <li>Abrí el navegador y andá a <span class="path">https://tu-erp.greatbaby.com/admin</span></li>
    <li>Ingresá tu correo y contraseña.</li>
    <li>Vas a caer en la <strong>Torre de Control</strong> (o en la <strong>Estación de Empaque</strong> si sos alistador).</li>
</ol>

<div class="callout callout-info">
    <strong>💡 Primer acceso</strong>
    La primera vez que ingresás, tu contraseña temporal la vas a recibir por WhatsApp o correo. Cambiala por una tuya desde tu avatar (arriba a la derecha) → <em>Editar perfil</em>.
</div>

<div class="callout callout-warn">
    <strong>⚠️ Sesiones</strong>
    La sesión dura <strong>8 horas de inactividad</strong>. Si dejás el navegador abierto durante la noche vas a tener que volver a iniciar sesión al día siguiente.
</div>

<h3>Navegación general</h3>
<p>El sistema tiene una <strong>barra lateral izquierda</strong> que se colapsa haciendo click en el ícono hamburguesa (☰). Los grupos principales son:</p>

<ul>
    <li><strong>Operación</strong> — Estación de Empaque, Torre de Control</li>
    <li><strong>Dropi</strong> — pedidos, cortes, wallet, devoluciones</li>
    <li><strong>Cartera y CRM</strong> — clientes B2B, facturas, pagos, cobranza</li>
    <li><strong>Configuración</strong> — empresa, integración SIIGO</li>
    <li><strong>Contabilidad</strong> — panel y reportes</li>
    <li><strong>Catálogo</strong> — productos, marcas, colores, tallas</li>
    <li><strong>Compras e Importaciones</strong> — OC, recepciones, contenedores</li>
    <li><strong>Inventario y Logística</strong> — traslados, toma física, alertas</li>
    <li><strong>Herramientas</strong> — bandeja de importaciones</li>
</ul>

<h3>Arriba a la derecha siempre tenés</h3>
<table class="tabla">
    <tr><th style="width:30%;">Elemento</th><th>Qué hace</th></tr>
    <tr><td>🔍 <span class="kbd">⌘K</span> / <span class="kbd">Ctrl K</span></td><td>Buscador global. Encuentra facturas, clientes, pedidos y productos por número o nombre.</td></tr>
    <tr><td>🔎 Buscar (barra)</td><td>Buscador general de Filament (funciona dentro del módulo actual).</td></tr>
    <tr><td>🔔 Campana</td><td>Notificaciones internas: facturas timbradas, DIAN rechazada, contenedor llegó a puerto, stock bajo.</td></tr>
    <tr><td>🅐 Avatar</td><td>Perfil y cerrar sesión.</td></tr>
</table>


{{-- ============ 2. ROLES ============ --}}
<div class="pagebreak"></div>
<a name="s2"></a>
<h2>2. Roles, permisos y quién ve qué</h2>

<p>El sistema distingue <strong>5 roles</strong>. Cada uno ve solo lo que le corresponde. Aracely (dueña) tiene acceso a todo.</p>

<table class="tabla">
    <tr>
        <th style="width:18%;">Rol</th>
        <th style="width:32%;">Quién es</th>
        <th>A qué accede</th>
    </tr>
    <tr>
        <td><span class="badge badge-danger">Aracely</span></td>
        <td>Dueña / Administradora. Tu cuenta personal.</td>
        <td><strong>Todo</strong>: facturas, pagos, empresa, SIIGO, reportes contables, catálogo, bodega, Dropi.</td>
    </tr>
    <tr>
        <td><span class="badge badge-warn">Gerente</span></td>
        <td>Encargado(a) de operación general.</td>
        <td>Facturas, cartera, cobranzas, PDF estados de cuenta, dashboard Dropi, reportes. NO configura SIIGO ni Empresa.</td>
    </tr>
    <tr>
        <td><span class="badge badge-info">Contador</span></td>
        <td>Contador externo o de planta.</td>
        <td>Facturas, pagos, contabilidad, reportes, PDF de facturas, estados de cuenta. NO opera bodega.</td>
    </tr>
    <tr>
        <td><span class="badge badge-info">Vendedor</span></td>
        <td>Vendedor B2B con cartera propia.</td>
        <td>Sus propios clientes, sus facturas, estados de cuenta. Puede buscar clientes en Cmd+K.</td>
    </tr>
    <tr>
        <td><span class="badge badge-ok">Alistador</span></td>
        <td>Operario de bodega con pistola escáner.</td>
        <td>Estación de Empaque, Modo TV, cola de pedidos por empacar. NO ve facturas ni finanzas.</td>
    </tr>
</table>

<div class="callout callout-warn">
    <strong>⚠️ Regla de oro</strong>
    Si intentás abrir una pantalla que tu rol no permite, el sistema te devuelve al inicio con un mensaje amistoso. No es un error — es protección de datos.
</div>


{{-- ============ 3. ESTACIÓN DE EMPAQUE ============ --}}
<div class="pagebreak"></div>
<a name="s3"></a>
<h2>3. Estación de Empaque — la pantalla estrella de bodega</h2>

<p>Es donde el alistador pasa el día entero. Diseñada para operar <strong>con pistola escáner USB</strong> (o el celular en móvil) sin tocar el teclado.</p>

<h3>Cómo llegar</h3>
<p>Menú lateral → <strong>Operación</strong> → <strong>📦 Estación de Empaque</strong></p>

<h3>Layout general</h3>
<div class="mockup">
    <div class="mockup-header">📦 ESTACIÓN DE EMPAQUE · GREAT BABY · ERP</div>
    <div class="mockup-body" style="background:#0f172a;">
        <div style="border:2pt solid #b45309;padding:8pt;border-radius:5pt;">
            <div style="color:#f59e0b;font-size:8pt;letter-spacing:2pt;">🔫 ESTACIÓN DE EMPAQUE · 🔊 · ⛶ · 3:45:12 p.m.</div>
            <div style="margin-top:5pt;padding:6pt;background:#000;color:#f59e0b;font-family:monospace;">
                Escanea guía o código de variante…
            </div>
        </div>
        <div style="margin-top:6pt;display:table;width:100%;">
            <div style="display:table-cell;background:rgba(16,185,129,.15);border-left:3pt solid #10b981;padding:6pt;width:33%;">
                <div style="font-size:7pt;color:#9ca3af;">EMPACADOS HOY</div>
                <div style="font-size:16pt;color:#10b981;font-weight:700;">12</div>
            </div>
            <div style="display:table-cell;background:rgba(59,130,246,.15);border-left:3pt solid #3b82f6;padding:6pt;width:33%;">
                <div style="font-size:7pt;color:#9ca3af;">MEJOR TIEMPO HOY</div>
                <div style="font-size:16pt;color:#3b82f6;font-weight:700;">01:24</div>
            </div>
        </div>
        <div style="margin-top:6pt;padding:8pt;background:rgba(180,83,9,.08);border:1pt solid #b45309;border-radius:4pt;">
            <div style="font-size:8pt;color:#f59e0b;">📦 PEDIDO ACTIVO · GUI-100002 · Diana Torres · Bogotá</div>
            <div style="margin-top:3pt;font-size:8pt;color:#e5e7eb;">Body león manga larga T6M · 1/1 escaneados</div>
            <div style="margin-top:5pt;">
                [Cancelar]  [📸 Foto paquete]  [✅ CONFIRMAR EMPAQUE]
            </div>
        </div>
    </div>
</div>

<h3>Flujo paso a paso: empacar un pedido</h3>
<ol>
    <li><strong>Escaneá la guía Dropi</strong> con la pistola. El sistema abre el pedido y muestra los ítems que hay que buscar en bodega.</li>
    <li><strong>Buscá cada producto</strong> en las ubicaciones. La lista muestra: nombre, color, talla, cantidad y código de barras.</li>
    <li><strong>Escaneá el código de cada variante</strong> a medida que la vas metiendo en la caja. El sistema los marca ✅ y suena un beep verde.</li>
    <li>Si el pedido pide <strong>2 unidades de la misma variante</strong>, escaneá el mismo código 2 veces. El sistema dice "faltan 1", "faltan 0".</li>
    <li>Cerrá la caja y hacé click en <strong>📸 Foto paquete</strong>. Se abre la cámara → click en <strong>📷 CAPTURAR</strong>.</li>
    <li>Click en <strong>✅ CONFIRMAR EMPAQUE</strong>. Suena 🎉 y aparece el próximo pedido de la cola.</li>
</ol>

<div class="callout callout-danger">
    <strong>🚫 No podés confirmar sin foto</strong>
    La foto es evidencia contra reclamos del cliente ("no llegó completo", "faltaba una talla"). El botón <strong>CONFIRMAR</strong> se bloquea hasta que capturés una foto del paquete cerrado.
</div>

<div class="callout callout-info">
    <strong>📱 ¿La cámara no funciona?</strong>
    Si el navegador te niega la cámara, aparece un botón <strong>📁 Subir foto desde archivo</strong>. En móvil, ese botón abre la cámara nativa del celular.
</div>

<h3>Sonidos y voz</h3>
<table class="tabla">
    <tr><th>Sonido</th><th>Significado</th></tr>
    <tr><td>🔊 <strong>Beep agudo (880 Hz)</strong></td><td>Escaneo correcto (guía abierta o variante marcada).</td></tr>
    <tr><td>🔔 <strong>Beep medio (440 Hz)</strong></td><td>Advertencia (ítem ya escaneado, código no reconocido).</td></tr>
    <tr><td>❌ <strong>Beep grave (220 Hz)</strong></td><td>Error (variante no está en el pedido, pedido ya despachado).</td></tr>
    <tr><td>🗣️ <strong>Voz en español</strong></td><td>"Pedido abierto", "Siguiente: Andrea López, Medellín", "Error".</td></tr>
</table>

<div class="callout callout-warn">
    <strong>🔇 Silenciar los sonidos</strong>
    Arriba a la derecha del panel del escáner hay un botón <span class="kbd">🔊</span> / <span class="kbd">🔇</span>. Click ahí silencia beep + voz + confeti. La preferencia se recuerda en el navegador.
</div>

<h3>Cola de pedidos siguientes</h3>
<p>Debajo del pedido activo aparecen los <strong>próximos 6 pedidos</strong> ordenados por antigüedad. Cada tarjeta muestra: guía, cliente, ciudad, cantidad de ítems. Sirve para planear los pasillos que vas a recorrer.</p>

<h3>Métricas y ranking</h3>
<p>El panel derecho muestra tus <strong>empacados del día</strong> y el <strong>mejor tiempo</strong>. Al final de la pantalla aparece el ranking del día con 🥇🥈🥉 — los primeros 5 operarios más rápidos.</p>

<h3>Pantalla completa (fullscreen)</h3>
<p>Botón <span class="kbd">⛶</span> arriba a la derecha del panel escáner. Ideal cuando la tablet está en un soporte y no querés el resto del navegador estorbando.</p>


{{-- ============ 4. TORRE DE CONTROL ============ --}}
<div class="pagebreak"></div>
<a name="s4"></a>
<h2>4. Torre de Control — dashboard ejecutivo</h2>

<p>La pantalla que Aracely y Gerencia miran <strong>todo el día</strong>. Actualiza sola cada 30 segundos.</p>

<h3>Cómo llegar</h3>
<p>Menú lateral → <strong>Operación</strong> → <strong>🗼 Torre de Control</strong> (o inicio automático al ingresar).</p>

<h3>Layout general</h3>
<div class="mockup">
    <div class="mockup-header">🗼 TORRE DE CONTROL · BODEGA · Lunes 07 Septiembre 2026 · 3:45:12 p.m. · SYNC · CADA 30s</div>
    <div class="mockup-body">
        <table style="width:100%;font-size:8pt;">
            <tr>
                <td style="background:rgba(239,68,68,.15);border-left:3pt solid #ef4444;padding:6pt;">PENDIENTES POR EMPACAR<br><span style="font-size:20pt;color:#ef4444;">7</span></td>
                <td style="background:rgba(16,185,129,.15);border-left:3pt solid #10b981;padding:6pt;">EMPACADOS HOY<br><span style="font-size:20pt;color:#10b981;">4</span></td>
                <td style="background:rgba(59,130,246,.15);border-left:3pt solid #3b82f6;padding:6pt;">DESPACHADOS HOY<br><span style="font-size:20pt;color:#3b82f6;">28</span></td>
                <td style="background:rgba(139,92,246,.15);border-left:3pt solid #8b5cf6;padding:6pt;">ENTREGADOS HOY<br><span style="font-size:20pt;color:#8b5cf6;">10</span></td>
            </tr>
            <tr>
                <td colspan="2" style="background:rgba(245,158,11,.15);border-left:3pt solid #f59e0b;padding:6pt;">VENTAS HOY<br><span style="font-size:16pt;color:#f59e0b;font-family:monospace;">$1.747.000</span><br><span style="font-size:7pt;color:#10b981;">↑ 100% vs mes ant.</span></td>
                <td colspan="2" style="background:rgba(107,114,128,.15);border-left:3pt solid #6b7280;padding:6pt;">DEVOLUCIONES HOY<br><span style="font-size:20pt;color:#e5e7eb;">0</span></td>
            </tr>
        </table>
        <div style="margin-top:5pt;padding:6pt;background:rgba(15,23,42,.6);border:1pt solid rgba(156,163,175,.15);border-radius:3pt;">
            📈 Últimos 7 días · Flujo diario (Empacados verde · Despachados azul)<br>
            🎯 Distribución por estado (donut de pedidos: Alistando, Despachado, Devolución, Empacado, Entregado, Pendiente, Nuevo)<br>
            🗺️ Mapa de envíos · Colombia (círculos ámbar por ciudad, tamaño = volumen)<br>
            🏆 Ranking operarios hoy (top 5)
        </div>
    </div>
</div>

<h3>Interpretación de los KPIs</h3>
<table class="tabla">
    <tr><th style="width:30%;">KPI</th><th>Qué significa · Cuándo preocuparse</th></tr>
    <tr><td>🔥 <strong>Pendientes por empacar</strong></td><td>Guías de Dropi sin empacar todavía. Si &gt; 50 aparece <strong>banner rojo pulsante</strong> con alarma sonora — pedir refuerzos.</td></tr>
    <tr><td>✅ <strong>Empacados hoy</strong></td><td>Pedidos que ya salieron de la Estación de Empaque hoy. Meta interna: match despachados.</td></tr>
    <tr><td>🚚 <strong>Despachados hoy</strong></td><td>Guías que ya salieron a ruta (marcadas con scanner al camión).</td></tr>
    <tr><td>💰 <strong>Ventas hoy</strong></td><td>Suma de <em>monto_esperado_proveedor</em> de pedidos pagados hoy por Dropi. Flecha ↑↓ compara con mismo día del mes anterior.</td></tr>
    <tr><td>↩️ <strong>Devoluciones hoy</strong></td><td>Guías que volvieron. Tasa &gt; 15% dispara <strong>banner rojo</strong>.</td></tr>
</table>

<h3>Gráficas</h3>
<ul>
    <li><strong>Línea 7 días</strong>: tendencia de empacados vs despachados. Debe verse una diagonal ascendente sana.</li>
    <li><strong>Donut estados</strong>: distribución de pedidos por estado activo. Si crece "Devolución en camino" es señal de problemas de calidad.</li>
    <li><strong>Mapa Colombia</strong>: heatmap de envíos. Círculos grandes = ciudades top. Sirve para negociar tarifas de transportadora por zona.</li>
    <li><strong>Ranking operarios</strong>: motivación sana. Aracely: usalo en la reunión de bodega semanal.</li>
</ul>

<h3>Alarmas sonoras</h3>
<p>Cuando aparecen alertas críticas nuevas (pendientes &gt; 50 o tasa devoluciones &gt; 15%) se dispara <strong>una sola vez</strong> un beep de alerta + banner rojo pulsante. No se repite cada 30 segundos — solo cuando el conjunto de alertas cambia.</p>


{{-- ============ 5. MODO TV ============ --}}
<div class="pagebreak"></div>
<a name="s5"></a>
<h2>5. Modo TV Bodega — pantalla para pared</h2>

<p>Vista <strong>fullscreen sin sidebar</strong> pensada para <strong>colgar en la pared de bodega</strong> con un TV o tablet fija. Muestra en grande los KPIs del día, ranking y últimos empaques. Motiva al equipo.</p>

<h3>Cómo llegar</h3>
<p>Abrí en el navegador: <span class="path">https://tu-erp.greatbaby.com/tv/empaque</span></p>

<h3>Uso típico</h3>
<ol>
    <li>Conectá un TV o una tablet al WiFi de bodega.</li>
    <li>Abrí Chrome en modo pantalla completa (F11).</li>
    <li>Pegá la URL. Iniciá sesión con el usuario de bodega.</li>
    <li>Se queda para siempre. Se refresca sola cada 20 segundos.</li>
</ol>

<div class="mockup">
    <div class="mockup-header">🏭 BODEGA · EMPAQUE · 3:45 p. m.</div>
    <div class="mockup-body">
        <table style="width:100%;font-size:9pt;">
            <tr>
                <td style="background:rgba(16,185,129,.2);border-left:6pt solid #10b981;padding:12pt;width:40%;">
                    ✅ EMPACADOS HOY<br><span style="font-size:40pt;color:#10b981;font-weight:900;">4</span>
                </td>
                <td style="background:rgba(15,23,42,.7);border:1pt solid #444;padding:8pt;">
                    🏆 RANKING DEL DÍA<br>
                    🥇 Aracely (demo) — <strong style="color:#10b981;">4</strong> (prom 00:45)<br>
                    🥈 María — 3 (prom 01:12)<br>
                    🥉 Carlos — 2 (prom 01:30)
                </td>
            </tr>
            <tr>
                <td style="background:rgba(239,68,68,.2);border-left:6pt solid #ef4444;padding:12pt;">
                    📦 PENDIENTES<br><span style="font-size:40pt;color:#ef4444;font-weight:900;">7</span>
                </td>
                <td colspan="1"></td>
            </tr>
        </table>
        <div style="margin-top:5pt;padding:5pt;background:rgba(15,23,42,.6);font-size:8pt;">
            🕒 Últimos: <span style="background:rgba(16,185,129,.1);padding:2pt 5pt;">GUI-100003 · Aracely · Cali · 00:01 · hace 49 min</span>
        </div>
    </div>
</div>


{{-- ============ 6. CARTERA ============ --}}
<div class="pagebreak"></div>
<a name="s6"></a>
<h2>6. Módulo Cartera — facturación y pagos</h2>

<h3>Cómo llegar</h3>
<p>Menú lateral → <strong>Cartera y CRM</strong>. Ahí adentro: Contactos, Facturas de venta, Pagos, Cobranzas, Condiciones de crédito, Solicitudes de crédito.</p>

<h3>Crear una factura de venta</h3>
<ol>
    <li>Cartera y CRM → <strong>Facturas de venta</strong> → botón <strong>Crear Factura</strong>.</li>
    <li>Completá <strong>Datos generales</strong>:
        <ul>
            <li><strong>Número</strong>: consecutivo interno (ej: FV-000123).</li>
            <li><strong>Cliente</strong>: buscá por nombre o NIT.</li>
            <li><strong>Fecha emisión</strong> y <strong>vencimiento</strong>.</li>
            <li><strong>Vendedor</strong> (opcional, para calcular comisiones).</li>
            <li><strong>Observaciones</strong> (opcional).</li>
        </ul>
    </li>
    <li>En <strong>Ítems</strong>, click en <strong>+ Agregar ítem</strong>. Repetí por cada producto:
        <ul>
            <li><strong>Variante</strong>: buscá por código de barras o nombre.</li>
            <li><strong>Descripción</strong> (se rellena sola con el nombre del producto).</li>
            <li><strong>Cantidad</strong>, <strong>Precio unitario</strong>, <strong>Descuento %</strong>, <strong>Impuesto %</strong> (default 19% IVA).</li>
        </ul>
    </li>
    <li>Completá <strong>Valores</strong>: subtotal, descuento, impuestos, total. El sistema calcula el saldo cuando registrés pagos.</li>
    <li>Click <strong>Crear</strong>. El sistema genera el asiento contable automáticamente (débito 1305 CxC, crédito 4135 ingresos + 2408 IVA).</li>
</ol>

<div class="callout callout-warn">
    <strong>⚠️ Sin ítems, sin asiento</strong>
    Si guardás una factura sin ítems, no se genera asiento contable. El sistema muestra advertencia y podés agregar los ítems después.
</div>

<h3>Registrar un pago</h3>
<ol>
    <li>Facturas de venta → click en la factura → botón <strong>Registrar pago</strong>.</li>
    <li>Completá:
        <ul>
            <li><strong>Monto recibido</strong>: default = saldo pendiente.</li>
            <li><strong>Fecha</strong>, <strong>Medio de pago</strong> (Transferencia, Efectivo, Tarjeta, Nequi, Daviplata).</li>
            <li><strong>Referencia</strong>: número de transacción.</li>
            <li><strong>Banco</strong>, <strong>Notas</strong>.</li>
        </ul>
    </li>
    <li>Click <strong>Confirmar</strong>. El sistema:
        <ul>
            <li>Actualiza saldo de la factura.</li>
            <li>Cambia estado a "Abonada" o "Pagada".</li>
            <li>Registra asiento contable (débito banco 1110, crédito 1305 CxC).</li>
            <li>Si el pago es <strong>menor al saldo</strong> y clasificás la diferencia, agrega asiento adicional (530510 descuentos, 5195 fletes).</li>
            <li>Si el pago es <strong>mayor al saldo</strong> (sobrepago), el sobrante se contabiliza como anticipo (2805).</li>
        </ul>
    </li>
</ol>

<div class="callout callout-success">
    <strong>✅ Anular un pago</strong>
    Si registraste mal un pago, hacé click en el ícono papelera. El sistema:
    <ul style="margin:4pt 0;">
        <li>Recalcula el saldo de la factura</li>
        <li>Elimina los asientos contables asociados</li>
        <li>Cambia el estado de la factura si aplica</li>
    </ul>
</div>

<h3>Antigüedad de saldos (aging)</h3>
<p>En el listado de facturas la columna <strong>Antigüedad</strong> muestra un badge con el tramo:</p>
<ul>
    <li><span class="badge badge-ok">Al día</span> — sin mora</li>
    <li><span class="badge badge-warn">0-30 días</span></li>
    <li><span class="badge badge-warn">31-59 días</span></li>
    <li><span class="badge badge-warn">60-89 días</span> — recordatorio agresivo</li>
    <li><span class="badge badge-danger">90-119 días</span> — evaluar suspensión</li>
    <li><span class="badge badge-danger">120+ días</span> — escala a Gerencia</li>
</ul>

<h3>Cobranza WhatsApp automática</h3>
<p>Todos los días a las <strong>9:00 AM</strong> el sistema barre las facturas vencidas y envía mensajes escalados por WhatsApp al cliente:</p>
<ul>
    <li><strong>0-30 días</strong>: recordatorio suave.</li>
    <li><strong>31-59 días</strong>: "por favor coordinemos el pago".</li>
    <li><strong>60-89 días</strong>: "estamos escalando la gestión".</li>
    <li><strong>90+ días</strong>: aviso de suspensión de crédito.</li>
    <li><strong>120+ días</strong>: escalado a Gerencia.</li>
</ul>

<p>Anti-spam: máximo 1 mensaje por factura por tramo cada 3 días. Bitácora completa en el módulo <strong>Cobranzas</strong>.</p>

<div class="callout callout-warn">
    <strong>⚠️ Requiere configuración</strong>
    Para que los mensajes salgan de verdad hay que configurar credenciales de WhatsApp Cloud API (Meta). Ver sección 15 · Pendientes del cliente.
</div>

<h3>PDF de la factura</h3>
<p>Cualquier factura tiene el botón <strong>PDF</strong> (ícono descarga). Genera el PDF con:</p>
<ul>
    <li>Datos de tu empresa (razón social, NIT, resolución DIAN).</li>
    <li>Datos del cliente.</li>
    <li>Detalle de ítems y totales.</li>
    <li>Si la factura fue emitida electrónicamente: <strong>QR DIAN</strong> con los 10 campos oficiales + banda verde de validez + link vpfe.dian.gov.co.</li>
</ul>


{{-- ============ 7. DIAN + SIIGO ============ --}}
<div class="pagebreak"></div>
<a name="s7"></a>
<h2>7. Facturación Electrónica DIAN vía SIIGO</h2>

<p>Este es el <strong>corazón contable</strong> del sistema. GREAT BABY debe emitir todas las facturas electrónicamente ante la DIAN. Se hace vía la API de SIIGO Cloud.</p>

<h3>Cómo emitir una factura a la DIAN</h3>
<ol>
    <li>Creá la factura normalmente (con ítems y total &gt; 0).</li>
    <li>En el listado de facturas, click en el botón <strong>Emitir a DIAN</strong> (ícono avión de papel).</li>
    <li>Aparece un modal de confirmación. Click <strong>Emitir ahora</strong>.</li>
    <li>El sistema hace: POST a <span class="mono">/v1/invoices</span> de SIIGO con <span class="mono">stamp.send=true</span>.</li>
    <li>SIIGO responde con: <span class="mono">CUFE</span>, <span class="mono">numero_siigo</span>, <span class="mono">stamp_status</span>.</li>
    <li>El sistema:
        <ul>
            <li>Guarda todo lo anterior en la factura.</li>
            <li>Genera el <strong>QR DIAN</strong> (Anexo Técnico 1.9) con los 10 campos oficiales.</li>
            <li>Envía email al cliente con el link público del PDF.</li>
            <li>Aparece notificación 🟢 en la campana de "Factura FV-XXX timbrada".</li>
        </ul>
    </li>
</ol>

<div class="callout callout-danger">
    <strong>🚫 Anti-doble emisión</strong>
    Si hacés doble-click en "Emitir ahora" por error, el sistema bloquea por 30 segundos con el mensaje "Ya hay emisión en curso". No se puede timbrar la misma factura dos veces.
</div>

<h3>¿Y si SIIGO rechaza?</h3>
<ul>
    <li>Aparece notificación 🔴 en la campana con el motivo.</li>
    <li>El sistema <strong>agenda 4 reintentos automáticos</strong> con backoff exponencial: 1 min, 5 min, 30 min, 3 horas.</li>
    <li>Si después de los 4 intentos sigue fallando, aparece notificación 🔴 persistente para que Aracely revise.</li>
</ul>

<h3>¿Y si se cae internet?</h3>
<p>El sistema detecta la excepción de red, limpia la bandera "emitiendo" y agenda un reintento en 1 minuto. La factura no queda bloqueada.</p>

<h3>Link público de la factura</h3>
<p>Cada factura emitida electrónicamente genera un <strong>link único</strong> tipo:</p>
<div class="caja"><span class="mono">https://tu-erp.greatbaby.com/factura/publica/kDfT8rYdZtjLg2QDQWQbgKSq1tNqY34...</span></div>
<p>Ese link descarga el PDF <strong>sin necesidad de iniciar sesión</strong>. Sirve para enviarlo al cliente por WhatsApp o correo.</p>

<h4>Cómo compartirlo</h4>
<ol>
    <li>En el listado de facturas, click en <strong>Link para el cliente</strong> (visible solo si la factura ya tiene token).</li>
    <li>Se abre un modal con el link + banda ámbar de aviso "no lo publiques en redes".</li>
    <li>Click en <strong>Copiar</strong> o directo en <strong>📱 Compartir por WhatsApp</strong>.</li>
</ol>

<div class="callout callout-info">
    <strong>💡 Anulación de facturas</strong>
    Si anulás una factura ya emitida, el link público deja de funcionar (devuelve error 410). El cliente no puede descargar una factura anulada por error.
</div>

<h3>Configuración de SIIGO — antes del primer uso</h3>
<p>Menú → <strong>Integraciones</strong> → <strong>Integración SIIGO</strong>. Completá:</p>

<table class="tabla">
    <tr><th>Campo</th><th>Dónde obtenerlo</th></tr>
    <tr><td>Username</td><td>Es el email de tu cuenta SIIGO Cloud.</td></tr>
    <tr><td>Access Key</td><td>SIIGO → Configuración → Credenciales API. Se guarda <strong>encriptada</strong>.</td></tr>
    <tr><td>Partner ID</td><td>Lo asigna SIIGO al aprobar tu cuenta de API. Formato "SandboxSiigoAPI".</td></tr>
    <tr><td>Ambiente</td><td><em>sandbox</em> (pruebas) o <em>production</em> (real DIAN).</td></tr>
    <tr><td>NIT emisor</td><td>Tu NIT sin guión ni DV (ej: 901738354).</td></tr>
    <tr><td>Tipo documento ID</td><td>SIIGO → Configuración → Tipos de documentos → busca "Factura de venta" y anota el ID numérico.</td></tr>
    <tr><td>Seller ID</td><td>ID numérico del vendedor default en SIIGO.</td></tr>
    <tr><td>Payment Type ID</td><td>ID numérico del método de pago default (crédito 30 días, contado, etc).</td></tr>
    <tr><td>Activo</td><td>Marcalo <strong>solo</strong> cuando esté todo lo anterior.</td></tr>
</table>

<h3>Sincronización automática con SIIGO</h3>
<p>Una vez configurado, el sistema sincroniza automáticamente:</p>
<ul>
    <li><strong>Productos</strong> → cada hora.</li>
    <li><strong>Clientes</strong> → cada 3 horas.</li>
    <li><strong>Catálogos</strong> (impuestos, tipos de documento, formas de pago) → diariamente a las 3:00 AM.</li>
</ul>
<p>Podés ver el log de cada sincronización en <strong>Integración SIIGO → historial</strong>.</p>


{{-- ============ 8. COMPRAS ============ --}}
<div class="pagebreak"></div>
<a name="s8"></a>
<h2>8. Módulo Compras — órdenes, recepciones, importaciones</h2>

<p>Menú → <strong>Compras e Importaciones</strong>. Este módulo maneja el <strong>ciclo completo de abastecimiento</strong>: OC → recepción → contabilización → si es importación, liquidación de costos y manifiesto DIAN.</p>

<div class="callout callout-warn">
    <strong>🔒 Acceso restringido</strong>
    Solo <strong>Aracely y Gerencia</strong> ven este módulo. Los costos de proveedor son información sensible.
</div>

<h3>Crear una Orden de Compra (OC)</h3>
<ol>
    <li>Compras → <strong>Órdenes de compra</strong> → <strong>Crear OC</strong>.</li>
    <li>Datos generales: proveedor, fecha, moneda, incoterm.</li>
    <li>Ítems: variante, cantidad, precio.</li>
    <li>Guardar. La OC queda <strong>Borrador</strong> hasta que la marques <strong>Enviada al proveedor</strong>.</li>
</ol>

<h3>Recibir mercancía</h3>
<ol>
    <li>Compras → <strong>Recepciones</strong> → <strong>Crear recepción</strong>.</li>
    <li>Seleccioná la OC.</li>
    <li>Confirmá cantidades recibidas por línea (pueden ser diferentes a las de la OC — parciales).</li>
    <li>Guardar. El sistema:
        <ul>
            <li>Crea movimientos de <strong>inventario</strong> (entrada por bodega).</li>
            <li>Actualiza <strong>costo promedio</strong> de cada variante.</li>
            <li>Genera asiento contable (débito 1435 mercancías, crédito 2205 proveedor).</li>
        </ul>
    </li>
</ol>

<h3>Importación (contenedor)</h3>
<p>Compras → <strong>Importaciones</strong>. Aquí registrás <strong>contenedores completos</strong> desde China con todos los costos asociados:</p>
<ul>
    <li>Fletes internacionales</li>
    <li>Seguros</li>
    <li>Aranceles</li>
    <li>Gastos portuarios</li>
    <li>Transporte terrestre</li>
</ul>

<p>Al hacer <strong>Liquidar contenedor</strong>, el sistema distribuye todos esos costos <strong>proporcionalmente al valor FOB</strong> de cada producto y actualiza el costo promedio ponderado.</p>

<h3>Manifiesto DIAN</h3>
<p>Al completar la importación, el sistema genera el <strong>Manifiesto DIAN</strong> (PDF) para presentar en la aduana. Se descarga desde el listado de Importaciones.</p>


{{-- ============ 9. INVENTARIO ============ --}}
<div class="pagebreak"></div>
<a name="s9"></a>
<h2>9. Módulo Inventario — traslados, toma física, kardex</h2>

<p>Menú → <strong>Inventario y Logística</strong>.</p>

<h3>Traslados entre bodegas</h3>
<ol>
    <li>Inventario → <strong>Traslados</strong> → <strong>Crear traslado</strong>.</li>
    <li>Bodega origen, bodega destino, ítems, cantidades.</li>
    <li>Guardar en <strong>Borrador</strong>.</li>
    <li>Cuando salga el camión: botón <strong>Ejecutar traslado</strong>. El sistema descuenta stock de origen y crea stock en tránsito.</li>
    <li>Cuando llegue: botón <strong>Confirmar recepción</strong>. Se acredita stock en destino.</li>
</ol>

<h3>Toma física (conteo de inventario)</h3>
<ol>
    <li>Inventario → <strong>Toma física</strong> → <strong>Nueva toma</strong>.</li>
    <li>Elegí la bodega y ubicaciones (opcional filtrar).</li>
    <li>Descargá la <strong>plantilla Excel</strong> con productos + stock actual.</li>
    <li>Con la pistola escáner o manualmente, contá y llená la columna "conteo real".</li>
    <li>Subí el Excel corregido.</li>
    <li>El sistema muestra las diferencias por línea. Confirmá el ajuste.</li>
    <li>Se crean movimientos de ajuste positivo/negativo por variante.</li>
</ol>

<h3>Alertas de stock bajo</h3>
<p>Configurable por variante: <strong>Alertas de Stock</strong>. Definís stock mínimo y punto de reorden. Todos los días a la mañana se dispara notificación 🟡 en la campana con las variantes bajo mínimo.</p>

<h3>Kardex</h3>
<p>Menú → <strong>Inventario</strong> → <strong>Kardex</strong>. Buscá una variante y ves el <strong>histórico completo</strong> de movimientos: fecha, tipo (entrada compra, salida venta, ajuste, traslado), documento, cantidad, saldo posterior.</p>

<h3>Escáner de picking</h3>
<p>Menú → <strong>Dropi</strong> → <strong>Escáner cámara</strong>. Vista alterna del escáner (usa la webcam en lugar de pistola USB) para operarios sin pistola.</p>


{{-- ============ 10. CONTABILIDAD ============ --}}
<div class="pagebreak"></div>
<a name="s10"></a>
<h2>10. Módulo Contabilidad — panel + 9 reportes</h2>

<p>Menú → <strong>Contabilidad</strong>. Aquí se ve el resultado consolidado de todo lo que hacen los otros módulos.</p>

<div class="callout callout-info">
    <strong>💡 Idempotencia</strong>
    Todos los asientos se generan automáticamente. Nada que registrar manualmente en el día a día. Los saldos y estados de facturas se recalculan en tiempo real.
</div>

<h3>Panel Contable</h3>
<p>Vista general con:</p>
<ul>
    <li>Cuentas y balances actualizados</li>
    <li>Movimientos del día</li>
    <li>Conciliación pendiente</li>
    <li>Alertas contables</li>
</ul>

<h3>Los 9 reportes exportables</h3>
<table class="tabla">
    <tr><th style="width:35%;">Reporte</th><th>Contenido</th></tr>
    <tr><td>1. Balance de comprobación</td><td>Débitos y créditos por cuenta, por período.</td></tr>
    <tr><td>2. Libro mayor</td><td>Movimientos detallados de cada cuenta PUC.</td></tr>
    <tr><td>3. Libro diario</td><td>Todos los asientos ordenados por fecha.</td></tr>
    <tr><td>4. Estado de resultados (P&amp;L)</td><td>Ingresos vs gastos del período. Utilidad bruta/operacional/neta.</td></tr>
    <tr><td>5. Balance general</td><td>Activo, pasivo, patrimonio al cierre.</td></tr>
    <tr><td>6. Antigüedad de saldos</td><td>CxC agrupadas por tramo de mora.</td></tr>
    <tr><td>7. Top morosos</td><td>Ranking de clientes con más deuda vencida.</td></tr>
    <tr><td>8. Consignaciones bancarias</td><td>Movimientos por medio de pago.</td></tr>
    <tr><td>9. Impuestos por pagar</td><td>IVA generado vs IVA descontado por mes.</td></tr>
</table>

<h3>Exportar</h3>
<p>Cada reporte tiene botón <strong>Excel</strong> (descarga XLSX con formato) y <strong>PDF</strong> (para archivo/legal).</p>


{{-- ============ 11. DROPI ============ --}}
<div class="pagebreak"></div>
<a name="s11"></a>
<h2>11. Módulo Dropi — cortes, wallet, sanciones</h2>

<p>Menú → <strong>Dropi</strong>. Este módulo gestiona la <strong>relación comercial con Dropi</strong>, la plataforma que ustedes usan para vender por transportadora contra-entrega.</p>

<h3>Cortes</h3>
<p>Cada semana Dropi envía a GREAT BABY el <strong>corte de pagos</strong>. Aquí:</p>
<ol>
    <li>Dropi → <strong>Cortes</strong> → <strong>Nuevo corte</strong>.</li>
    <li>Subí el Excel/CSV que Dropi envió.</li>
    <li>El sistema empareja cada línea con un pedido interno.</li>
    <li>Detecta diferencias: pedido pagado a distinta tarifa, comisión mayor a la esperada, retenciones no acordadas.</li>
    <li>Cada diferencia genera una <strong>Sanción detectada</strong> que Aracely puede reclamar a Dropi.</li>
</ol>

<h3>Wallet</h3>
<p>Dropi maneja un "monedero virtual" con GREAT BABY. Aquí ves todos los movimientos:</p>
<ul>
    <li>Ingresos por pedidos pagados</li>
    <li>Retiros a banco</li>
    <li>Comisiones cobradas</li>
    <li>Indemnizaciones por pedidos perdidos</li>
    <li>Fletes garantía</li>
    <li>Cargos por tarjeta</li>
</ul>

<h3>Devoluciones</h3>
<p>Cuando un cliente rechaza un pedido y vuelve a bodega:</p>
<ol>
    <li>Escanear la guía en la <strong>Estación de Empaque</strong> → el sistema detecta que ya fue despachada y pregunta "¿Es una devolución?".</li>
    <li>Confirmar → se registra la devolución.</li>
    <li>El operario recibe la mercancía, la inspecciona (¿está en buen estado?), y elige destino: reintegrar a stock o dar de baja.</li>
</ol>

<h3>Discrepancias Wallet</h3>
<p>Menú → <strong>Dropi</strong> → <strong>Discrepancias Wallet</strong>. Vista analítica de todas las sanciones detectadas + su estado (pendiente, en reclamo, resuelta).</p>


{{-- ============ 12. CATÁLOGO ============ --}}
<div class="pagebreak"></div>
<a name="s12"></a>
<h2>12. Catálogo — productos, variantes, códigos de barras</h2>

<p>Menú → <strong>Catálogo</strong>. Aquí se define el <strong>maestro de productos</strong> de GREAT BABY.</p>

<h3>Jerarquía</h3>
<ul>
    <li><strong>Categorías</strong> (Bodies, Vestidos, Enterizos, Accesorios…)</li>
    <li><strong>Marcas</strong> (GREAT BABY, marca blanca de importación…)</li>
    <li><strong>Diseños</strong> (Osito, León, Estrellas…)</li>
    <li><strong>Colores</strong> (Azul, Rosa, Amarillo… con código HEX para vista previa).</li>
    <li><strong>Tallas</strong> (RN, 3M, 6M, 12M, 24M, T4, T6…)</li>
    <li><strong>Productos</strong> — el maestro (nombre, categoría, marca, precio base).</li>
    <li><strong>Variantes</strong> — la combinación producto + color + talla + diseño. Cada variante tiene su propio código de barras.</li>
</ul>

<h3>Crear un producto nuevo</h3>
<ol>
    <li>Catálogo → <strong>Productos</strong> → <strong>Crear producto</strong>.</li>
    <li>Completá: nombre, categoría, marca, precio base, impuesto default (19%).</li>
    <li>Guardar.</li>
    <li>En la ficha del producto, subí las <strong>fotos</strong> (múltiples).</li>
    <li>Sección <strong>Variantes</strong>: agregá cada combinación. El código de barras se genera automáticamente si no lo especificás.</li>
</ol>

<h3>Códigos de barras y QR</h3>
<p>Cada variante tiene:</p>
<ul>
    <li><strong>Código de barras</strong> (formato Code128, imprimible).</li>
    <li><strong>QR</strong> con enlace al detalle de la variante (útil para mostrar al cliente en showroom).</li>
    <li><strong>Etiqueta imprimible</strong> (formato 50x25mm típico).</li>
</ul>

<h3>Etiquetas masivas</h3>
<p>Catálogo → <strong>Etiquetas lote</strong>. Seleccioná variantes con checkbox, elegí cantidad de etiquetas por cada una, y descargá un PDF con todas las etiquetas listas para impresora térmica.</p>

<h3>Bulk actions</h3>
<ul>
    <li><strong>Imprimir etiquetas</strong> masivo</li>
    <li><strong>Generar códigos de barras</strong> para variantes que no tengan</li>
    <li><strong>Exportar catálogo</strong> completo a Excel</li>
</ul>


{{-- ============ 13. HERRAMIENTAS TRANSVERSALES ============ --}}
<div class="pagebreak"></div>
<a name="s13"></a>
<h2>13. Herramientas transversales</h2>

<h3>🔍 Command Palette (buscador global)</h3>
<p>Presioná <span class="kbd">⌘K</span> (Mac) o <span class="kbd">Ctrl K</span> (Windows/Linux) desde <strong>cualquier pantalla</strong>. Se abre un modal centrado con un buscador.</p>

<ul>
    <li>Escribí al menos 2 letras.</li>
    <li>Busca en 4 tipos: 🧾 Facturas · 👤 Contactos · 📦 Pedidos Dropi · 🏷️ Productos.</li>
    <li><span class="kbd">↑</span> <span class="kbd">↓</span> para navegar los resultados.</li>
    <li><span class="kbd">Enter</span> para abrir el resultado seleccionado.</li>
    <li><span class="kbd">Esc</span> para cerrar.</li>
</ul>

<p>Los resultados <strong>respetan tu rol</strong>: un alistador solo ve pedidos y productos. Un vendedor no ve facturas de otros vendedores.</p>

<h3>🔔 Bell de notificaciones</h3>
<p>Ícono campana arriba a la derecha con badge rojo cuando hay notificaciones sin leer.</p>

<p>Tipos de notificación:</p>
<table class="tabla">
    <tr><th>Ícono</th><th>Tipo</th><th>Cuándo aparece</th></tr>
    <tr><td>🟢</td><td>Timbrado DIAN OK</td><td>Cada vez que se emite una factura electrónica exitosa.</td></tr>
    <tr><td>🔴</td><td>Timbrado rechazado</td><td>Cuando SIIGO/DIAN rechaza. Incluye motivo.</td></tr>
    <tr><td>🔴</td><td>Facturas vencidas</td><td>Al final del día laboral con listado de vencidas del día.</td></tr>
    <tr><td>🔵</td><td>Contenedor llegó a puerto</td><td>Aviso de la importación registrada.</td></tr>
    <tr><td>🟡</td><td>Stock bajo</td><td>Variantes bajo mínimo configurado.</td></tr>
</table>

<p>Click en la notificación → te lleva directo a la pantalla relevante (factura, importación, etc). Botón <strong>Marcar todas leídas</strong> arriba.</p>

<h3>📥 Bandeja de importaciones</h3>
<p>Menú → <strong>Herramientas</strong> → <strong>Bandeja de importaciones</strong>. Historial de todos los Excel subidos (facturas, contactos, productos, OC, etc) con:</p>
<ul>
    <li>Barra de progreso en vivo (para las que están corriendo).</li>
    <li>Filas OK / Errores por línea.</li>
    <li>Estado (pendiente, corriendo, terminado, fallido).</li>
    <li>Usuario que la subió, tiempo transcurrido.</li>
</ul>


{{-- ============ 14. CONFIGURACIÓN INICIAL ============ --}}
<div class="pagebreak"></div>
<a name="s14"></a>
<h2>14. Configuración inicial · Checklist de arranque</h2>

<p>Antes de operar en producción, completá este checklist en orden:</p>

<h3>Datos de la empresa</h3>
<p>Configuración → <strong>Empresa</strong>. Completá los 5 tabs:</p>

<ul class="checklist">
    <li><strong>Tab Identidad</strong>: razón social, nombre comercial, NIT con DV, régimen, CIIU, logo.</li>
    <li><strong>Tab Contacto</strong>: dirección, ciudad, teléfono, email, web.</li>
    <li><strong>Tab Resolución DIAN</strong>: número de resolución, fechas desde/hasta, prefijo, rango.</li>
    <li><strong>Tab Banco</strong>: cuenta principal para B2B, SWIFT, IBAN (si aplica), moneda.</li>
    <li><strong>Tab PDF</strong>: pie de página del PDF de facturas.</li>
</ul>

<h3>Integración SIIGO</h3>
<p>Configuración → <strong>Integración SIIGO</strong>. Ver detalle en sección 7.</p>

<ul class="checklist">
    <li>Username SIIGO</li>
    <li>Access Key SIIGO</li>
    <li>Partner ID</li>
    <li>Ambiente (sandbox → production)</li>
    <li>NIT emisor sin DV</li>
    <li>Tipo documento ID</li>
    <li>Seller ID</li>
    <li>Payment Type ID</li>
    <li>Activo ✅</li>
    <li>Botón <strong>Sincronizar todo</strong> — primera sync manual (5-10 min).</li>
</ul>

<h3>Usuarios y roles</h3>
<ul class="checklist">
    <li>Crear cuenta para cada operario (Alistador).</li>
    <li>Crear cuenta para Gerente si aplica.</li>
    <li>Crear cuenta para Contador si aplica.</li>
    <li>Crear cuenta para cada Vendedor B2B.</li>
    <li>Asignar rol correcto a cada uno.</li>
    <li>Enviar contraseña temporal por WhatsApp.</li>
</ul>

<h3>Catálogo maestro</h3>
<ul class="checklist">
    <li>Cargar Marcas.</li>
    <li>Cargar Categorías.</li>
    <li>Cargar Colores (con HEX para preview visual).</li>
    <li>Cargar Tallas (RN, 3M, 6M, 12M, 24M, T4, T6…).</li>
    <li>Cargar Diseños.</li>
    <li>Cargar Impuestos (default IVA 19%).</li>
    <li>Cargar Listas de precios (mayorista, retail, exportación).</li>
    <li>Importar Productos desde Excel plantilla.</li>
    <li>Generar Variantes en bulk (combinaciones producto × color × talla).</li>
    <li>Generar códigos de barras masivamente.</li>
    <li>Imprimir etiquetas físicas para bodega.</li>
</ul>

<h3>Contactos B2B</h3>
<ul class="checklist">
    <li>Descargar plantilla Excel de contactos.</li>
    <li>Completar con clientes B2B actuales.</li>
    <li>Importar.</li>
    <li>Definir condiciones de crédito por cliente (plazo, monto máximo).</li>
</ul>

<h3>Bodegas y ubicaciones</h3>
<ul class="checklist">
    <li>Crear las bodegas (Principal, Contenedor, Devoluciones).</li>
    <li>Crear ubicaciones dentro de cada bodega (Estante A, Estante B, Piso, Estantería alta…).</li>
    <li>Hacer toma física inicial para cargar stock real.</li>
</ul>


{{-- ============ 15. PENDIENTES DEL CLIENTE ============ --}}
<div class="pagebreak"></div>
<a name="s15"></a>
<h2>15. Pendientes del cliente · Qué necesitamos de vos</h2>

<p>Para poner el sistema en producción y demostrárselo a full a Aracely, <strong>necesitamos que el cliente nos entregue:</strong></p>

<h3>🔑 Credenciales de servicios externos</h3>

<table class="tabla">
    <tr><th style="width:25%;">Servicio</th><th>Qué necesitamos</th><th>Impacto</th></tr>
    <tr>
        <td><strong>SIIGO Cloud API</strong></td>
        <td>Username, Access Key, Partner ID<br>+ Tipo documento ID, Seller ID, Payment Type ID</td>
        <td><span class="badge badge-danger">CRÍTICO</span><br>Sin esto no se puede emitir facturas electrónicas DIAN.</td>
    </tr>
    <tr>
        <td><strong>WhatsApp Cloud API (Meta)</strong></td>
        <td>Access Token (permanent), Phone Number ID, plantilla aprobada "pedido_despachado"</td>
        <td><span class="badge badge-warn">ALTO</span><br>Sin esto la cobranza WhatsApp queda en modo mock (solo log, no envía).</td>
    </tr>
    <tr>
        <td><strong>SMTP (correo saliente)</strong></td>
        <td>Host, puerto, usuario, contraseña (recomendado: Gmail Workspace con app password, o Mailgun/SES)</td>
        <td><span class="badge badge-danger">CRÍTICO</span><br>Sin esto, el email al cliente al emitir factura DIAN nunca sale.</td>
    </tr>
    <tr>
        <td><strong>Servidor de producción</strong></td>
        <td>VPS con Ubuntu 22+, PHP 8.2+, MySQL 8, Nginx, Certbot SSL, dominio apuntado.</td>
        <td><span class="badge badge-danger">CRÍTICO</span><br>Hoy corre en localhost. Para presentar al equipo real se necesita hosting.</td>
    </tr>
</table>

<h3>📄 Documentos legales</h3>
<ul class="checklist">
    <li>Copia de la <strong>Resolución DIAN de facturación electrónica</strong> vigente.</li>
    <li><strong>NIT de GREAT BABY</strong> con dígito de verificación.</li>
    <li>Datos completos de la <strong>empresa</strong> (razón social exacta, dirección fiscal, teléfono, email).</li>
    <li><strong>Certificado bancario</strong> de la cuenta principal (para PDF de facturas B2B/exportación).</li>
    <li><strong>Firma digital DIAN</strong> (si Aracely la maneja aparte; SIIGO la gestiona por defecto).</li>
</ul>

<h3>📸 Assets de marca</h3>
<ul class="checklist">
    <li>Logo en <strong>alta resolución</strong> (PNG con fondo transparente, mínimo 800px).</li>
    <li>Logo en <strong>versión monocromática</strong> para PDF blanco/negro.</li>
    <li>Paleta de colores oficial (si difiere del ámbar actual).</li>
    <li>Fotos de <strong>productos</strong> del catálogo actual (idealmente 1 por variante).</li>
</ul>

<h3>📊 Datos maestros iniciales</h3>
<ul class="checklist">
    <li>Excel con <strong>clientes B2B actuales</strong> (nombre, NIT, dirección, teléfono, email, ciudad).</li>
    <li>Excel con <strong>catálogo actual de productos</strong> (nombre, categoría, marca, precio base).</li>
    <li>Excel con <strong>stock actual</strong> por variante (para toma física inicial).</li>
    <li>Excel con <strong>proveedores actuales</strong> (China + nacional).</li>
    <li>Excel con <strong>facturas venta 2026</strong> (para arrancar con histórico).</li>
    <li>Excel con <strong>pagos recibidos 2026</strong>.</li>
    <li>Excel con <strong>órdenes de compra en tránsito</strong> (importaciones pendientes).</li>
</ul>

<h3>👥 Definiciones de negocio</h3>
<ul class="checklist">
    <li>Lista de <strong>usuarios</strong> del sistema (nombre, correo, rol asignado).</li>
    <li><strong>Condiciones de crédito</strong> por cliente (plazo en días, monto máximo).</li>
    <li><strong>Umbrales de alerta</strong>: cuántos pedidos pendientes disparan la alarma (default 50), tasa de devolución tope (default 15%).</li>
    <li><strong>Bodegas físicas</strong> con dirección y responsable.</li>
    <li>Convenios con <strong>transportadoras</strong> (Servientrega, Interrapidísimo, Coordinadora): tarifas.</li>
    <li>Códigos de <strong>cuentas contables PUC</strong> personalizadas (si difieren del estándar).</li>
</ul>

<h3>🎯 Decisiones pendientes</h3>
<ul class="checklist">
    <li>¿Se emiten notas crédito por devoluciones? ¿Qué proceso?</li>
    <li>¿Cómo se manejan las retenciones (retefuente, retenciva, reteica)?</li>
    <li>¿Hay comisiones a vendedores? ¿Cómo se calculan?</li>
    <li>¿Se factura en moneda extranjera (USD/EUR)? ¿Con qué tasa (TRM diaria del Banrep)?</li>
    <li>¿Habrá plantillas de factura distintas por tipo de cliente (Mytheresa vs nacional)?</li>
    <li>¿Qué reportes contables exportar mensualmente al contador externo?</li>
</ul>


{{-- ============ 16. OPERACIÓN DIARIA ============ --}}
<div class="pagebreak"></div>
<a name="s16"></a>
<h2>16. Operación diaria · Rutina sugerida</h2>

<h3>🌅 Aracely (dueña) — mañana</h3>
<ol>
    <li>Abrí <strong>Torre de Control</strong> y revisá KPIs del día anterior vs hoy.</li>
    <li>Chequeá <strong>Campana</strong> por notificaciones nocturnas (facturas timbradas, sync SIIGO errors, contenedor llegado).</li>
    <li>Si hay alerta roja de pendientes altos, llamá a bodega.</li>
    <li>Confirmá que el <strong>backup nocturno</strong> corrió (Herramientas → Bandeja o revisar log).</li>
</ol>

<h3>🏭 Alistador — todo el día</h3>
<ol>
    <li>Abrí <strong>Estación de Empaque</strong> (fullscreen ⛶).</li>
    <li>Silenciá si hace falta (🔇).</li>
    <li>Escaneá guía → busca ítems → escaneá cada uno → foto → CONFIRMAR.</li>
    <li>Repetí hasta terminar la cola.</li>
    <li>Al almuerzo, click en <strong>Cancelar</strong> del pedido activo (para que otro alistador pueda tomarlo).</li>
    <li>Al final del día, revisá tu <strong>ranking</strong> y celebrá.</li>
</ol>

<h3>💼 Gerente / Contador — semanal</h3>
<ol>
    <li>Cartera → <strong>Facturas de venta</strong>: filtrar por Vencida → gestionar cobranza manual sobre las top morosas.</li>
    <li>Cartera → <strong>Cobranzas</strong>: revisar bitácora de WhatsApp enviados.</li>
    <li>Contabilidad → <strong>Balance de comprobación</strong>: exportar y enviar al contador externo.</li>
    <li>Dropi → <strong>Cortes</strong>: subir el corte semanal y revisar sanciones detectadas.</li>
</ol>

<h3>💰 Fin de mes</h3>
<ol>
    <li>Contabilidad → exportar los 9 reportes.</li>
    <li>Revisar <strong>Antigüedad de saldos</strong> con Gerencia.</li>
    <li>Reunión de cierre con el contador externo.</li>
    <li>Backup manual adicional antes del cierre.</li>
</ol>


{{-- ============ 17. BACKUP ============ --}}
<div class="pagebreak"></div>
<a name="s17"></a>
<h2>17. Backup y recuperación</h2>

<h3>Backup automático diario</h3>
<p>Todos los días a las <strong>2:00 AM</strong> el sistema ejecuta:</p>
<ul>
    <li><strong>mysqldump</strong> de toda la base de datos → <span class="path">storage/app/backups/db-YYYYMMDD-HHMMSS.sql</span></li>
    <li><strong>tar.gz</strong> de fotos de empaque → <span class="path">storage/app/backups/fotos-YYYYMMDD-HHMMSS.tar.gz</span></li>
    <li>Retención: <strong>14 días</strong>. Los más viejos se borran automáticamente.</li>
</ul>

<div class="callout callout-warn">
    <strong>⚠️ Backup fuera del servidor</strong>
    Para producción real, hay que sincronizar la carpeta <span class="path">storage/app/backups</span> con S3, DigitalOcean Spaces o similar. Un servidor caído sin backup remoto es un backup inútil.
</div>

<h3>Backup manual</h3>
<p>Cualquier momento, desde consola SSH:</p>
<div class="caja"><span class="mono">php artisan gb:backup --keep=30</span></div>

<h3>Restauración</h3>
<ol>
    <li>Copiar el .sql más reciente al servidor.</li>
    <li>Restaurar: <span class="mono">mysql -u USER -p BASE &lt; db-YYYYMMDD.sql</span></li>
    <li>Restaurar fotos: <span class="mono">tar xzf fotos-YYYYMMDD.tar.gz -C storage/app/private/</span></li>
    <li>Limpiar caches: <span class="mono">php artisan optimize:clear</span></li>
</ol>


{{-- ============ 18. SOLUCIÓN DE PROBLEMAS ============ --}}
<div class="pagebreak"></div>
<a name="s18"></a>
<h2>18. Solución de problemas frecuentes</h2>

<table class="tabla">
    <tr><th style="width:35%;">Problema</th><th>Causa probable · Solución</th></tr>
    <tr>
        <td>El escáner no lee el código de barras</td>
        <td>1) Verificar que el input tenga foco (borde amarillo). 2) Si es pistola USB, probar con teclas manuales. 3) Verificar que el código está impreso legible.</td>
    </tr>
    <tr>
        <td>"Falta la foto del paquete"</td>
        <td>El botón <strong>Foto paquete</strong> se debe presionar antes de <strong>Confirmar</strong>. Si el navegador niega la cámara, usar el fallback <strong>Subir foto desde archivo</strong>.</td>
    </tr>
    <tr>
        <td>"Emisión en curso"</td>
        <td>Hiciste doble-click en Emitir. Esperá 30 segundos y volvé a intentar.</td>
    </tr>
    <tr>
        <td>"Siigo rechazó la factura"</td>
        <td>Ver mensaje detallado en la campana 🔴. Correcciones típicas: NIT del cliente inválido, item sin código válido en SIIGO, resolución DIAN vencida.</td>
    </tr>
    <tr>
        <td>El PDF de factura sale sin logo</td>
        <td>Configuración → Empresa → Tab Identidad → subir el logo.</td>
    </tr>
    <tr>
        <td>El cliente no recibió el email</td>
        <td>1) Verificar que el contacto tenga email. 2) Ver notificación en campana con status ("encolado" vs "falló"). 3) Verificar SMTP configurado. 4) Correr worker de queue.</td>
    </tr>
    <tr>
        <td>Sesión expirada mientras trabajaba</td>
        <td>La sesión dura 8 hs. Volver a ingresar. El trabajo no se pierde (los borradores se guardan automáticamente en Filament).</td>
    </tr>
    <tr>
        <td>"Otro operario está empacando este pedido"</td>
        <td>Alguien más ya escaneó esa guía. Coordinar quién sigue.</td>
    </tr>
    <tr>
        <td>Torre de Control no carga las gráficas</td>
        <td>Necesita internet (Chart.js y Leaflet vienen de CDN). Si el WiFi está caído, refrescar cuando vuelva.</td>
    </tr>
    <tr>
        <td>Command Palette no responde</td>
        <td>La combinación <span class="kbd">⌘K</span>/<span class="kbd">Ctrl K</span> puede estar en conflicto con extensiones del navegador. Probar en modo incógnito.</td>
    </tr>
</table>

<h3>Canales de soporte</h3>
<ul>
    <li><strong>Soporte técnico</strong>: MYTech Solutions — michcardenas001@gmail.com</li>
    <li><strong>Bugs</strong>: reportar por WhatsApp con captura de pantalla + descripción del paso a paso que causó el error.</li>
    <li><strong>Solicitud de features</strong>: se prioriza según impacto en operación.</li>
</ul>

<hr>

<p style="text-align:center;color:#9ca3af;font-size:9pt;margin-top:25pt;">
    Manual v1.0 · GREAT BABY ERP · MYTech Solutions S.A.S.<br>
    Septiembre 2026 · Bucaramanga, Colombia<br>
    <em>Este documento se actualizará conforme se liberen nuevos módulos y funcionalidades.</em>
</p>

</body>
</html>
