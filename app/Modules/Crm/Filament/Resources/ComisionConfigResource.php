<?php

namespace App\Modules\Crm\Filament\Resources;

use App\Modules\Crm\Filament\Resources\ComisionConfigResource\Pages;
use App\Modules\Crm\Models\ComisionConfig;
use App\Support\FilamentPolicy\HeredaAutorizacion;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ComisionConfigResource extends Resource
{
    use HeredaAutorizacion;

    protected static ?string $model = ComisionConfig::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-percent-badge';

    protected static ?string $navigationLabel = 'Config comisiones';

    protected static ?string $modelLabel = 'Configuración';

    protected static ?string $pluralModelLabel = 'Configuración comisiones';

    protected static string|\UnitEnum|null $navigationGroup = 'Cartera y CRM';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'comisiones-config';

    public static function canViewAny(): bool
    {
        return auth()->user()?->esAracely() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Config del vendedor')->schema([
                Select::make('vendedor_id')
                    ->label('Vendedor')
                    ->relationship('vendedor', 'name', fn ($q) => $q->whereHas('roles', fn ($qq) => $qq->where('name', 'Vendedor')))
                    ->searchable()->preload()->required()->unique(ignoreRecord: true),
                TextInput::make('porcentaje_base')->numeric()->suffix('%')->default(3)
                    ->required()->helperText('% sobre venta cobrada'),
                Toggle::make('cobra_solo_cobrado')->default(true)
                    ->helperText('ON = solo comisiona lo pagado · OFF = sobre facturado'),
                TextInput::make('meta_mensual')->numeric()->prefix('$')
                    ->helperText('Meta mensual para bono adicional'),
                TextInput::make('bono_por_meta_pct')->numeric()->suffix('%')
                    ->helperText('% adicional si supera la meta'),
                Toggle::make('activo')->default(true),
                Textarea::make('notas')->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('vendedor.name')->label('Vendedor')->searchable(),
                TextColumn::make('porcentaje_base')->label('%')->suffix(' %')->alignEnd(),
                IconColumn::make('cobra_solo_cobrado')->boolean()->label('Sobre cobrado'),
                TextColumn::make('meta_mensual')->money('COP')->alignEnd()->placeholder('—'),
                TextColumn::make('bono_por_meta_pct')->label('Bono')->suffix(' %')->alignEnd()->placeholder('—'),
                IconColumn::make('activo')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComisionConfigs::route('/'),
            'create' => Pages\CreateComisionConfig::route('/create'),
            'edit' => Pages\EditComisionConfig::route('/{record}/edit'),
        ];
    }
}
