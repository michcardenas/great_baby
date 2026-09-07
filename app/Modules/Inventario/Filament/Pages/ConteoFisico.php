<?php

namespace App\Modules\Inventario\Filament\Pages;

use App\Modules\Inventario\Enums\EstadoTomaFisica;
use App\Modules\Inventario\Filament\Resources\TomaFisicaResource;
use App\Modules\Inventario\Models\TomaFisica;
use App\Modules\Inventario\Models\TomaFisicaItem;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

/**
 * Página interactiva para capturar el conteo físico línea a línea.
 * Se accede desde el botón "Iniciar conteo" en la lista de tomas.
 */
class ConteoFisico extends Page
{
    protected string $view = 'inventario.pages.conteo-fisico';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'conteo-fisico/{toma}';

    #[Url]
    public ?int $toma = null;

    public ?TomaFisica $tomaModel = null;

    public string $buscar = '';

    public array $cantidades = [];

    public function mount(int $toma): void
    {
        abort_unless(auth()->user()?->esAracely(), 403);

        $this->toma = $toma;
        $this->tomaModel = TomaFisica::with(['items.variante.producto', 'ubicacion'])->findOrFail($toma);

        foreach ($this->tomaModel->items as $item) {
            $this->cantidades[$item->id] = $item->cantidad_contada;
        }
    }

    public function guardar(int $itemId): void
    {
        $item = TomaFisicaItem::findOrFail($itemId);
        $item->cantidad_contada = $this->cantidades[$itemId] !== null ? (int) $this->cantidades[$itemId] : null;
        $item->save();

        Notification::make()->title('Cantidad guardada')->success()->send();
    }

    public function itemsFiltrados()
    {
        $q = $this->tomaModel->items;
        if ($this->buscar) {
            $t = strtolower($this->buscar);
            $q = $q->filter(fn ($i) => str_contains(strtolower($i->variante?->codigo_barras ?? ''), $t)
                || str_contains(strtolower($i->variante?->producto?->nombre ?? ''), $t));
        }

        return $q->take(50);
    }

    public function totalItems(): int
    {
        return $this->tomaModel->items->count();
    }

    public function itemsContados(): int
    {
        return $this->tomaModel->items->whereNotNull('cantidad_contada')->count();
    }

    public function porcentaje(): int
    {
        $t = $this->totalItems();

        return $t > 0 ? (int) round($this->itemsContados() / $t * 100) : 0;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->esAracely() ?? false;
    }
}
