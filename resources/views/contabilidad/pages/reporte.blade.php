<x-filament-panels::page>
    @php
        $columnas = $this->getColumnas();
        $filas = $this->getFilas();
        $filtros = $this->filtrosDisponibles();
        $inputCss = 'padding:.45rem .6rem;border:1px solid rgba(156,163,175,.35);border-radius:.5rem;background:transparent;color:inherit;font-size:.85rem;';
        $labelCss = 'font-size:.68rem;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;';
    @endphp

    <div style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap;">
        <a href="{{ url('/admin/reportes-contables') }}" style="padding:.5rem 1rem;background:rgba(156,163,175,.15);border-radius:.5rem;text-decoration:none;color:inherit;font-size:.85rem;">← Volver a reportes</a>
    </div>

    {{-- Filtros inteligentes --}}
    <x-filament::section style="margin-bottom:1rem;">
        <x-slot name="heading">Filtros</x-slot>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;">
            @if($filtros['fecha'])
                <div style="display:flex;flex-direction:column;gap:.25rem;">
                    <label style="{{ $labelCss }}">{{ $filtros['fecha'] }} desde</label>
                    <input type="date" wire:model.live="fechaDesde" style="{{ $inputCss }}">
                </div>
                <div style="display:flex;flex-direction:column;gap:.25rem;">
                    <label style="{{ $labelCss }}">{{ $filtros['fecha'] }} hasta</label>
                    <input type="date" wire:model.live="fechaHasta" style="{{ $inputCss }}">
                </div>
            @endif

            @if($filtros['buscar'])
                <div style="display:flex;flex-direction:column;gap:.25rem;flex:1;min-width:220px;">
                    <label style="{{ $labelCss }}">Buscar</label>
                    <input type="text" wire:model.live.debounce.400ms="buscar"
                           placeholder="{{ $filtros['buscar'] }}" style="{{ $inputCss }};width:100%;">
                </div>
            @endif

            @if($filtros['extra'])
                <div style="display:flex;flex-direction:column;gap:.25rem;">
                    <label style="{{ $labelCss }}">{{ $filtros['extra']['label'] }}</label>
                    <select wire:model.live="filtroExtra" style="{{ $inputCss }}">
                        <option value="">Todos</option>
                        @foreach($filtros['extra']['options'] as $val => $lab)
                            <option value="{{ $val }}">{{ $lab }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <button type="button" wire:click="limpiarFiltros"
                    style="padding:.5rem 1rem;background:rgba(156,163,175,.15);border:1px solid rgba(156,163,175,.35);border-radius:.5rem;color:inherit;font-size:.85rem;cursor:pointer;">
                Limpiar
            </button>

            <div wire:loading style="align-self:center;font-size:.8rem;color:#9ca3af;">Filtrando…</div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">{{ $this->titulo() }}</x-slot>
        <x-slot name="description">{{ count($filas) }} registros · datos en tiempo real desde la operación</x-slot>

        @if(empty($filas))
            <div style="color:#9ca3af;padding:2rem;text-align:center;">Sin datos para los filtros actuales.</div>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%;font-size:.9rem;border-collapse:collapse;min-width:600px;">
                    <thead>
                        <tr style="border-bottom:2px solid rgba(156,163,175,.3);">
                            @foreach($columnas as $c)
                                <th style="text-align:left;padding:.75rem .5rem;font-size:.75rem;text-transform:uppercase;color:#9ca3af;">{{ $c }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($filas as $f)
                        <tr style="border-bottom:1px solid rgba(156,163,175,.1);">
                            @foreach($f as $celda)
                                <td style="padding:.5rem;">{{ $celda }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
