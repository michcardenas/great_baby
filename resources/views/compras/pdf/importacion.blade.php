<!DOCTYPE html>
<html><head>
<meta charset="UTF-8">
<title>Liquidación importación {{ $importacion->numero }}</title>
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #111; margin: 0; padding: 10mm; }
h1 { font-size: 14pt; color:#b45309; }
h2 { font-size: 10pt; margin-top: 5mm; border-bottom:2px solid #e5e7eb; padding-bottom:1mm; text-transform:uppercase; color:#6b7280; letter-spacing:.5px;}
.small { color:#6b7280; font-size: 8pt; }
table { width: 100%; border-collapse: collapse; margin-top: 3mm; }
th { background:#f3f4f6; padding: 2mm; text-align:left; border-bottom:2px solid #d1d5db; font-size: 8pt; text-transform:uppercase; }
td { padding: 1.5mm; border-bottom:1px solid #f3f4f6; }
.right { text-align: right; }
.pill { display:inline-block; padding: 1mm 2mm; border-radius: 3mm; font-size: 8pt; background:#dbeafe; color:#1e40af;}
</style>
</head><body>

<h1>Liquidación de importación {{ $importacion->numero }}</h1>
<div class="small">
    Contenedor <strong>{{ $importacion->contenedor ?? '—' }}</strong>
    · BL/AWB {{ $importacion->bl_awb ?? '—' }}
    · Incoterm <span class="pill">{{ $importacion->incoterm ?? '—' }}</span>
    · País origen {{ $importacion->proveedor_pais ?? '—' }}
    · Puerto {{ $importacion->puerto_destino ?? '—' }}
    · Liquidado por {{ $importacion->liquidador?->name ?? '—' }} el {{ $importacion->fecha_liquidacion?->format('Y-m-d') }}
</div>

<h2>Cronología</h2>
<table>
    <tr>
        <td>Zarpe: {{ $importacion->fecha_zarpe?->format('Y-m-d') ?? '—' }}</td>
        <td>ETA: {{ $importacion->eta?->format('Y-m-d') ?? '—' }}</td>
        <td>Llegada: {{ $importacion->fecha_llegada?->format('Y-m-d') ?? '—' }}</td>
        <td>Nacionalización: {{ $importacion->fecha_nacionalizacion?->format('Y-m-d') ?? '—' }}</td>
        <td>Días tránsito: {{ $importacion->diasEnTransito() ?? '—' }}</td>
    </tr>
</table>

<h2>Gastos aplicados</h2>
<table>
    <thead><tr><th>Concepto</th><th>Descripción</th><th>Proveedor</th><th class="right">Monto orig</th><th class="right">Monto base</th><th>Método</th><th>Capitaliza</th></tr></thead>
    <tbody>
    @foreach($importacion->gastos as $g)
        <tr>
            <td>{{ $g->concepto->label() }}</td>
            <td>{{ $g->descripcion }}</td>
            <td class="small">{{ $g->proveedor?->nombreDisplay() ?? '—' }}</td>
            <td class="right">{{ $g->moneda }} {{ number_format((float)$g->monto, 2, ',', '.') }}</td>
            <td class="right">${{ number_format((float)$g->monto_base, 0, ',', '.') }}</td>
            <td>{{ $g->metodo_prorrateo }}</td>
            <td>{{ $g->capitalizable ? 'Sí' : 'No' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<h2>Líneas prorrateadas</h2>
<table>
    <thead><tr>
        <th>Ref</th><th>Producto</th><th>Variante</th>
        <th class="right">Cant</th>
        <th class="right">FOB unit</th>
        <th class="right">FOB total</th>
        <th class="right">Gasto prorrateado</th>
        <th class="right">Costo final unit</th>
        <th class="right">Costo final total</th>
    </tr></thead>
    <tbody>
    @foreach($importacion->lineas as $l)
        <tr>
            <td>{{ $l->producto?->referencia }}</td>
            <td>{{ $l->producto?->nombre }}</td>
            <td class="small">{{ $l->variante?->codigo_barras ?? '—' }}</td>
            <td class="right">{{ rtrim(rtrim(number_format((float)$l->cantidad, 3, ',', '.'), '0'), ',') }}</td>
            <td class="right">${{ number_format((float)$l->costo_fob_unit, 2, ',', '.') }}</td>
            <td class="right">${{ number_format((float)$l->costo_fob_total, 0, ',', '.') }}</td>
            <td class="right">${{ number_format((float)$l->gasto_prorrateado, 0, ',', '.') }}</td>
            <td class="right"><strong>${{ number_format((float)$l->costo_final_unit, 2, ',', '.') }}</strong></td>
            <td class="right"><strong>${{ number_format((float)$l->costo_final_total, 0, ',', '.') }}</strong></td>
        </tr>
    @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5" class="right"><strong>Totales:</strong></td>
            <td class="right"><strong>${{ number_format((float)$importacion->valor_fob, 0, ',', '.') }}</strong></td>
            <td class="right"><strong>${{ number_format((float)$importacion->valor_gastos, 0, ',', '.') }}</strong></td>
            <td></td>
            <td class="right"><strong>${{ number_format((float)$importacion->valor_total_costo, 0, ',', '.') }}</strong></td>
        </tr>
    </tfoot>
</table>

</body></html>
