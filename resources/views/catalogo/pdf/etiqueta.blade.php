<!DOCTYPE html>
<html><head>
    <meta charset="UTF-8">
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family: DejaVu Sans, sans-serif; padding: 5px; }
        .etiqueta { width: 278px; height: 135px; border: 1px dashed #ccc; padding: 6px; display: flex; }
        .qr { width: 100px; height: 100px; }
        .info { flex: 1; padding-left: 8px; }
        .marca { font-size: 8pt; color: #666; }
        .nombre { font-size: 10pt; font-weight: 700; margin-top: 2px; line-height: 1.1; }
        .variante { font-size: 8pt; color: #555; margin-top: 2px; }
        .codigo { font-family: monospace; font-size: 9pt; font-weight: 700; margin-top: 6px; }
        .precio { font-size: 12pt; font-weight: 700; color: #b45309; margin-top: 4px; }
    </style>
</head><body>
    <div class="etiqueta">
        <img src="{{ $qr }}" class="qr" alt="QR">
        <div class="info">
            <div class="marca">GREAT BABY</div>
            <div class="nombre">{{ Str::limit($variante->producto?->nombre ?? '—', 32) }}</div>
            <div class="variante">
                @if($variante->color_nombre) 🎨 {{ $variante->color_nombre }} @endif
                @if($variante->diseno_nombre) · {{ $variante->diseno_nombre }} @endif
                @if($variante->talla) · T {{ $variante->talla }} @endif
            </div>
            <div class="codigo">{{ $variante->codigo_barras }}</div>
            <div class="precio">${{ number_format((float) ($variante->producto?->precio_proveedor ?? 0), 0, ',', '.') }}</div>
        </div>
    </div>
</body></html>
