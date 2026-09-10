<?php

namespace App\Modules\Catalogo\Filament\Resources;

use App\Modules\Catalogo\Filament\Resources\MarcaResource\Pages;
use App\Modules\Catalogo\Models\Marca;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use App\Support\FilamentPolicy\HeredaAutorizacion;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MarcaResource extends Resource
{
    use HeredaAutorizacion;

    protected static ?string $model = Marca::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'Marcas';
    protected static string|\UnitEnum|null $navigationGroup = 'Catálogo · Maestras';
    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool { return auth()->user()?->esAracely() ?? false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('codigo')->required()->unique(ignoreRecord: true)->maxLength(20),
            TextInput::make('nombre')->required()->maxLength(100),
            Select::make('proveedor_id')->label('Proveedor')
                ->options(fn () => \App\Models\Contacto::query()
                    ->where('es_proveedor', true)
                    ->orderBy('nombre_completo')->pluck('nombre_completo', 'id')->all())
                ->searchable(),
            Toggle::make('activa')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')->badge()->weight('bold')->searchable(),
                TextColumn::make('nombre')->searchable(),
                TextColumn::make('proveedor.nombre_completo')->label('Proveedor')->toggleable(),
                IconColumn::make('activa')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMarcas::route('/'),
            'create' => Pages\CreateMarca::route('/create'),
            'edit' => Pages\EditMarca::route('/{record}/edit'),
        ];
    }
}
