<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Manifiesto de despacho — Corte {{ $corte->numero }} · {{ $corte->fecha->format('Y-m-d') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #111; margin: 0; padding: 24px; }
        h1 { font-size: 16pt; margin: 0 0 4px; color: #b45309; }
        h2 { font-size: 11pt; margin: 24px 0 8px; padding-bottom: 4px; border-bottom: 1px solid #d1d5db; color: #374151; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { text-align: left; background: #fef3c7; padding: 6px 8px; font-size: 9pt; color: #78350f; }
        td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; font-size: 9pt; }
        .totales { background: #f3f4f6; padding: 10px 12px; border-radius: 4px; margin: 12px 0; }
        .totales-grid { display: table; width: 100%; }
        .totales-cell { display: table-cell; width: 25%; padding: 4px 6px; }
        .totales-label { font-size: 8pt; text-transform: uppercase; color: #6b7280; }
        .totales-val { font-size: 12pt; font-weight: 700; color: #111; }
        .hash { font-family: monospace; font-size: 7pt; word-break: break-all; color: #6b7280; }
        .footer { margin-top: 30px; padding-top: 12px; border-top: 1px solid #d1d5db; font-size: 8pt; color: #6b7280; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 8pt; }
        .badge-ok { background: #dcfce7; color: #166534; }
        .badge-warn { background: #fef3c7; color: #78350f; }
        .badge-err { background: #fee2e2; color: #991b1b; }
        .header-table { width: 100%; }
        .header-table td { border: none; vertical-align: top; padding: 0; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td>
                <h1>GREAT BABY S.A.S.</h1>
                <div style="font-size: 9pt; color: #6b7280;">NIT 901.738.354 · Bucaramanga</div>
                <div style="margin-top: 12px;">
                    <div style="font-weight: 700; font-size: 12pt;">Manifiesto de despacho</div>
                    <div>Corte <strong>{{ $corte->numero }}</strong> · Fecha <strong>{{ $corte->fecha->format('Y-m-d') }}</strong></div>
                    <div>Cerrado {{ \Carbon\Carbon::parse($snapshot['cerrado_at'])->format('Y-m-d H:i') }}</div>
                </div>
            </td>
            <td style="text-align: right; width: 130px;">
                {!! $qrSvg ?? '' !!}
                <div class="hash" style="margin-top: 4px;">SHA-256:<br>{{ $hash }}</div>
            </td>
        </tr>
    </table>

    <div class="totales">
        <div class="totales-grid">
            <div class="totales-cell">
                <div class="totales-label">Total pedidos</div>
                <div class="totales-val">{{ $snapshot['totales']['pedidos_totales'] }}</div>
            </div>
            <div class="totales-cell">
                <div class="totales-label">Despachados</div>
                <div class="totales-val" style="color:#059669;">{{ $snapshot['totales']['pedidos_despachados'] }}</div>
            </div>
            <div class="totales-cell">
                <div class="totales-label">Devueltos</div>
                <div class="totales-val" style="color:#dc2626;">{{ $snapshot['totales']['pedidos_devueltos'] }}</div>
            </div>
            <div class="totales-cell">
                <div class="totales-label">Monto proveedor</div>
                <div class="totales-val">${{ number_format($snapshot['totales']['monto_esperado_total'], 0, ',', '.') }}</div>
            </div>
        </div>
    </div>

    <h2>Detalle de guías del corte</h2>
    <table>
        <thead>
            <tr>
                <th>Guía</th>
                <th>Cliente</th>
                <th>Ciudad</th>
                <th>Transportadora</th>
                <th>Estado</th>
                <th style="text-align: right;">Monto GB</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pedidos as $p)
                <tr>
                    <td style="font-family: monospace;">{{ $p->guia }}</td>
                    <td>{{ $p->cliente_nombre }}</td>
                    <td>{{ $p->cliente_ciudad }}</td>
                    <td>{{ $p->transportadora }}</td>
                    <td>
                        <span class="badge {{ in_array($p->estado->value, ['pagado','despachado','entregado']) ? 'badge-ok' : (in_array($p->estado->value, ['devuelto','cancelado_dropi','cancelado_gb']) ? 'badge-err' : 'badge-warn') }}">
                            {{ $p->estado->label() }}
                        </span>
                    </td>
                    <td style="text-align: right; font-weight: 600;">${{ number_format((float) $p->monto_esperado_proveedor, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <div>Documento generado automáticamente por el ERP GREAT BABY · MYTech Solutions S.A.S.</div>
        <div>Hash de integridad SHA-256: <span class="hash">{{ $hash }}</span></div>
        <div>Cualquier modificación posterior al cierre invalidará este manifiesto.</div>
    </div>
</body>
</html>
