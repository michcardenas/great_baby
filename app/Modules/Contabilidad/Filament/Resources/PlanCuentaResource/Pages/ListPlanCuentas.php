<?php

namespace App\Modules\Contabilidad\Filament\Resources\PlanCuentaResource\Pages;

use App\Modules\Contabilidad\Filament\Resources\PlanCuentaResource;
use App\Modules\Contabilidad\Models\PlanCuenta;
use App\Modules\Contabilidad\Services\ImportadorPlanCuentas;
use App\Modules\Contabilidad\Services\PlantillaPlanCuentas;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListPlanCuentas extends ListRecords
{
    protected static string $resource = PlanCuentaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva cuenta')
                ->modalWidth(Width::TwoExtraLarge)
                ->after(fn () => PlanCuenta::vincularPadres()),

            Action::make('importar')
                ->label('Importar')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('info')
                ->modalHeading('Importar plan de cuentas')
                ->modalDescription('Sube tu plan en Excel (.xlsx) o CSV. Se actualizan las cuentas existentes por código y se crean las nuevas (no se borra nada).')
                ->modalSubmitActionLabel('Importar')
                ->schema([
                    FileUpload::make('archivo')
                        ->label('Archivo (.xlsx o .csv)')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'text/csv',
                            'application/csv',
                            'application/octet-stream',
                        ])
                        ->disk('local')
                        ->directory('imports')
                        ->required()
                        ->helperText('Columnas: codigo, nombre (obligatorias) · naturaleza, permite_movimiento, siigo_cuenta_id, activa (opcionales). Descarga la plantilla si tienes dudas.'),
                ])
                ->action(function (array $data): void {
                    $rel = $data['archivo'] ?? null;
                    if (! $rel) {
                        Notification::make()->title('Sube un archivo primero.')->danger()->send();
                        return;
                    }

                    $ruta = collect([
                        storage_path('app/private/' . $rel),
                        storage_path('app/' . $rel),
                    ])->first(fn ($p) => file_exists($p));

                    if (! $ruta) {
                        Notification::make()->title('No se encontró el archivo cargado.')->danger()->send();
                        return;
                    }

                    try {
                        $res = app(ImportadorPlanCuentas::class)->importar($ruta);
                    } catch (\Throwable $e) {
                        Notification::make()->title('Error al importar')->body($e->getMessage())->danger()->send();
                        return;
                    } finally {
                        @unlink($ruta);
                    }

                    $errores = $res['errores'] ?? [];
                    $cuerpo = "Creadas: {$res['creados']} · Actualizadas: {$res['actualizados']}";
                    if (! empty($errores)) {
                        $cuerpo .= ' · ' . count($errores) . ' fila(s) con aviso: '
                            . implode(' | ', array_slice($errores, 0, 5))
                            . (count($errores) > 5 ? ' …' : '');
                    }

                    Notification::make()
                        ->title(empty($errores) ? '✅ Plan de cuentas importado' : '⚠️ Importado con avisos')
                        ->body($cuerpo)
                        ->color(empty($errores) ? 'success' : 'warning')
                        ->persistent()
                        ->send();
                }),

            Action::make('plantilla')
                ->label('Plantilla')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->tooltip('Descarga el formato Excel con ejemplos e instrucciones')
                ->action(function () {
                    $ruta = app(PlantillaPlanCuentas::class)->generar();

                    return response()->download($ruta, 'Plantilla_Plan_de_Cuentas_GREAT_BABY.xlsx', [
                        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])->deleteFileAfterSend();
                }),
        ];
    }
}
