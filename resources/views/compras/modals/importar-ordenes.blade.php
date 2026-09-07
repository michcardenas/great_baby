{{-- Modal importar OC estilo TalentMap: 3 pasos + plantilla + explicación --}}
<div style="max-width:640px;">
    <ol style="display:flex;flex-direction:column;gap:1rem;list-style:none;padding:0;">
        <li style="display:flex;gap:.75rem;">
            <div style="width:2rem;height:2rem;border-radius:9999px;background:#b45309;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;">1</div>
            <div style="flex:1;">
                <div style="font-weight:600;">Descarga la plantilla</div>
                <div style="color:#9ca3af;font-size:.85rem;margin-bottom:.5rem;">Excel con las columnas requeridas y un ejemplo pre-llenado.</div>
                <a href="{{ route('compras.plantilla.oc') }}" target="_blank"
                   style="display:inline-block;padding:.5rem .875rem;background:rgba(180,83,9,.15);color:#b45309;border-radius:.5rem;text-decoration:none;font-weight:600;font-size:.85rem;">
                    ⬇ Descargar plantilla
                </a>
            </div>
        </li>

        <li style="display:flex;gap:.75rem;">
            <div style="width:2rem;height:2rem;border-radius:9999px;background:#b45309;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;">2</div>
            <div style="flex:1;">
                <div style="font-weight:600;">Llena la plantilla</div>
                <ul style="color:#9ca3af;font-size:.85rem;margin:.25rem 0 .5rem 1rem;">
                    <li>Una fila por cada ítem. Las filas con el mismo <code>numero_oc</code> se agrupan en una sola OC.</li>
                    <li><strong>proveedor_nit</strong> debe existir en Contactos (marcado como proveedor).</li>
                    <li><strong>producto_referencia</strong> debe existir en Catálogo · Productos.</li>
                    <li><strong>tipo</strong>: <code>nacional</code> o <code>importacion</code>.</li>
                </ul>
            </div>
        </li>

        <li style="display:flex;gap:.75rem;">
            <div style="width:2rem;height:2rem;border-radius:9999px;background:#b45309;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;">3</div>
            <div style="flex:1;">
                <div style="font-weight:600;">Sube el archivo</div>
                <div style="color:#9ca3af;font-size:.85rem;margin-bottom:.5rem;">
                    Los ítems se validan uno a uno; si un ítem falla el resto se procesa.
                </div>
                <form method="POST" action="{{ route('compras.import.oc') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="file" name="archivo" accept=".xlsx,.xls,.csv" required
                           style="padding:.5rem;border:1px dashed #b45309;border-radius:.5rem;width:100%;">
                    <button type="submit" style="margin-top:.5rem;padding:.5rem 1rem;background:#b45309;color:#fff;border:0;border-radius:.5rem;font-weight:600;cursor:pointer;">
                        Procesar Excel
                    </button>
                </form>
            </div>
        </li>
    </ol>

    <div style="margin-top:1rem;padding:.75rem;background:rgba(59,130,246,.1);border-left:3px solid #3b82f6;font-size:.8rem;color:#9ca3af;">
        💡 Tip: el historial de importaciones se registra en la bitácora del sistema, con detalle de qué filas se aceptaron o rechazaron.
    </div>
</div>
