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
        // Re-audit M3 λ (SEG-B1) · alinear autorización con el controller web:
        //   Aracely/Gerencia siempre; Alistador si la toma está en una de sus
        //   bodegas asignadas. Antes: sólo `esAracely()` bloqueaba a Contador
        //   que sí puede cerrar la toma → matriz incoherente.
        $u = auth()->user();
        $this->toma = $toma;
        $this->tomaModel = TomaFisica::with(['items.variante.producto', 'ubicacion'])->findOrFail($toma);

        $puede = $u && ($u->esAracely() || $u->esContable());
        if (! $puede && $u && $u->esAlistador()) {
            $puede = in_array((int) $this->tomaModel->ubicacion_id, $u->bodegasAsignadasIds(), true);
        }
        abort_unless($puede, 403, 'No autorizado para operar esta toma.');

        foreach ($this->tomaModel->items as $item) {
            $this->cantidades[$item->id] = $item->cantidad_contada;
        }
    }

    public function guardar(int $itemId): void
    {
        // Re-audit M3 λ (FUNC-C7) · IDOR fix.
        //   Antes: `findOrFail($itemId)` sin verificar toma ni estado. Un
        //   usuario podía sobrescribir cantidad_contada de tomas AJENAS o
        //   ya cerradas (Ajustada) desde la URL de otra toma abierta.
        $item = TomaFisicaItem::findOrFail($itemId);
        abort_unless(
            $item->toma_id === $this->tomaModel->id
            && $this->tomaModel->estado === EstadoTomaFisica::EnConteo,
            403,
            'Sólo se pueden capturar cantidades de items de la toma actual y mientras esté En Conteo.'
        );

        $raw = $this->cantidades[$itemId] ?? null;
        $item->cantidad_contada = ($raw === null || $raw === '') ? null : round((float) $raw, 4);
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
        $u = auth()->user();
        if (! $u) return false;
        return $u->esAracely() || $u->esContable() || $u->esAlistador();
    }
}
