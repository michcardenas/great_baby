<!DOCTYPE html>
<html><head>
<meta charset="UTF-8">
<title>REC {{ $recepcion->numero }}</title>
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #111; margin: 0; padding: 12mm; }
h1 { font-size: 14pt; color:#b45309; }
.small { color:#6b7280; font-size: 9pt; }
table { width: 100%; border-collapse: collapse; margin-top: 4mm; }
th { background:#f3f4f6; padding: 2mm; text-align:left; border-bottom:2px solid #d1d5db; font-size: 8pt; text-transform:uppercase; }
td { padding: 2mm; border-bottom:1px solid #f3f4f6; }
.right { text-align: right; }
</style>
</head><body>

<h1>RECEPCIÓN DE MERCANCÍA {{ $recepcion->numero }}</h1>
<div class="small">
    OC origen: <strong>{{ $recepcion->orden?->numero }}</strong> ·
    Proveedor: <strong>{{ $recepcion->orden?->proveedor?->nombreDisplay() }}</strong> ·
    Bodega: <strong>{{ $recepcion->bodega?->nombre }}</strong> ·
    Fecha: <strong>{{ $recepcion->fecha_recepcion?->format('Y-m-d') }}</strong>
</div>
@if($recepcion->factura_proveedor)<div class="small">Factura proveedor: {{ $recepcion->factura_proveedor }}</div>@endif
@if($recepcion->transportista)<div class="small">Transportista: {{ $recepcion->transportista }}</div>@endif

<table>
    <thead><tr>
        <th>#</th><th>Ítem</th><th>Variante</th>
        <th class="right">Cant recibida</th>
        <th class="right">Costo unit</th>
        <th class="right">Subtotal</th>
        <th>Lote</th><th>Vence</th>
    </tr></thead>
    <tbody>
    @foreach($recepcion->items as $i => $it)
        <tr>
            <td>{{ $i+1 }}</td>
            <td>{{ $it->producto?->nombre ?? '—' }}<br><span class="small">{{ $it->producto?->referencia }}</span></td>
            <td class="small">{{ $it->variante?->codigo_barras ?? '—' }}</td>
            <td class="right"><strong>{{ rtrim(rtrim(number_format((float)$it->cantidad_recibida, 3, ',', '.'), '0'), ',') }}</strong></td>
            <td class="right">${{ number_format((float)$it->costo_unit, 2, ',', '.') }}</td>
            <td class="right">${{ number_format((float)$it->subtotal, 0, ',', '.') }}</td>
            <td>{{ $it->lote ?? '—' }}</td>
            <td>{{ $it->fecha_vencimiento?->format('Y-m-d') ?? '—' }}</td>
        </tr>
    @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5" class="right"><strong>Total recibido:</strong></td>
            <td class="right"><strong>${{ number_format((float)$recepcion->total_recibido, 0, ',', '.') }}</strong></td>
            <td colspan="2"></td>
        </tr>
    </tfoot>
</table>

<div style="margin-top:20mm; display:flex; justify-content:space-between;">
    <div style="width:60mm; border-top:1px solid #111; padding-top:2mm; text-align:center;">Recibió: {{ $recepcion->receptor?->name ?? '—' }}</div>
    <div style="width:60mm; border-top:1px solid #111; padding-top:2mm; text-align:center;">Firma proveedor</div>
</div>

</body></html>
