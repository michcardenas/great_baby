<?php

namespace App\Modules\Catalogo\Filament\Resources;

use App\Modules\Catalogo\Filament\Resources\CategoriaResource\Pages;
use App\Modules\Catalogo\Models\Categoria;
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

class CategoriaResource extends Resource
{
    use HeredaAutorizacion;

    protected static ?string $model = Categoria::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'Categorías';
    protected static string|\UnitEnum|null $navigationGroup = 'Catálogo · Maestras';
    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool { return auth()->user()?->esAracely() ?? false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('codigo')->required()->unique(ignoreRecord: true),
            TextInput::make('nombre')->required(),
            Select::make('padre_id')->label('Categoría padre')
                ->relationship('padre', 'nombre')->searchable()->preload()->nullable(),
            TextInput::make('cuenta_puc_ingreso')->label('Cuenta PUC Ingreso')->placeholder('4135'),
            TextInput::make('cuenta_puc_costo')->label('Cuenta PUC Costo')->placeholder('6135'),
            Toggle::make('activa')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('codigo')->badge()->searchable(),
            TextColumn::make('nombre')->searchable(),
            TextColumn::make('padre.nombre')->label('Padre')->placeholder('—'),
            TextColumn::make('cuenta_puc_ingreso')->label('PUC Ingreso')->toggleable(),
            IconColumn::make('activa')->boolean(),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategorias::route('/'),
            'create' => Pages\CreateCategoria::route('/create'),
            'edit' => Pages\EditCategoria::route('/{record}/edit'),
        ];
    }
}
