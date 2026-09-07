<x-filament-panels::page>
    <div style="display:flex;gap:1rem;align-items:end;flex-wrap:wrap;margin-bottom:1.25rem;">
        <div style="flex:1;min-width:280px;">
            {{ $this->form }}
        </div>
    </div>

    @foreach($this->getHeaderWidgets() as $widget)
        @livewire($widget)
    @endforeach
</x-filament-panels::page>
