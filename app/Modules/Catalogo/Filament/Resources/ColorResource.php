<?php

namespace App\Modules\Catalogo\Filament\Resources;

use App\Modules\Catalogo\Filament\Resources\ColorResource\Pages;
use App\Modules\Catalogo\Models\Color;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use App\Support\FilamentPolicy\HeredaAutorizacion;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ColorResource extends Resource
{
    use HeredaAutorizacion;

    protected static ?string $model = Color::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-swatch';
    protected static ?string $navigationLabel = 'Colores';
    protected static string|\UnitEnum|null $navigationGroup = 'Catálogo · Maestras';
    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool { return auth()->user()?->esAracely() ?? false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('codigo')->required()->unique(ignoreRecord: true)->maxLength(10)->helperText('Ej: 02 (2 caracteres)'),
            TextInput::make('nombre')->required(),
            ColorPicker::make('hex')->label('Color hex'),
            Toggle::make('activo')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('codigo')->badge()->searchable(),
            TextColumn::make('nombre')->searchable(),
            ColorColumn::make('hex'),
            IconColumn::make('activo')->boolean(),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListColores::route('/'),
            'create' => Pages\CreateColor::route('/create'),
            'edit' => Pages\EditColor::route('/{record}/edit'),
        ];
    }
}
