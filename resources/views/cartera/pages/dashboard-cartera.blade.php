<x-filament-panels::page>
    @foreach($this->getHeaderWidgets() as $widget)
        @livewire($widget)
    @endforeach
</x-filament-panels::page>
