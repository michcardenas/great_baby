<div style="display:flex;flex-direction:column;gap:1.25rem;padding:.5rem 0;">

    {{-- PASO 1: Descargar plantilla --}}
    <div style="padding:1rem;border:1px solid rgba(59,130,246,.3);border-radius:.75rem;background:rgba(59,130,246,.06);">
        <div style="display:flex;align-items:flex-start;gap:.75rem;">
            <div style="width:2.25rem;height:2.25rem;border-radius:9999px;background:#3b82f6;color:#fff;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;">1</div>
            <div style="flex:1;">
                <div style="font-weight:700;font-size:1rem;">Descarga la plantilla</div>
                <div style="font-size:.85rem;color:#9ca3af;margin-top:.25rem;">Trae 3 filas de ejemplo con clientes B2B, proveedor y vendedor Dropi. Ábrela en Excel, no cambies los encabezados.</div>
                <a href="{{ route('cartera.plantilla.contactos') }}"
                   style="display:inline-flex;align-items:center;gap:.5rem;margin-top:.75rem;padding:.625rem 1rem;background:#3b82f6;color:#fff;border-radius:.5rem;text-decoration:none;font-weight:600;">
                    ⬇️ Descargar plantilla (.xlsx)
                </a>
            </div>
        </div>
    </div>

    {{-- PASO 2: Columnas explicadas --}}
    <div style="padding:1rem;border:1px solid rgba(156,163,175,.3);border-radius:.75rem;">
        <div style="display:flex;align-items:flex-start;gap:.75rem;">
            <div style="width:2.25rem;height:2.25rem;border-radius:9999px;background:#6b7280;color:#fff;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;">2</div>
            <div style="flex:1;">
                <div style="font-weight:700;font-size:1rem;">Llena los datos siguiendo las columnas</div>
                <table style="width:100%;font-size:.8rem;margin-top:.75rem;border-collapse:collapse;">
                    <thead><tr style="background:rgba(156,163,175,.1);">
                        <th style="text-align:left;padding:.375rem .5rem;">Columna</th>
                        <th style="text-align:left;padding:.375rem .5rem;">Obligatorio</th>
                        <th style="text-align:left;padding:.375rem .5rem;">Ejemplo</th>
                    </tr></thead>
                    <tbody>
                        <tr><td style="padding:.375rem .5rem;font-family:ui-monospace,monospace;">tipo_documento</td><td>Sí</td><td>CC · CE · NIT · PP</td></tr>
                        <tr><td style="padding:.375rem .5rem;font-family:ui-monospace,monospace;">numero_documento</td><td>Sí</td><td>1094567890</td></tr>
                        <tr><td style="padding:.375rem .5rem;font-family:ui-monospace,monospace;">nombre_completo</td><td>Sí</td><td>María Pérez López</td></tr>
                        <tr><td style="padding:.375rem .5rem;font-family:ui-monospace,monospace;">razon_social</td><td>No</td><td>Distribuidora Bogotá SAS</td></tr>
                        <tr><td style="padding:.375rem .5rem;font-family:ui-monospace,monospace;">email · telefono · direccion · ciudad · departamento</td><td>No</td><td>—</td></tr>
                        <tr><td style="padding:.375rem .5rem;font-family:ui-monospace,monospace;">es_cliente · es_cliente_b2b · es_proveedor · es_vendedor_dropi · es_empleado</td><td>No</td><td>true · false · 1 · 0</td></tr>
                        <tr><td style="padding:.375rem .5rem;font-family:ui-monospace,monospace;">cupo</td><td>No (solo B2B)</td><td>2000000</td></tr>
                        <tr><td style="padding:.375rem .5rem;font-family:ui-monospace,monospace;">plazo_dias</td><td>No (solo B2B)</td><td>30</td></tr>
                    </tbody>
                </table>
                <div style="margin-top:.5rem;font-size:.75rem;color:#9ca3af;">💡 Si <code>es_cliente_b2b=true</code> y traes <code>cupo</code>+<code>plazo_dias</code>, se crea la condición de crédito automáticamente.</div>
            </div>
        </div>
    </div>

    {{-- PASO 3: Subir con preview --}}
    <div style="padding:1rem;border:1px solid rgba(16,185,129,.3);border-radius:.75rem;background:rgba(16,185,129,.06);">
        <div style="display:flex;align-items:flex-start;gap:.75rem;">
            <div style="width:2.25rem;height:2.25rem;border-radius:9999px;background:#10b981;color:#fff;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;">3</div>
            <div style="flex:1;">
                <div style="font-weight:700;font-size:1rem;">Sube el archivo y revisa el preview</div>
                <div style="font-size:.85rem;color:#9ca3af;margin-top:.25rem;">Verás cuántos crean, cuántos actualizan y los errores por fila ANTES de confirmar. Nada se aplica hasta que confirmes.</div>
                <a href="/admin/importar-contactos"
                   style="display:inline-flex;align-items:center;gap:.5rem;margin-top:.75rem;padding:.625rem 1rem;background:#10b981;color:#fff;border-radius:.5rem;text-decoration:none;font-weight:600;">
                    Ir al importador →
                </a>
            </div>
        </div>
    </div>

</div>
