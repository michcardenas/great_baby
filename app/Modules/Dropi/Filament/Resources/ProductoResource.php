<?php

namespace App\Modules\Dropi\Filament\Resources;

use App\Modules\Dropi\Filament\Resources\ProductoResource\Pages;
use App\Modules\Dropi\Models\Producto;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use App\Support\FilamentPolicy\HeredaAutorizacion;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductoResource extends Resource
{
    use HeredaAutorizacion;

    protected static ?string $model = Producto::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Productos';

    protected static ?string $modelLabel = 'Producto';

    protected static ?string $pluralModelLabel = 'Productos';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->esAracely() ?? false;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['referencia', 'nombre', 'categoria'];
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return ['Ref' => $record->referencia, 'Precio' => '$' . number_format((float) $record->precio_proveedor, 0, ',', '.')];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Tabs::make('tabs')->tabs([
                \Filament\Schemas\Components\Tabs\Tab::make('Información general')->icon('heroicon-o-information-circle')->schema([
                    TextInput::make('referencia')->required()->unique(ignoreRecord: true)->maxLength(100)
                        ->helperText('Código general del proveedor. Ej: AND2512-79/154'),
                    TextInput::make('nombre')->required(),
                    \Filament\Forms\Components\Select::make('marca_id')->relationship('marca', 'nombre')->searchable()->preload(),
                    \Filament\Forms\Components\Select::make('categoria_id')->label('Categoría')
                        ->relationship('categoriaMaestra', 'nombre')->searchable()->preload(),
                    \Filament\Forms\Components\Select::make('coleccion_id')->label('Colección')
                        ->relationship('coleccion', 'nombre')->searchable()->preload(),
                    \Filament\Forms\Components\Select::make('unidad_medida_id')->label('Unidad medida')
                        ->relationship('unidadMedida', 'nombre')->searchable()->preload(),
                    \Filament\Forms\Components\Select::make('impuesto_id')->label('Impuesto')
                        ->relationship('impuesto', 'nombre')->searchable()->preload(),
                    TextInput::make('precio_proveedor')->numeric()->prefix('$')->required(),
                    Textarea::make('descripcion')->columnSpanFull(),
                ])->columns(2),

                \Filament\Schemas\Components\Tabs\Tab::make('Reglas y dimensiones')->icon('heroicon-o-cog-6-tooth')->schema([
                    Toggle::make('activo')->default(true),
                    Toggle::make('requiere_talla')->label('Requiere talla'),
                    Toggle::make('es_set')->label('Es set/kit'),
                    Toggle::make('neto')->label('Neto (aplica descuentos y flete)')->default(true)
                        ->helperText('Si NO es neto, quedará protegido de descuentos comerciales'),
                    TextInput::make('peso_gr')->numeric()->suffix('g')->label('Peso'),
                    TextInput::make('alto_cm')->numeric()->suffix('cm')->label('Alto'),
                    TextInput::make('ancho_cm')->numeric()->suffix('cm')->label('Ancho'),
                    TextInput::make('largo_cm')->numeric()->suffix('cm')->label('Largo'),
                ])->columns(2),

                \Filament\Schemas\Components\Tabs\Tab::make('Imágenes')->icon('heroicon-o-photo')->schema([
                    \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('imagenes')
                        ->collection('imagenes')
                        ->multiple()->image()->imageEditor()->reorderable()->downloadable()
                        ->columnSpanFull(),
                ]),

                \Filament\Schemas\Components\Tabs\Tab::make('Sincronización SIIGO')->icon('heroicon-o-cloud')->schema([
                    TextInput::make('siigo_id')->label('ID en SIIGO')->disabled()->dehydrated(false),
                    TextInput::make('siigo_code')->label('Code SIIGO')->disabled()->dehydrated(false),
                    \Filament\Forms\Components\DateTimePicker::make('siigo_sync_at')->label('Última sync')->disabled()->dehydrated(false)->native(false),
                ])->columns(2),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\SpatieMediaLibraryImageColumn::make('imagen')
                    ->collection('imagenes')->label('')
                    ->circular()->size(40)->limit(1),
                TextColumn::make('referencia')->searchable()->weight('bold')->copyable(),
                TextColumn::make('nombre')->searchable()->limit(35),
                TextColumn::make('marca.nombre')->badge()->color('info')->toggleable(),
                TextColumn::make('categoriaMaestra.nombre')->label('Categoría')->badge()->toggleable(),
                TextColumn::make('precio_proveedor')->money('COP')->alignEnd()->sortable(),
                TextColumn::make('variantes_count')->counts('variantes')->label('Variantes')->badge(),
                IconColumn::make('siigo_id')
                    ->label('SIIGO')
                    ->getStateUsing(fn ($record) => ! empty($record->siigo_id))
                    ->boolean()
                    ->trueColor('info')->trueIcon('heroicon-o-cloud'),
                IconColumn::make('activo')->boolean(),
                IconColumn::make('neto')->boolean()->toggleable(),
                IconColumn::make('requiere_talla')->label('Talla')->boolean()->toggleable(),
            ])
            ->defaultSort('referencia')
            ->filters([
                \Filament\Tables\Filters\TernaryFilter::make('activo'),
                \Filament\Tables\Filters\SelectFilter::make('marca_id')->relationship('marca', 'nombre'),
                \Filament\Tables\Filters\SelectFilter::make('categoria_id')->relationship('categoriaMaestra', 'nombre')->label('Categoría'),
                \Filament\Tables\Filters\TernaryFilter::make('siigo_id')
                    ->label('Sincronizado con SIIGO')
                    ->queries(
                        true: fn ($q) => $q->whereNotNull('siigo_id'),
                        false: fn ($q) => $q->whereNull('siigo_id'),
                        blank: fn ($q) => $q,
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('imprimir_etiquetas')
                        ->label('Imprimir etiquetas de variantes')
                        ->icon('heroicon-o-printer')
                        ->color('warning')
                        ->action(fn ($records) => redirect()->to(
                            route('catalogo.etiquetas.lote') . '?' . http_build_query(['productos' => $records->pluck('id')->toArray()])
                        )),
                    \pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction::make()
                        ->exports([
                            \pxlrbt\FilamentExcel\Exports\ExcelExport::make('catalogo')
                                ->fromTable()
                                ->withFilename('catalogo-productos-' . now()->format('Y-m-d')),
                        ]),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductos::route('/'),
            'create' => Pages\CreateProducto::route('/create'),
            'view' => Pages\ViewProducto::route('/{record}'),
            'edit' => Pages\EditProducto::route('/{record}/edit'),
        ];
    }
}
