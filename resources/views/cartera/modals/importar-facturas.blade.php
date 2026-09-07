<div style="display:flex;flex-direction:column;gap:1.25rem;padding:.5rem 0;">

    <div style="padding:1rem;border:1px solid rgba(59,130,246,.3);border-radius:.75rem;background:rgba(59,130,246,.06);">
        <div style="display:flex;align-items:flex-start;gap:.75rem;">
            <div style="width:2.25rem;height:2.25rem;border-radius:9999px;background:#3b82f6;color:#fff;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;">1</div>
            <div style="flex:1;">
                <div style="font-weight:700;font-size:1rem;">Descarga la plantilla</div>
                <div style="font-size:.85rem;color:#9ca3af;margin-top:.25rem;">Trae ejemplos con clientes B2B ya seedeados. Cada fila es una factura.</div>
                <a href="#" onclick="alert('Plantilla en camino — próximo sprint.'); return false;"
                   style="display:inline-flex;align-items:center;gap:.5rem;margin-top:.75rem;padding:.625rem 1rem;background:#3b82f6;color:#fff;border-radius:.5rem;text-decoration:none;font-weight:600;">
                    ⬇️ Descargar plantilla (.xlsx)
                </a>
            </div>
        </div>
    </div>

    <div style="padding:1rem;border:1px solid rgba(156,163,175,.3);border-radius:.75rem;">
        <div style="display:flex;align-items:flex-start;gap:.75rem;">
            <div style="width:2.25rem;height:2.25rem;border-radius:9999px;background:#6b7280;color:#fff;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;">2</div>
            <div style="flex:1;">
                <div style="font-weight:700;font-size:1rem;">Columnas esperadas</div>
                <table style="width:100%;font-size:.8rem;margin-top:.75rem;border-collapse:collapse;">
                    <thead><tr style="background:rgba(156,163,175,.1);">
                        <th style="text-align:left;padding:.375rem .5rem;">Columna</th>
                        <th style="text-align:left;padding:.375rem .5rem;">Obligatorio</th>
                        <th style="text-align:left;padding:.375rem .5rem;">Ejemplo</th>
                    </tr></thead>
                    <tbody>
                        <tr><td style="padding:.375rem .5rem;font-family:ui-monospace,monospace;">numero</td><td>Sí</td><td>FV-000123</td></tr>
                        <tr><td style="padding:.375rem .5rem;font-family:ui-monospace,monospace;">documento_cliente</td><td>Sí</td><td>900123456 (debe existir)</td></tr>
                        <tr><td style="padding:.375rem .5rem;font-family:ui-monospace,monospace;">fecha_emision</td><td>Sí</td><td>2026-09-05</td></tr>
                        <tr><td style="padding:.375rem .5rem;font-family:ui-monospace,monospace;">fecha_vencimiento</td><td>Sí</td><td>2026-10-05</td></tr>
                        <tr><td style="padding:.375rem .5rem;font-family:ui-monospace,monospace;">subtotal · impuestos · total</td><td>Sí</td><td>850000 · 161500 · 1011500</td></tr>
                        <tr><td style="padding:.375rem .5rem;font-family:ui-monospace,monospace;">observaciones</td><td>No</td><td>Referencia OC-2025-001</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div style="padding:1rem;border:1px solid rgba(16,185,129,.3);border-radius:.75rem;background:rgba(16,185,129,.06);">
        <div style="display:flex;align-items:flex-start;gap:.75rem;">
            <div style="width:2.25rem;height:2.25rem;border-radius:9999px;background:#10b981;color:#fff;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;">3</div>
            <div style="flex:1;">
                <div style="font-weight:700;font-size:1rem;">Sube y confirma</div>
                <div style="font-size:.85rem;color:#9ca3af;margin-top:.25rem;">El importador está listo en el próximo sprint. Mientras tanto usa "Crear Factura" para pruebas.</div>
            </div>
        </div>
    </div>

</div>
