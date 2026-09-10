<?php

namespace App\Modules\Cartera\Filament\Resources;

use App\Modules\Cartera\Filament\Resources\CobranzaRegistroResource\Pages;
use App\Modules\Cartera\Models\CobranzaRegistro;
use BackedEnum;
use Filament\Resources\Resource;
use App\Support\FilamentPolicy\HeredaAutorizacion;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CobranzaRegistroResource extends Resource
{
    use HeredaAutorizacion;

    protected static ?string $model = CobranzaRegistro::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Cobranzas enviadas';

    protected static ?string $modelLabel = 'Cobranza';

    protected static ?string $pluralModelLabel = 'Historial de cobranzas';

    protected static string|\UnitEnum|null $navigationGroup = 'Cartera y CRM';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'cobranzas';

    public static function canViewAny(): bool
    {
        return \App\Auth\Permisos::puede(auth()->user(), 'cobranzas');
    }

    public static function canCreate(): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('enviado_at')->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('contacto.nombre_completo')->label('Cliente')->searchable()->limit(28),
                TextColumn::make('factura.numero')->label('Factura')->weight('bold'),
                BadgeColumn::make('tramo')
                    ->colors(['success' => '0-30', 'warning' => '31-59', 'warning' => '60-89', 'danger' => '90-119', 'gray' => '120+']),
                BadgeColumn::make('canal')->color('info'),
                BadgeColumn::make('estado')->colors([
                    'success' => 'enviado', 'info' => 'leido', 'success' => 'respondido',
                    'warning' => 'escalado', 'success' => 'resuelto', 'danger' => 'fallido',
                ]),
                TextColumn::make('mensaje')->limit(60)->tooltip(fn ($r) => $r->mensaje),
            ])
            ->defaultSort('enviado_at', 'desc')
            ->filters([
                SelectFilter::make('canal')->options(['whatsapp' => 'WhatsApp', 'email' => 'Email', 'llamada' => 'Llamada', 'manual' => 'Manual']),
                SelectFilter::make('tramo')->options(['0-30' => '0–30 d', '31-59' => '31–59 d', '60-89' => '60–89 d', '90-119' => '90–119 d', '120+' => '120+ d']),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction::make()
                        ->exports([
                            \pxlrbt\FilamentExcel\Exports\ExcelExport::make('cobranzas')
                                ->fromTable()->withFilename('cobranzas-' . now()->format('Y-m-d')),
                        ]),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListCobranzas::route('/')];
    }
}
