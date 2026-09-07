<?php

namespace App\Modules\Siigo\Filament\Pages;

use App\Modules\Siigo\Clients\SiigoClient;
use App\Modules\Siigo\Models\SiigoConfig;
use App\Modules\Siigo\Models\SiigoSyncLog;
use App\Modules\Siigo\Services\SiigoService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IntegracionSiigo extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cloud';

    protected static ?string $navigationLabel = 'Integración SIIGO';

    protected static ?string $title = 'Integración SIIGO — sincronización de inventario y clientes';

    protected static string|\UnitEnum|null $navigationGroup = 'Integraciones';

    protected static ?int $navigationSort = 1;

    protected string $view = 'siigo.pages.integracion';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->esAracely() ?? false;
    }

    public function mount(): void
    {
        $config = SiigoConfig::current();
        $this->form->fill([
            'username' => $config->username,
            'partner_id' => $config->partner_id,
            'ambiente' => $config->ambiente,
            'activo' => $config->activo,
            'nit_emisor' => $config->nit_emisor,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Credenciales SIIGO API')
                ->description('El access_key se almacena encriptado en BD (Crypt). Solo administradores pueden verlo/cambiarlo.')
                ->schema([
                    TextInput::make('username')->label('Usuario (email)')->email()->required(),
                    TextInput::make('access_key')->label('Access key')->password()->revealable()
                        ->helperText('Déjalo vacío para conservar el actual. Nuevo valor lo reemplaza y encripta.'),
                    TextInput::make('partner_id')->label('Partner ID')
                        ->helperText('Se registra en el header Partner-Id de cada request.'),
                    Select::make('ambiente')->options([
                        'sandbox' => 'Sandbox (pruebas)',
                        'production' => 'Producción',
                    ])->default('sandbox')->required(),
                    TextInput::make('nit_emisor')->label('NIT emisor'),
                    Toggle::make('activo')->label('Integración activa'),
                ])->columns(2),
        ])->statePath('data');
    }

    public function guardar(): void
    {
        $data = $this->form->getState();
        $config = SiigoConfig::current();
        if (empty($data['access_key'])) unset($data['access_key']);
        $data['token_cache'] = null;
        $data['token_expires_at'] = null;
        $config->fill($data)->save();

        Notification::make()->title('Configuración guardada')->success()->send();
    }

    public function probarConexion(): void
    {
        $r = (new SiigoClient(SiigoConfig::current()))->probarConexion();
        Notification::make()->title($r['ok'] ? '✅ Conexión OK' : '❌ Falló')
            ->body($r['mensaje'])
            ->color($r['ok'] ? 'success' : 'danger')->send();
    }

    public function sincronizarCatalogos(): void
    {
        try {
            $r = (new SiigoService(new SiigoClient(SiigoConfig::current())))->sincronizarCatalogos();
            $total = array_sum($r);
            Notification::make()->title("✅ Catálogos sincronizados ({$total})")
                ->body(collect($r)->map(fn ($n, $k) => "{$k}: {$n}")->join(' · '))
                ->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    public function sincronizarProductos(): void
    {
        try {
            $r = (new SiigoService(new SiigoClient(SiigoConfig::current())))->sincronizarProductos();
            Notification::make()->title('✅ Productos sincronizados')
                ->body("Nuevos: {$r['nuevos']} · Actualizados: {$r['actualizados']} · Errores: {$r['errores']}")
                ->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    public function sincronizarClientes(): void
    {
        try {
            $r = (new SiigoService(new SiigoClient(SiigoConfig::current())))->sincronizarClientes();
            Notification::make()->title('✅ Clientes sincronizados')
                ->body("Nuevos: {$r['nuevos']} · Actualizados: {$r['actualizados']} · Errores: {$r['errores']}")
                ->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    public function getLogs()
    {
        return SiigoSyncLog::query()->orderByDesc('created_at')->limit(15)->get();
    }

    public function getConfig(): SiigoConfig
    {
        return SiigoConfig::current();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('guardar')->label('Guardar credenciales')->color('primary')->action('guardar'),
            Action::make('probar')->label('Probar conexión')->color('info')->icon('heroicon-o-signal')->action('probarConexion'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync_cat')->label('Sincronizar catálogos + warehouses')->icon('heroicon-o-arrow-path')->color('info')->action('sincronizarCatalogos'),
            Action::make('sync_prod')->label('Sincronizar productos')->icon('heroicon-o-cube')->color('success')->action('sincronizarProductos'),
            Action::make('sync_cli')->label('Sincronizar clientes')->icon('heroicon-o-user-group')->color('warning')->action('sincronizarClientes'),
        ];
    }
}
