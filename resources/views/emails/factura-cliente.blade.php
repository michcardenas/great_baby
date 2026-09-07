<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura {{ $factura->numero }}</title>
</head>
<body style="font-family:system-ui,-apple-system,'Segoe UI',sans-serif;color:#111827;line-height:1.55;background:#f3f4f6;margin:0;padding:20px;">
    <div style="max-width:600px;margin:0 auto;background:white;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06);">

        {{-- Outlook desktop no soporta linear-gradient: usamos color sólido + bgcolor como fallback --}}
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" bgcolor="#b45309" style="background:#b45309;">
            <tr>
                <td bgcolor="#b45309" style="padding:24px 28px;background:#b45309;color:#ffffff;font-family:system-ui,-apple-system,'Segoe UI',sans-serif;">
                    <div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#fef3c7;">Factura electrónica</div>
                    <div style="font-size:22px;font-weight:800;margin-top:6px;color:#ffffff;">{{ $empresa->razon_social }}</div>
                </td>
            </tr>
        </table>

        <div style="padding:28px;">
            <p style="font-size:1rem;margin:0 0 12px;">Hola <strong>{{ $factura->contacto?->nombreDisplay() ?? 'cliente' }}</strong>,</p>
            <p style="margin:0 0 20px;">
                Adjuntamos tu factura <strong>{{ $factura->numero }}</strong>@if($factura->numero_siigo) (DIAN <strong>{{ $factura->numero_siigo }}</strong>)@endif por
                <strong style="color:#b45309;font-size:1.15rem;">${{ number_format((float) $factura->total, 0, ',', '.') }} COP</strong>.
            </p>

            <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:16px 20px;margin:20px 0;">
                <table style="width:100%;font-size:.9rem;">
                    <tr>
                        <td style="color:#6b7280;padding:4px 0;">Fecha emisión:</td>
                        <td style="text-align:right;font-weight:600;">{{ $factura->fecha_emision?->format('Y-m-d') }}</td>
                    </tr>
                    <tr>
                        <td style="color:#6b7280;padding:4px 0;">Fecha vencimiento:</td>
                        <td style="text-align:right;font-weight:600;color:{{ $factura->fecha_vencimiento?->lt(now()) ? '#ef4444' : '#111' }};">
                            {{ $factura->fecha_vencimiento?->format('Y-m-d') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="color:#6b7280;padding:4px 0;">Total:</td>
                        <td style="text-align:right;font-weight:800;color:#b45309;">${{ number_format((float) $factura->total, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td style="color:#6b7280;padding:4px 0;">Saldo pendiente:</td>
                        <td style="text-align:right;font-weight:700;color:{{ $factura->saldo > 0 ? '#ef4444' : '#10b981' }};">
                            ${{ number_format((float) $factura->saldo, 0, ',', '.') }}
                        </td>
                    </tr>
                </table>
            </div>

            {{-- Botón bulletproof para Outlook: table+bgcolor sólido, sin gradient --}}
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:24px auto;">
                <tr>
                    <td bgcolor="#b45309" style="border-radius:6px;background:#b45309;">
                        <a href="{{ $linkPublico }}" target="_blank"
                           style="display:inline-block;padding:14px 32px;color:#ffffff !important;text-decoration:none;font-weight:700;letter-spacing:.5px;font-family:system-ui,'Segoe UI',sans-serif;font-size:15px;border-radius:6px;">
                            📄 DESCARGAR FACTURA (PDF)
                        </a>
                    </td>
                </tr>
            </table>

            @if($factura->es_electronica)
                <div style="margin-top:24px;padding:14px 18px;background:#ecfdf5;border-left:4px solid #10b981;border-radius:4px;color:#065f46;font-size:.85rem;">
                    ✅ Esta factura fue emitida electrónicamente y cumple con las normas DIAN.
                    @if($factura->qr_url)
                        <br>Puedes verificarla en <a href="{{ $factura->qr_url }}" style="color:#065f46;">catalogo-vpfe.dian.gov.co</a>.
                    @endif
                </div>
            @endif

            @if($empresa->banco_nombre)
                <div style="margin-top:16px;padding:12px 16px;background:#f3f4f6;border-radius:4px;font-size:.85rem;color:#4b5563;">
                    <strong>Datos para pago:</strong><br>
                    Banco {{ $empresa->banco_nombre }}
                    @if($empresa->banco_cuenta) · Cuenta {{ $empresa->banco_cuenta }}@endif
                    @if($empresa->banco_moneda) · {{ $empresa->banco_moneda }}@endif
                </div>
            @endif

            <p style="margin-top:24px;font-size:.85rem;color:#6b7280;">
                Cualquier duda, respondé este correo o contactanos al {{ $empresa->telefono ?: 'nuestro canal habitual' }}.
                <br>Gracias por tu confianza · <strong>{{ $empresa->razon_social }}</strong>
            </p>
        </div>

        <div style="padding:14px 28px;background:#f9fafb;border-top:1px solid #e5e7eb;font-size:.75rem;color:#9ca3af;text-align:center;">
            {{ $empresa->pie_pdf ?: 'Enviado por el ERP de ' . $empresa->razon_social }}
        </div>
    </div>
</body>
</html>
