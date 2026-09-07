<?php

namespace App\Modules\Dropi\Filament\Resources\DropiDevolucionResource\Pages;

use App\Modules\Dropi\Actions\RegistrarDevolucion as RegistrarDevolucionAction;
use App\Modules\Dropi\Enums\DestinoDevolucion;
use App\Modules\Dropi\Filament\Resources\DropiDevolucionResource;
use App\Modules\Dropi\Models\DropiPedido;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RegistrarDevolucion extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = DropiDevolucionResource::class;

    protected static ?string $title = 'Registrar devolución';

    protected string $view = 'dropi.pages.registrar-devolucion';

    public ?array $data = [];

    public ?DropiPedido $pedido = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('1 · Escanear o ingresar guía')->schema([
                TextInput::make('guia')
                    ->label('Guía Dropi')
                    ->placeholder('GUI-000001')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state) {
                        $this->pedido = $state ? DropiPedido::with('items.variante')->where('guia', $state)->first() : null;
                        if ($state && ! $this->pedido) {
                            Notification::make()->title("Guía {$state} no encontrada")->danger()->send();
                        }
                    }),
            ]),
            Section::make('3 · Decidir destino del inventario')
                ->description('Elige según la verificación física del producto')
                ->schema([
                    Select::make('destino')
                        ->label('Destino')
                        ->options(collect(DestinoDevolucion::cases())->mapWithKeys(fn ($d) => [$d->value => $d->label()]))
                        ->required(),
                    Textarea::make('notas')->columnSpanFull(),
                ])
                ->visible(fn () => $this->pedido !== null),
        ])->statePath('data');
    }

    public function registrar(): void
    {
        $data = $this->form->getState();

        try {
            $result = RegistrarDevolucionAction::run(
                guia: $data['guia'],
                destino: DestinoDevolucion::from($data['destino']),
                userId: auth()->id(),
                notas: $data['notas'] ?? null,
            );

            Notification::make()
                ->title('Devolución registrada')
                ->body("Guía {$data['guia']} · destino: " . DestinoDevolucion::from($data['destino'])->label() . " · movimientos: {$result['movimientos']}")
                ->success()
                ->send();

            $this->redirect(DropiDevolucionResource::getUrl('index'));
        } catch (\Throwable $e) {
            Notification::make()->title('No se pudo registrar')->body($e->getMessage())->danger()->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('registrar')
                ->label('Registrar devolución')
                ->color('primary')
                ->action('registrar'),
        ];
    }
}
