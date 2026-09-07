<!DOCTYPE html>
<html><head>
    <meta charset="UTF-8">
    <title>Etiquetas lote</title>
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }
        @page { margin: 10mm; }
        body { font-family: DejaVu Sans, sans-serif; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 5mm; }
        .etiqueta {
            border: 1px dashed #999; padding: 4mm;
            display: flex; align-items: center; gap: 3mm;
            height: 35mm; page-break-inside: avoid;
        }
        .qr { width: 25mm; height: 25mm; }
        .info { flex: 1; min-width: 0; }
        .marca { font-size: 7pt; color: #666; }
        .nombre { font-size: 9pt; font-weight: 700; line-height: 1.1; margin-top: 1mm; }
        .var { font-size: 7pt; color: #555; margin-top: 1mm; }
        .codigo { font-family: monospace; font-size: 8pt; font-weight: 700; margin-top: 2mm; word-break: break-all; }
        .precio { font-size: 11pt; font-weight: 700; color: #b45309; margin-top: 2mm; }
    </style>
</head><body>
    <div class="grid">
        @foreach($variantes as $v)
            <div class="etiqueta">
                <img src="{{ $qrs[$v->id] ?? '' }}" class="qr" alt="QR">
                <div class="info">
                    <div class="marca">GREAT BABY</div>
                    <div class="nombre">{{ \Illuminate\Support\Str::limit($v->producto?->nombre ?? '—', 28) }}</div>
                    <div class="var">
                        @if($v->color_nombre)🎨 {{ $v->color_nombre }}@endif
                        @if($v->diseno_nombre) · {{ $v->diseno_nombre }}@endif
                        @if($v->talla) · T{{ $v->talla }}@endif
                    </div>
                    <div class="codigo">{{ $v->codigo_barras }}</div>
                    <div class="precio">${{ number_format((float) ($v->producto?->precio_proveedor ?? 0), 0, ',', '.') }}</div>
                </div>
            </div>
        @endforeach
    </div>
</body></html>
