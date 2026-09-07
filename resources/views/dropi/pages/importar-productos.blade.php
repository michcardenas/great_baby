<x-filament-panels::page>
    <form wire:submit="procesar">
        <x-filament::section>
            <x-slot name="heading">Importar productos</x-slot>
            <x-slot name="description">
                Sube un Excel o CSV con productos. Puede incluir variantes en la misma fila.
                <strong>Recomendado:</strong> descarga primero la plantilla para no equivocarte con las columnas.
            </x-slot>

            <div style="margin-bottom:1rem;">
                <a href="{{ route('dropi.plantilla.productos') }}" style="display:inline-flex;align-items:center;gap:.5rem;padding:.625rem 1rem;background:rgba(59,130,246,.15);color:#3b82f6;border-radius:.5rem;text-decoration:none;font-weight:600;">
                    <x-filament::icon icon="heroicon-o-arrow-down-tray" style="width:1.125rem;height:1.125rem;" />
                    Descargar plantilla (.xlsx)
                </a>
                <span style="margin-left:.75rem;font-size:.85rem;color:#9ca3af;">
                    Trae 3 filas de ejemplo con variantes válidas.
                </span>
            </div>

            {{ $this->form }}
        </x-filament::section>

        @if($previewCalculado)
            <x-filament::section style="margin-top:1rem;">
                <x-slot name="heading">Previsualización</x-slot>
                <x-slot name="description">Esto es lo que va a pasar si confirmas. Nada se ha aplicado aún.</x-slot>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;">
                    <div style="padding:1rem;border:1px solid rgba(16,185,129,.3);border-radius:.5rem;background:rgba(16,185,129,.08);">
                        <div style="font-size:.7rem;color:#10b981;text-transform:uppercase;">Productos nuevos</div>
                        <div style="font-size:1.75rem;font-weight:700;color:#10b981;">{{ $preview['nuevos'] ?? 0 }}</div>
                    </div>
                    <div style="padding:1rem;border:1px solid rgba(59,130,246,.3);border-radius:.5rem;background:rgba(59,130,246,.08);">
                        <div style="font-size:.7rem;color:#3b82f6;text-transform:uppercase;">Actualizados</div>
                        <div style="font-size:1.75rem;font-weight:700;color:#3b82f6;">{{ $preview['actualizados'] ?? 0 }}</div>
                    </div>
                    <div style="padding:1rem;border:1px solid rgba(245,158,11,.3);border-radius:.5rem;background:rgba(245,158,11,.08);">
                        <div style="font-size:.7rem;color:#f59e0b;text-transform:uppercase;">Variantes</div>
                        <div style="font-size:1.75rem;font-weight:700;color:#f59e0b;">{{ $preview['variantes'] ?? 0 }}</div>
                    </div>
                </div>

                @if(count($errores) > 0)
                    <div style="margin-top:1.25rem;">
                        <div style="font-weight:600;color:#dc2626;margin-bottom:.5rem;">⚠️ {{ count($errores) }} filas con problemas</div>
                        <div style="max-height:220px;overflow-y:auto;border:1px solid rgba(220,38,38,.25);border-radius:.5rem;">
                            <table style="width:100%;font-size:.85rem;border-collapse:collapse;">
                                <thead>
                                    <tr style="background:rgba(220,38,38,.1);">
                                        <th style="text-align:left;padding:.5rem;">Fila</th>
                                        <th style="text-align:left;padding:.5rem;">Referencia</th>
                                        <th style="text-align:left;padding:.5rem;">Problema</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($errores as $e)
                                        <tr style="border-top:1px solid rgba(220,38,38,.15);">
                                            <td style="padding:.5rem;font-family:ui-monospace,monospace;">{{ $e['fila'] }}</td>
                                            <td style="padding:.5rem;font-family:ui-monospace,monospace;">{{ $e['sku'] }}</td>
                                            <td style="padding:.5rem;color:#ef4444;">{{ $e['mensaje'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </x-filament::section>
        @endif

        <div style="margin-top:1.25rem;display:flex;gap:.75rem;flex-wrap:wrap;">
            @foreach($this->getFormActions() as $action)
                {{ $action }}
            @endforeach
        </div>
    </form>
</x-filament-panels::page>
