<?php

namespace App\Filament\Admin\Pages;

use App\Models\EmpresaConfig;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ConfiguracionEmpresa extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Empresa';

    protected static ?string $title = 'Configuración de la empresa';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'configuracion-empresa';

    protected string $view = 'admin.pages.configuracion-empresa';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->esAracely() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(EmpresaConfig::current()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Empresa')->tabs([

                Tab::make('Identidad')->icon('heroicon-o-identification')->schema([
                    Section::make()->schema([
                        TextInput::make('razon_social')->required()->maxLength(200),
                        TextInput::make('nombre_comercial')->maxLength(200),
                        TextInput::make('nit')->required()->helperText('Con guión y dígito de verificación (ej: 901.738.354-7).'),
                        Select::make('regimen')->options([
                            'Responsable de IVA' => 'Responsable de IVA',
                            'No responsable de IVA' => 'No responsable de IVA',
                            'Régimen simple' => 'Régimen simple',
                            'Gran contribuyente' => 'Gran contribuyente',
                            'Exportador' => 'Exportador',
                        ])->required(),
                        TextInput::make('actividad_economica')->label('CIIU')->maxLength(10),
                        FileUpload::make('logo_path')->image()->directory('empresa')->maxSize(2048),
                    ])->columns(2),
                ]),

                Tab::make('Contacto')->icon('heroicon-o-map-pin')->schema([
                    Section::make()->schema([
                        TextInput::make('direccion')->columnSpan(2),
                        TextInput::make('ciudad'),
                        TextInput::make('departamento'),
                        TextInput::make('pais')->default('Colombia'),
                        TextInput::make('telefono')->tel(),
                        TextInput::make('email')->email(),
                        TextInput::make('web')->url(),
                    ])->columns(3),
                ]),

                Tab::make('Resolución DIAN')->icon('heroicon-o-shield-check')->schema([
                    Section::make('Factura electrónica de venta')->schema([
                        TextInput::make('resolucion_dian')->label('Nº resolución'),
                        DatePicker::make('resolucion_desde')->native(false),
                        DatePicker::make('resolucion_hasta')->native(false),
                        TextInput::make('prefijo_dian')->label('Prefijo'),
                        TextInput::make('rango_desde')->numeric(),
                        TextInput::make('rango_hasta')->numeric(),
                    ])->columns(3),
                ]),

                Tab::make('Banco')->icon('heroicon-o-banknotes')->schema([
                    Section::make('Cuenta principal (para pagos B2B / exportación)')->schema([
                        TextInput::make('banco_nombre')->label('Banco'),
                        TextInput::make('banco_cuenta')->label('Cuenta'),
                        TextInput::make('banco_swift')->label('SWIFT/BIC'),
                        TextInput::make('banco_iban')->label('IBAN'),
                        Select::make('banco_moneda')->options([
                            'COP' => 'COP · Peso colombiano',
                            'USD' => 'USD · Dólar',
                            'EUR' => 'EUR · Euro',
                        ]),
                    ])->columns(2),
                    Section::make('Contacto financiero')->schema([
                        TextInput::make('financiero_nombre'),
                        TextInput::make('financiero_email')->email(),
                        TextInput::make('financiero_telefono')->tel(),
                    ])->columns(3),
                ]),

                Tab::make('PDF')->icon('heroicon-o-document-text')->schema([
                    Textarea::make('pie_pdf')->rows(3)->helperText('Se muestra al pie del PDF de la factura.'),
                ]),

            ])->columnSpanFull(),
        ])->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('guardar')->label('Guardar')->color('primary')->icon('heroicon-o-check')
                ->action(function () {
                    $data = $this->form->getState();
                    $empresa = EmpresaConfig::current();
                    $empresa->fill($data)->save();
                    Notification::make()->title('Configuración de empresa guardada')->success()->send();
                }),
        ];
    }
}
