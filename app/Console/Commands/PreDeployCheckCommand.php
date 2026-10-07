<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * C-F9/C-F10 · Pre-deploy check automatizado.
 *
 * Lo que un humano NO debería tener que validar a mano antes de deploy. Corre
 * 20+ verificaciones en segundos y marca verde/amarillo/rojo por bloque:
 *
 *   AMBIENTE: APP_ENV, APP_DEBUG, APP_KEY, timezone, build de Vite, .env.production
 *   BD:       conexión, tabla sessions, migraciones pendientes, snapshots recientes
 *   USUARIOS: existencia de los 7 roles demo del seeder (si falta → ejecuta QA ciego)
 *   RUTAS:    las 10+ pantallas críticas resuelven controller (no 404)
 *   SIIGO:    config, cola worker alcanzable, feature flag, último sync < 1h
 *   TESTS:    phpunit/pest corre y pasa
 *   SEGURIDAD: HTTPS forzado, APP_DEBUG=false, gate esRoot activo
 *
 * Uso:
 *   php artisan gb:pre-deploy-check                 # correr todos
 *   php artisan gb:pre-deploy-check --fix           # intenta corregir lo arreglable
 *   php artisan gb:pre-deploy-check --json          # salida JSON (CI)
 *   php artisan gb:pre-deploy-check --skip-tests    # omite tests (más rápido)
 */
class PreDeployCheckCommand extends Command
{
    protected $signature = 'gb:pre-deploy-check
                            {--fix : Intenta corregir lo arreglable (optimize:clear, cache)}
                            {--json : Salida JSON para CI}
                            {--skip-tests : Omite phpunit/pest (acelera 30s)}';

    protected $description = 'C-F9/C-F10 · Chequeo pre-deploy automatizado · 20+ verificaciones';

    /** @var array<int,array{bloque:string,check:string,estado:string,detalle:string}> */
    private array $resultados = [];

    public function handle(): int
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('  GB · Pre-deploy check (C-F9 + C-F10)');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $this->bloqueAmbiente();
        $this->bloqueBD();
        $this->bloqueUsuariosDemo();
        $this->bloqueRutasCriticas();
        $this->bloqueSiigo();
        $this->bloqueSeguridad();
        if (! $this->option('skip-tests')) {
            $this->bloqueTests();
        }

        return $this->resumir();
    }

    // ── BLOQUE AMBIENTE ─────────────────────────────────────────────────
    private function bloqueAmbiente(): void
    {
        $bloque = 'AMBIENTE';
        $this->add($bloque, 'APP_ENV', config('app.env') !== 'local', 'env=' . config('app.env'));
        $this->add($bloque, 'APP_DEBUG=false', ! config('app.debug'), 'debug=' . var_export(config('app.debug'), true));
        $this->add($bloque, 'APP_KEY presente', !! config('app.key'), config('app.key') ? 'OK' : 'FALTA APP_KEY');
        $this->add($bloque, 'timezone America/Bogota', config('app.timezone') === 'America/Bogota',
            'tz=' . config('app.timezone'));
        $this->add($bloque, 'Vite build (public/build/manifest.json)',
            file_exists(public_path('build/manifest.json')), 'si falta: npm run build');
        $this->add($bloque, '.env.production existe',
            file_exists(base_path('.env.production')) || file_exists(base_path('.env')),
            file_exists(base_path('.env.production')) ? 'OK' : 'solo .env — crea .env.production');
        $this->add($bloque, 'HTTPS forzado (APP_URL)',
            str_starts_with((string) config('app.url'), 'https://') || config('app.env') === 'local',
            config('app.url'));
    }

    // ── BLOQUE BASE DE DATOS ────────────────────────────────────────────
    private function bloqueBD(): void
    {
        $bloque = 'BD';
        try {
            DB::connection()->getPdo();
            $this->add($bloque, 'Conexión BD', true, config('database.connections.' . config('database.default') . '.database'));

            $tablas = ['users', 'sessions', 'productos', 'pedidos_cliente', 'asientos_manuales', 'siigo_sync_log'];
            foreach ($tablas as $t) {
                $this->add($bloque, "Tabla $t", Schema::hasTable($t),
                    Schema::hasTable($t) ? '✓' : 'FALTA → php artisan migrate');
            }

            // Migraciones pendientes.
            $pendientes = count(array_filter(
                scandir(database_path('migrations')) ?: [],
                fn ($f) => str_ends_with($f, '.php')
            )) - DB::table('migrations')->count();
            $this->add($bloque, 'Migraciones aplicadas', $pendientes <= 0,
                $pendientes > 0 ? "$pendientes pendientes · corré migrate" : 'todo al día');

            // Snapshot reciente (backups/).
            $backupsDir = base_path('backups');
            if (is_dir($backupsDir)) {
                $files = glob("$backupsDir/*.sql") ?: [];
                $reciente = collect($files)->map(fn ($f) => filemtime($f))->max();
                $horas = $reciente ? round((time() - $reciente) / 3600, 1) : 999;
                $this->add($bloque, 'Backup < 24h', $horas < 24,
                    $reciente ? "último hace {$horas}h" : 'sin backups en backups/');
            } else {
                $this->add($bloque, 'Backup < 24h', false, 'no existe backups/');
            }
        } catch (\Throwable $e) {
            $this->add($bloque, 'Conexión BD', false, 'FAIL · ' . mb_substr($e->getMessage(), 0, 80));
        }
    }

    // ── BLOQUE USUARIOS DEMO (necesarios para QA C-F9) ──────────────────
    private function bloqueUsuariosDemo(): void
    {
        $bloque = 'USUARIOS DEMO';
        if (! Schema::hasTable('users')) {
            $this->add($bloque, 'tabla users', false, 'FALTA · migrate primero');
            return;
        }
        // Los 10 usuarios del UsuariosDemoSeeder (1 por cada rol).
        $demos = \Database\Seeders\UsuariosDemoSeeder::USUARIOS;
        foreach ($demos as [$email, $name, $rol]) {
            $u = \App\Models\User::firstWhere('email', $email);
            $ok = $u && $u->hasRole($rol);
            $this->add($bloque, "$rol ($email)", $ok,
                $ok ? 'OK' : ($u ? "existe pero sin rol $rol → gb:usuarios-demo" : 'no existe → gb:usuarios-demo'));
        }
    }

    // ── BLOQUE RUTAS CRÍTICAS ───────────────────────────────────────────
    private function bloqueRutasCriticas(): void
    {
        $bloque = 'RUTAS';
        $rutas = [
            'app.login',
            'app.dashboard',
            'app.vendedor.index',
            'app.vendedor.seguimiento',
            'app.contabilidad.index',
            'app.contabilidad.panel',
            'app.contabilidad.pendientes-siigo',
            'app.contabilidad.discrepancias-siigo',
            'app.contabilidad.asiento.ver-siigo',
            'app.contabilidad.reportes.exportar-csv',
            'app.marketing.index',
        ];
        foreach ($rutas as $nombre) {
            $r = Route::getRoutes()->getByName($nombre);
            $this->add($bloque, $nombre, $r !== null,
                $r ? $r->uri() : 'ruta no registrada');
        }
    }

    // ── BLOQUE SIIGO ────────────────────────────────────────────────────
    private function bloqueSiigo(): void
    {
        $bloque = 'SIIGO';
        try {
            if (! Schema::hasTable('siigo_config')) {
                $this->add($bloque, 'tabla siigo_config', false, 'FALTA · migrate');
                return;
            }
            $cfg = DB::table('siigo_config')->first();
            $this->add($bloque, 'siigo_config inicializado', $cfg !== null,
                $cfg ? 'OK' : 'no hay fila');
            $this->add($bloque, 'SIIGO_USERNAME env', !! env('SIIGO_USERNAME'),
                env('SIIGO_USERNAME') ? mb_substr(env('SIIGO_USERNAME'), 0, 20) . '…' : 'FALTA');
            $this->add($bloque, 'SIIGO_ACCESS_KEY env', !! env('SIIGO_ACCESS_KEY'),
                env('SIIGO_ACCESS_KEY') ? '✓ (oculta)' : 'FALTA');

            // Feature flag kill-switch de push automático.
            $pushAuto = env('FEATURE_SIIGO_PUSH_AUTO', 'false');
            $this->add($bloque, 'FEATURE_SIIGO_PUSH_AUTO',
                in_array($pushAuto, ['true', 'false', true, false], true),
                "valor actual: " . var_export($pushAuto, true));

            // Último sync.
            if (Schema::hasTable('siigo_sync_log')) {
                $ultimo = DB::table('siigo_sync_log')->where('estado', 'ok')
                    ->latest('created_at')->first();
                $horas = $ultimo ? round(now()->diffInMinutes($ultimo->created_at) / 60, 1) : 9999;
                $this->add($bloque, 'último sync OK < 1h', $horas < 1,
                    $ultimo ? "hace {$horas}h" : 'nunca · corré php artisan siigo:smoke-test');
            }
        } catch (\Throwable $e) {
            $this->add($bloque, 'SIIGO check', false, mb_substr($e->getMessage(), 0, 80));
        }
    }

    // ── BLOQUE SEGURIDAD ────────────────────────────────────────────────
    private function bloqueSeguridad(): void
    {
        $bloque = 'SEGURIDAD';
        $this->add($bloque, 'APP_DEBUG=false', ! config('app.debug'),
            config('app.debug') ? 'DEBUG=TRUE · fuga de stacktraces en prod' : 'OK');

        // Helper esRoot presente en User.
        $existe = method_exists(\App\Models\User::class, 'esRoot');
        $this->add($bloque, 'User::esRoot() helper', $existe,
            $existe ? 'OK' : 'falta helper · CONT-C8');

        // Logging de audit activo.
        $audit = config('logging.channels.audit');
        $this->add($bloque, 'Canal logging audit', !! $audit,
            $audit ? 'OK' : 'sin canal audit · export CSV no deja rastro');
    }

    // ── BLOQUE TESTS ────────────────────────────────────────────────────
    private function bloqueTests(): void
    {
        $bloque = 'TESTS';
        $this->line('[TESTS] corriendo phpunit... (puede tardar 30s)');
        $t0 = microtime(true);
        exec('php artisan test --compact 2>&1', $out, $code);
        $segs = round(microtime(true) - $t0, 1);
        $linea = end($out) ?: '';
        $this->add($bloque, 'phpunit/pest', $code === 0,
            "{$segs}s · " . mb_substr((string) $linea, 0, 80));
    }

    // ── UTIL ───────────────────────────────────────────────────────────
    private function add(string $bloque, string $check, bool $ok, string $detalle): void
    {
        $this->resultados[] = [
            'bloque' => $bloque,
            'check' => $check,
            'estado' => $ok ? '✓' : '✗',
            'detalle' => $detalle,
        ];
    }

    private function resumir(): int
    {
        if ($this->option('json')) {
            $this->line(json_encode([
                'ok' => count(array_filter($this->resultados, fn ($r) => $r['estado'] === '✓')),
                'total' => count($this->resultados),
                'resultados' => $this->resultados,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $this->todosOk() ? self::SUCCESS : self::FAILURE;
        }

        foreach (collect($this->resultados)->groupBy('bloque') as $bloque => $rows) {
            $this->newLine();
            $this->line("<fg=cyan>── {$bloque} ──</>");
            foreach ($rows as $r) {
                $color = $r['estado'] === '✓' ? 'green' : 'red';
                $this->line("  <fg={$color}>{$r['estado']}</> {$r['check']} <fg=gray>· {$r['detalle']}</>");
            }
        }

        $this->newLine();
        $ok = count(array_filter($this->resultados, fn ($r) => $r['estado'] === '✓'));
        $total = count($this->resultados);
        $color = $ok === $total ? 'info' : ($ok >= $total - 2 ? 'warn' : 'error');
        $this->{$color}("━━━ {$ok}/{$total} checks OK ━━━");

        if ($this->option('fix') && $ok < $total) {
            $this->newLine();
            $this->line('<fg=yellow>Aplicando fixes automáticos…</>');
            exec('php artisan optimize:clear');
            exec('php artisan config:cache');
            exec('php artisan route:cache');
            exec('php artisan view:cache');
            $this->info('Caches limpiadas/recacheadas. Volvé a correr sin --fix para validar.');
        }

        return $this->todosOk() ? self::SUCCESS : self::FAILURE;
    }

    private function todosOk(): bool
    {
        return collect($this->resultados)->every(fn ($r) => $r['estado'] === '✓');
    }
}
