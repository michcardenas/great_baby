<?php

namespace App\Modules\Dropi\Filament\Pages;

use App\Modules\Dropi\Models\Producto;
use App\Modules\Dropi\Models\ProductoVariante;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\Common\Creator\ReaderEntityFactory;

class ImportarProductos extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationLabel = 'Importar productos';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Importar productos desde Excel/CSV';

    protected string $view = 'dropi.pages.importar-productos';

    public ?array $data = [];

    /** @var array<int, array{fila:int, sku:string, mensaje:string, tipo:string}> */
    public array $errores = [];

    /** @var array<string, int> */
    public array $preview = [];

    public bool $previewCalculado = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->esAracely() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('archivo')
                ->label('Archivo Excel (.xlsx) o CSV')
                ->acceptedFileTypes([
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'text/csv',
                    'application/csv',
                    'application/octet-stream',
                ])
                ->disk('local')->directory('imports')
                ->required(),
        ])->statePath('data');
    }

    protected function abrirLector(): array
    {
        $data = $this->form->getState();
        $rel = $data['archivo'] ?? null;
        if (! $rel) {
            throw new \RuntimeException('Sube un archivo primero.');
        }
        $paths = [
            storage_path('app/private/' . $rel),
            storage_path('app/' . $rel),
        ];
        $path = collect($paths)->first(fn ($p) => file_exists($p));
        if (! $path) {
            throw new \RuntimeException('No se encontró el archivo cargado.');
        }
        return [ReaderEntityFactory::createReaderFromFile($path), $path];
    }

    public function previsualizar(): void
    {
        try {
            [$reader, $path] = $this->abrirLector();
            $reader->open($path);

            $nuevos = 0; $actualizados = 0; $variantes = 0; $errores = [];

            foreach ($reader->getSheetIterator() as $sheet) {
                $header = null;
                foreach ($sheet->getRowIterator() as $i => $row) {
                    $cells = array_map(fn ($c) => trim((string) $c->getValue()), $row->getCells());
                    if ($i === 1) {
                        $header = array_map(fn ($h) => strtolower(trim($h)), $cells);
                        if (! in_array('referencia', $header) || ! in_array('nombre', $header)) {
                            $errores[] = ['fila' => 1, 'sku' => '—', 'mensaje' => 'Faltan columnas obligatorias: referencia y nombre.', 'tipo' => 'error'];
                            break;
                        }
                        continue;
                    }
                    if (empty(array_filter($cells))) continue;

                    $r = @array_combine(array_pad($header, count($cells), ''), array_pad($cells, count($header), ''));
                    if (empty($r['referencia'])) {
                        $errores[] = ['fila' => $i, 'sku' => '—', 'mensaje' => 'Referencia vacía.', 'tipo' => 'error'];
                        continue;
                    }
                    if (empty($r['nombre'])) {
                        $errores[] = ['fila' => $i, 'sku' => $r['referencia'], 'mensaje' => 'Nombre vacío.', 'tipo' => 'error'];
                        continue;
                    }
                    $existe = Producto::where('referencia', $r['referencia'])->exists();
                    $existe ? $actualizados++ : $nuevos++;
                    if (! empty($r['color_codigo']) || ! empty($r['diseno_codigo']) || ! empty($r['talla'])) {
                        $variantes++;
                    }
                }
            }

            $reader->close();

            $this->preview = ['nuevos' => $nuevos, 'actualizados' => $actualizados, 'variantes' => $variantes];
            $this->errores = $errores;
            $this->previewCalculado = true;
        } catch (\Throwable $e) {
            Notification::make()->title('Error al leer el archivo')->body($e->getMessage())->danger()->send();
        }
    }

    public function procesar(): void
    {
        try {
            [$reader, $path] = $this->abrirLector();
            $reader->open($path);

            $creados = 0; $actualizados = 0; $variantes = 0; $errores = [];

            DB::transaction(function () use ($reader, &$creados, &$actualizados, &$variantes, &$errores) {
                foreach ($reader->getSheetIterator() as $sheet) {
                    $header = null;
                    foreach ($sheet->getRowIterator() as $i => $row) {
                        $cells = array_map(fn ($c) => trim((string) $c->getValue()), $row->getCells());
                        if ($i === 1) {
                            $header = array_map(fn ($h) => strtolower(trim($h)), $cells);
                            continue;
                        }
                        if (empty(array_filter($cells))) continue;

                        try {
                            $r = @array_combine(array_pad($header, count($cells), ''), array_pad($cells, count($header), ''));
                            if (empty($r['referencia']) || empty($r['nombre'])) {
                                throw new \RuntimeException('referencia y/o nombre vacíos');
                            }
                            $producto = Producto::updateOrCreate(
                                ['referencia' => $r['referencia']],
                                [
                                    'nombre' => $r['nombre'],
                                    'categoria' => $r['categoria'] ?? null,
                                    'precio_proveedor' => (float) ($r['precio_proveedor'] ?? 0),
                                    'requiere_talla' => filter_var($r['requiere_talla'] ?? false, FILTER_VALIDATE_BOOL),
                                    'es_set' => filter_var($r['es_set'] ?? false, FILTER_VALIDATE_BOOL),
                                    'activo' => filter_var($r['activo'] ?? true, FILTER_VALIDATE_BOOL),
                                ]
                            );
                            $producto->wasRecentlyCreated ? $creados++ : $actualizados++;

                            if (! empty($r['color_codigo']) || ! empty($r['diseno_codigo']) || ! empty($r['talla'])) {
                                $codigo = ProductoVariante::generarCodigoBarras(
                                    $producto->referencia,
                                    $r['color_codigo'] ?? null,
                                    $r['diseno_codigo'] ?? null,
                                    $r['talla'] ?? null,
                                );
                                ProductoVariante::updateOrCreate(
                                    ['codigo_barras' => $codigo],
                                    [
                                        'producto_id' => $producto->id,
                                        'color_codigo' => $r['color_codigo'] ?? null,
                                        'color_nombre' => $r['color_nombre'] ?? null,
                                        'diseno_codigo' => $r['diseno_codigo'] ?? null,
                                        'diseno_nombre' => $r['diseno_nombre'] ?? null,
                                        'talla' => $r['talla'] ?? null,
                                    ]
                                );
                                $variantes++;
                            }
                        } catch (\Throwable $e) {
                            $errores[] = [
                                'fila' => $i, 'sku' => $r['referencia'] ?? '—',
                                'mensaje' => $e->getMessage(), 'tipo' => 'error',
                            ];
                        }
                    }
                }
            });

            $reader->close();
            @unlink($path);

            $this->errores = $errores;
            $this->preview = ['nuevos' => $creados, 'actualizados' => $actualizados, 'variantes' => $variantes];

            Notification::make()
                ->title(empty($errores) ? '✅ Importación exitosa' : "⚠️ Importación con {" . count($errores) . "} errores")
                ->body("Nuevos: {$creados} · Actualizados: {$actualizados} · Variantes: {$variantes}")
                ->color(empty($errores) ? 'success' : 'warning')
                ->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Error importando')->body($e->getMessage())->danger()->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('previsualizar')
                ->label('1 · Previsualizar')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->action('previsualizar'),
            Action::make('procesar')
                ->label('2 · Importar de verdad')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->requiresConfirmation()
                ->modalDescription('Se aplicarán los cambios en la BD. ¿Confirmas?')
                ->action('procesar')
                ->hidden(fn () => ! $this->previewCalculado),
        ];
    }
}
