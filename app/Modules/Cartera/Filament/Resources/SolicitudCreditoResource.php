<?php

namespace App\Modules\Cartera\Filament\Resources;

use App\Modules\Cartera\Filament\Resources\SolicitudCreditoResource\Pages;
use App\Modules\Cartera\Models\SolicitudCredito;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use App\Support\FilamentPolicy\HeredaAutorizacion;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SolicitudCreditoResource extends Resource
{
    use HeredaAutorizacion;

    protected static ?string $model = SolicitudCredito::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Excepciones de crédito';

    protected static ?string $modelLabel = 'Solicitud';

    protected static ?string $pluralModelLabel = 'Excepciones de crédito';

    protected static string|\UnitEnum|null $navigationGroup = 'Cartera y CRM';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'excepciones-credito';

    public static function canViewAny(): bool { return \App\Auth\Permisos::puede(auth()->user(), 'excepciones_credito'); }
    public static function canCreate(): bool { return false; }

    public static function getNavigationBadge(): ?string
    {
        $c = SolicitudCredito::where('estado', 'pendiente')->count();
        return $c > 0 ? (string) $c : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('contacto.nombre_completo')->label('Cliente')->searchable()->weight('bold'),
                TextColumn::make('monto_pedido')->money('COP')->alignEnd(),
                TextColumn::make('motivo_retencion')->limit(50)->wrap(),
                BadgeColumn::make('nivel_actual')->colors(['warning' => 'cartera', 'danger' => 'gerencia']),
                BadgeColumn::make('estado')->colors([
                    'warning' => 'pendiente',
                    'success' => fn ($s) => in_array($s, ['aprobada_cartera', 'aprobada_gerencia']),
                    'danger' => 'rechazada',
                ]),
                TextColumn::make('resolutor.name')->label('Resolvió')->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('estado')->options([
                    'pendiente' => 'Pendiente', 'aprobada_cartera' => 'Aprobada Cartera',
                    'aprobada_gerencia' => 'Aprobada Gerencia', 'rechazada' => 'Rechazada',
                ]),
            ])
            ->recordActions([
                Action::make('aprobar')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (SolicitudCredito $r) => $r->estado === 'pendiente')
                    ->schema([Textarea::make('notas')->label('Motivo de aprobación')->required()])
                    ->action(function (SolicitudCredito $record, array $data) {
                        $record->update([
                            'estado' => $record->nivel_actual === 'gerencia' ? 'aprobada_gerencia' : 'aprobada_cartera',
                            'resuelta_por' => auth()->id(),
                            'resuelta_at' => now(),
                            'resolucion_notas' => $data['notas'],
                        ]);
                        Notification::make()->title('Aprobada')->success()->send();
                    }),
                Action::make('escalar')
                    ->label('Escalar a Gerencia')
                    ->icon('heroicon-o-arrow-up-circle')
                    ->color('warning')
                    ->visible(fn (SolicitudCredito $r) => $r->estado === 'pendiente' && $r->nivel_actual === 'cartera')
                    ->schema([Textarea::make('notas')->label('Motivo del escalamiento')->required()])
                    ->action(function (SolicitudCredito $record, array $data) {
                        $record->update([
                            'nivel_actual' => 'gerencia',
                            'resolucion_notas' => $data['notas'],
                        ]);
                        Notification::make()->title('Escalada a Gerencia')->warning()->send();
                    }),
                Action::make('rechazar')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (SolicitudCredito $r) => $r->estado === 'pendiente')
                    ->schema([Textarea::make('notas')->label('Motivo del rechazo')->required()])
                    ->action(function (SolicitudCredito $record, array $data) {
                        $record->update([
                            'estado' => 'rechazada',
                            'resuelta_por' => auth()->id(),
                            'resuelta_at' => now(),
                            'resolucion_notas' => $data['notas'],
                        ]);
                        Notification::make()->title('Rechazada')->danger()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListSolicitudes::route('/')];
    }
}
