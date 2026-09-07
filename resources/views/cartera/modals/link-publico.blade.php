<div style="padding:.5rem 0;">
    <p style="color:#6b7280;font-size:.9rem;margin-bottom:.5rem;">
        Enviá este link al cliente por WhatsApp o correo. Descarga el PDF de la factura sin iniciar sesión.
    </p>
    <div style="padding:.5rem .75rem;background:rgba(245,158,11,.1);border-left:3px solid #f59e0b;border-radius:.35rem;font-size:.8rem;color:#92400e;margin-bottom:.75rem;">
        ⚠️ <strong>No lo publiques en redes sociales ni en foros.</strong>
        Cualquiera con el link puede ver esta factura.
    </div>

    <div style="display:flex;gap:.5rem;align-items:center;">
        <input type="text" readonly value="{{ $url }}"
               onclick="this.select();document.execCommand('copy');"
               style="flex:1;padding:.6rem .75rem;border:1px solid rgba(156,163,175,.4);border-radius:.4rem;font-family:ui-monospace,monospace;font-size:.85rem;background:#f9fafb;color:#111827;">
        <button type="button" id="btn-copiar-link"
                onclick="const b=this;navigator.clipboard.writeText('{{ $url }}').then(()=>{b.textContent='✓ Copiado';setTimeout(()=>b.textContent='Copiar',2000);})"
                style="padding:.6rem 1rem;background:#f59e0b;color:white;border:0;border-radius:.4rem;cursor:pointer;font-weight:600;min-width:88px;">
            Copiar
        </button>
    </div>
    <a href="https://wa.me/?text={{ urlencode('Aquí está tu factura: ' . $url) }}" target="_blank" rel="noopener"
       style="display:inline-block;margin-top:.75rem;padding:.5rem 1rem;background:#25d366;color:white;text-decoration:none;border-radius:.4rem;font-weight:600;font-size:.9rem;">
        📱 Compartir por WhatsApp
    </a>
</div>
