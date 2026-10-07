<?php

namespace App\Console\Commands;

use App\Modules\Siigo\Clients\SiigoClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

/**
 * TEST-S6 · Smoke test contra el sandbox real de SIIGO.
 *
 * Lanza una serie de peticiones GET de solo-lectura a los endpoints clave
 * de SIIGO y reporta por consola cuáles respondieron, con qué tiempo y qué
 * tamaño de respuesta. NO crea ni modifica datos en SIIGO.
 *
 * Para qué sirve:
 *   - Checar que las credenciales siguen vivas después de un cambio.
 *   - Verificar en 30s que todos los endpoints que usamos responden.
 *   - Primera parada ante "SIIGO no me recibe nada".
 *
 * Uso:
 *   php artisan siigo:smoke-test               # usa SIIGO_* del .env
 *   php artisan siigo:smoke-test --fake        # solo valida wiring (no toca red)
 *   php artisan siigo:smoke-test --json        # salida JSON para CI
 */
class SiigoSmokeTestCommand extends Command
{
    protected $signature = 'siigo:smoke-test
                            {--fake : Usa el modo fake del SiigoClient (no sale a internet)}
                            {--json : Salida JSON (para CI o integración con monitoreo)}';

    protected $description = 'Pinga los 10 endpoints clave de SIIGO (sandbox) · dry-run, no escribe nada';

    /**
     * Endpoints de solo-lectura que tocamos en producción. Vigílalos acá — si
     * alguno falla, lo demás del ERP ya trae datos malos o rechaza payloads.
     */
    private array $endpoints = [
        ['método' => 'auth',          'path' => '/auth'],
        ['método' => 'GET',           'path' => '/v1/users'],
        ['método' => 'GET',           'path' => '/v1/products?page_size=1'],
        ['método' => 'GET',           'path' => '/v1/customers?page_size=1'],
        ['método' => 'GET',           'path' => '/v1/document-types?type=FV'],
        ['método' => 'GET',           'path' => '/v1/document-types?type=NC'],
        ['método' => 'GET',           'path' => '/v1/taxes'],
        ['método' => 'GET',           'path' => '/v1/warehouses'],
        ['método' => 'GET',           'path' => '/v1/invoices?page_size=1'],
        ['método' => 'GET',           'path' => '/v1/journals?page_size=1'],
    ];

    public function handle(SiigoClient $siigo): int
    {
        if ($this->option('fake')) {
            // El cliente decide por `siigo.driver`, no por `siigo.fake`: con la
            // clave vieja el --fake no hacía nada y el smoke test golpeaba la
            // API real creyendo estar en seco.
            Config::set('siigo.driver', 'fake');
            $this->warn('Modo FAKE activado — no se tocará internet.');
        }

        if (config('siigo.driver', 'real') !== 'fake') {
            $this->line('<fg=gray>Modo REAL — se consultará api.siigo.com (sólo lecturas).</>');
        }

        $resultados = [];
        $ok = 0;
        $totalMs = 0;

        foreach ($this->endpoints as $e) {
            $t0 = microtime(true);
            $estado = '✓';
            $http = null;
            $bytes = 0;
            $err = null;

            try {
                if ($e['método'] === 'auth') {
                    $token = $siigo->authenticate();
                    $http = $token ? 200 : 401;
                    $bytes = $token ? mb_strlen($token) : 0;
                } else {
                    $resp = $siigo->request($e['método'], $e['path']);
                    $http = $resp->status();
                    $bytes = mb_strlen((string) $resp->body());
                    if (! $resp->successful()) {
                        $estado = '✗';
                        $err = mb_substr((string) $resp->body(), 0, 120);
                    }
                }
            } catch (\Throwable $t) {
                $estado = '✗';
                $err = mb_substr($t->getMessage(), 0, 120);
            }

            $ms = (int) ((microtime(true) - $t0) * 1000);
            $totalMs += $ms;
            if ($estado === '✓') $ok++;

            $resultados[] = [
                'endpoint' => "{$e['método']} {$e['path']}",
                'estado' => $estado,
                'http' => $http,
                'ms' => $ms,
                'bytes' => $bytes,
                'error' => $err,
            ];
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'ok' => $ok,
                'total' => count($this->endpoints),
                'ms' => $totalMs,
                'resultados' => $resultados,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $ok === count($this->endpoints) ? self::SUCCESS : self::FAILURE;
        }

        $this->table(
            ['Endpoint', 'OK', 'HTTP', 'ms', 'bytes', 'error'],
            array_map(fn ($r) => [$r['endpoint'], $r['estado'], $r['http'], $r['ms'], $r['bytes'], $r['error'] ?? '—'], $resultados),
        );
        $this->newLine();
        $color = $ok === count($this->endpoints) ? 'info' : ($ok >= count($this->endpoints) - 2 ? 'warn' : 'error');
        $this->{$color}("{$ok}/" . count($this->endpoints) . " endpoints OK · {$totalMs}ms totales");

        return $ok === count($this->endpoints) ? self::SUCCESS : self::FAILURE;
    }
}
