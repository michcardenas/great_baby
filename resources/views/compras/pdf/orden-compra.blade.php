<!DOCTYPE html>
<html><head>
<meta charset="UTF-8">
<title>OC {{ $orden->numero }}</title>
<style>
* { box-sizing:border-box; margin:0; padding:0; }
@page { margin: 15mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #111; }
.header { border-bottom: 3px solid #b45309; padding-bottom: 8mm; margin-bottom: 6mm; }
.brand { font-size: 16pt; font-weight: 700; color: #b45309; letter-spacing: 1px; }
.doc-num { float:right; text-align:right; }
.doc-num .num { font-size: 14pt; font-weight: 700; }
.section { margin-top: 4mm; }
.section h3 { font-size: 10pt; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #e5e7eb; padding-bottom: 2mm; margin-bottom: 2mm; letter-spacing: .5px; }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 4mm; }
.kv { line-height: 1.4; }
.kv .k { color: #6b7280; font-size: 8pt; text-transform: uppercase; }
.kv .v { font-weight: 600; }
table { width: 100%; border-collapse: collapse; margin-top: 3mm; }
th { background: #f3f4f6; text-align: left; font-size: 8pt; text-transform: uppercase; padding: 2mm; border-bottom: 2px solid #d1d5db; }
td { padding: 2mm; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
td.right, th.right { text-align: right; }
.totales { width: 60mm; margin-left: auto; margin-top: 4mm; }
.totales td { border: none; padding: 1mm 2mm; }
.totales .total { border-top: 2px solid #111; font-weight: 700; font-size: 12pt; }
.stamp { margin-top: 10mm; text-align: center; color: #9ca3af; font-size: 8pt; border-top: 1px solid #e5e7eb; padding-top: 4mm; }
</style>
</head><body>

<div class="header">
    <div class="doc-num">
        <div style="color:#6b7280;font-size:8pt;">ORDEN DE COMPRA</div>
        <div class="num">{{ $orden->numero }}</div>
    </div>
    <div class="brand">GREAT BABY</div>
    <div style="color:#6b7280;font-size:9pt;">NIT 900.XXX.XXX — Bogotá, Colombia</div>
</div>

<div class="section grid2">
    <div class="kv">
        <div class="k">Proveedor</div>
        <div class="v">{{ $orden->proveedor?->nombreDisplay() ?? '—' }}</div>
        <div style="font-size:9pt;color:#6b7280;">NIT {{ $orden->proveedor?->numero_documento }}</div>
        @if($orden->proveedor?->email)<div style="font-size:9pt;color:#6b7280;">{{ $orden->proveedor->email }}</div>@endif
    </div>
    <div class="kv" style="text-align:right;">
        <div class="k">Fecha emisión</div>
        <div class="v">{{ $orden->fecha_emision?->format('Y-m-d') }}</div>
        @if($orden->fecha_esperada)
            <div class="k" style="margin-top:2mm;">Fecha esperada</div>
            <div class="v">{{ $orden->fecha_esperada->format('Y-m-d') }}</div>
        @endif
        <div class="k" style="margin-top:2mm;">Moneda</div>
        <div class="v">{{ $orden->moneda }} @if($orden->moneda !== 'COP') · TRM {{ number_format($orden->tasa_cambio, 2) }}@endif</div>
    </div>
</div>

<div class="section">
    <h3>Ítems</h3>
    <table>
        <thead><tr>
            <th>Ref</th><th>Descripción</th>
            <th class="right">Cant</th>
            <th class="right">Precio</th>
            <th class="right">Dcto%</th>
            <th class="right">IVA%</th>
            <th class="right">Subtotal</th>
            <th class="right">Total</th>
        </tr></thead>
        <tbody>
        @foreach($orden->items as $it)
            <tr>
                <td>{{ $it->producto?->referencia ?? '—' }}</td>
                <td>{{ $it->descripcion }}@if($it->variante?->codigo_barras)<br><span style="font-size:8pt;color:#6b7280;font-family:monospace;">{{ $it->variante->codigo_barras }}</span>@endif</td>
                <td class="right">{{ rtrim(rtrim(number_format((float)$it->cantidad, 3, ',', '.'), '0'), ',') }}</td>
                <td class="right">${{ number_format((float)$it->precio_unit, 2, ',', '.') }}</td>
                <td class="right">{{ number_format((float)$it->descuento_pct, 1) }}%</td>
                <td class="right">{{ number_format((float)$it->iva_pct, 1) }}%</td>
                <td class="right">${{ number_format((float)$it->subtotal, 0, ',', '.') }}</td>
                <td class="right"><strong>${{ number_format((float)$it->total, 0, ',', '.') }}</strong></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<table class="totales">
    <tr><td>Subtotal</td><td class="right">${{ number_format((float)$orden->subtotal, 0, ',', '.') }}</td></tr>
    @if((float)$orden->descuento > 0)<tr><td>Descuento</td><td class="right">-${{ number_format((float)$orden->descuento, 0, ',', '.') }}</td></tr>@endif
    <tr><td>IVA</td><td class="right">${{ number_format((float)$orden->iva, 0, ',', '.') }}</td></tr>
    @if((float)$orden->retefuente > 0)<tr><td>Retefuente</td><td class="right">-${{ number_format((float)$orden->retefuente, 0, ',', '.') }}</td></tr>@endif
    @if((float)$orden->reteiva > 0)<tr><td>Reteiva</td><td class="right">-${{ number_format((float)$orden->reteiva, 0, ',', '.') }}</td></tr>@endif
    @if((float)$orden->reteica > 0)<tr><td>Reteica</td><td class="right">-${{ number_format((float)$orden->reteica, 0, ',', '.') }}</td></tr>@endif
    <tr class="total"><td>TOTAL</td><td class="right">${{ number_format((float)$orden->total, 0, ',', '.') }}</td></tr>
</table>

@if($orden->observaciones)
<div class="section">
    <h3>Observaciones</h3>
    <div>{{ $orden->observaciones }}</div>
</div>
@endif

<div class="stamp">
    Documento generado por Great Baby ERP · {{ now()->format('Y-m-d H:i') }} · Estado: {{ $orden->estado->label() }}
</div>

</body></html>
