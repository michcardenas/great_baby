<?php

namespace App\Modules\Cartera\Filament\Resources;

use App\Models\Contacto;
use App\Modules\Cartera\Actions\ConsultarCredito;
use App\Modules\Cartera\Filament\Resources\ContactoResource\Pages;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use App\Support\FilamentPolicy\HeredaAutorizacion;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ContactoResource extends Resource
{
    use HeredaAutorizacion;

    protected static ?string $model = Contacto::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Contactos';

    protected static ?string $modelLabel = 'Contacto';

    protected static ?string $pluralModelLabel = 'Contactos (clientes / proveedores / vendedores)';

    protected static string|\UnitEnum|null $navigationGroup = 'Cartera y CRM';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return \App\Auth\Permisos::puede(auth()->user(), 'contactos');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['numero_documento', 'nombre_completo', 'razon_social', 'email'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identificación')->schema([
                Select::make('tipo_documento')
                    ->options(['CC' => 'Cédula', 'CE' => 'Cédula extranjera', 'NIT' => 'NIT empresa', 'PP' => 'Pasaporte'])
                    ->default('CC')->required(),
                TextInput::make('numero_documento')->required()->unique(ignoreRecord: true)->maxLength(30),
                TextInput::make('nombre_completo')->required()->maxLength(180),
                TextInput::make('razon_social')->maxLength(180)->helperText('Si es persona jurídica'),
            ])->columns(2),

            Section::make('Contacto')->schema([
                TextInput::make('email')->email(),
                TextInput::make('telefono')->tel(),
                TextInput::make('direccion')->columnSpanFull(),
                TextInput::make('ciudad'),
                TextInput::make('departamento'),
            ])->columns(2),

            Section::make('Roles')->description('Marca los roles que cumple este contacto en la operación')->schema([
                Toggle::make('es_cliente')->label('Cliente'),
                Toggle::make('es_cliente_b2b')->label('Cliente B2B (con cupo de crédito)'),
                Toggle::make('es_proveedor')->label('Proveedor'),
                Toggle::make('es_vendedor_dropi')->label('Vendedor Dropshipping'),
                Toggle::make('es_empleado')->label('Empleado'),
                Toggle::make('activo')->default(true),
            ])->columns(3),

            Section::make('Fiscal (opcional)')->schema([
                Select::make('regimen_iva')
                    ->options(['responsable' => 'Responsable de IVA', 'no_responsable' => 'No responsable'])
                    ->nullable(),
            ])->columns(2)->collapsible()->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero_documento')->searchable()->weight('bold')->copyable(),
                TextColumn::make('nombre_completo')->searchable()->limit(30),
                TextColumn::make('razon_social')->searchable()->limit(28)->toggleable(),
                TextColumn::make('ciudad')->badge()->toggleable(),
                IconColumn::make('es_cliente_b2b')->boolean()->label('B2B'),
                IconColumn::make('es_proveedor')->boolean()->label('Prov')->toggleable(),
                IconColumn::make('es_vendedor_dropi')->boolean()->label('Drop')->toggleable(),
                TextColumn::make('condicionVigente.cupo')
                    ->label('Cupo')->money('COP')->alignEnd()
                    ->toggleable(),
                TextColumn::make('email')->toggleable()->searchable(),
                TextColumn::make('telefono')->toggleable(),
                IconColumn::make('activo')->boolean(),
            ])
            ->defaultSort('nombre_completo')
            ->filters([
                TernaryFilter::make('es_cliente_b2b')->label('Solo clientes B2B'),
                TernaryFilter::make('es_proveedor')->label('Solo proveedores'),
                TernaryFilter::make('activo')->label('Activos'),
            ])
            ->headerActions([
                Action::make('importar')
                    ->label('Importar contactos')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('info')
                    ->modalHeading('Importar contactos desde Excel/CSV')
                    ->modalDescription('Sigue estos 3 pasos. La plantilla trae ejemplos válidos.')
                    ->modalContent(view('cartera.modals.importar-contactos'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
            ])
            ->recordActions([
                Action::make('estado_cuenta')
                    ->label('Estado cuenta')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->visible(fn (Contacto $r) => $r->es_cliente || $r->es_cliente_b2b)
                    ->url(fn (Contacto $r) => route('cartera.estado-cuenta', $r))
                    ->openUrlInNewTab(),
                Action::make('consultar_credito')
                    ->label('Cupo')
                    ->icon('heroicon-o-credit-card')
                    ->color('info')
                    ->visible(fn (Contacto $r) => $r->es_cliente_b2b)
                    ->action(function (Contacto $r) {
                        $c = ConsultarCredito::run($r->id);
                        $body = "Cupo: $" . number_format($c['cupo'], 0, ',', '.') .
                                " · Saldo: $" . number_format($c['saldo_cartera'], 0, ',', '.') .
                                " · Disponible: $" . number_format($c['disponible'], 0, ',', '.');
                        if ($c['tiene_mora_critica']) {
                            $body .= " · ⚠️ Mora crítica {$c['dias_mora_max']}d";
                        }
                        Notification::make()->title('Consulta de crédito')->body($body)
                            ->color($c['tiene_mora_critica'] ? 'danger' : 'success')->send();
                    }),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction::make()
                        ->exports([
                            \pxlrbt\FilamentExcel\Exports\ExcelExport::make('contactos')
                                ->fromTable()
                                ->withFilename('contactos-' . now()->format('Y-m-d')),
                        ]),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContactos::route('/'),
            'create' => Pages\CreateContacto::route('/create'),
            'view' => Pages\ViewContacto::route('/{record}'),
            'edit' => Pages\EditContacto::route('/{record}/edit'),
        ];
    }
}
