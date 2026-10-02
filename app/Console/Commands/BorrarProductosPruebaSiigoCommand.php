<?php

namespace App\Console\Commands;

use App\Modules\Dropi\Models\Producto;
use App\Modules\Siigo\Clients\SiigoClient;
use App\Modules\Siigo\Models\SiigoConfig;
use App\Modules\Siigo\Models\SiigoSyncLog;
use Illuminate\Console\Command;

/**
 * Borra en SIIGO los productos de prueba creados por nosotros.
 *
 * Flujo por producto:
 *   1. Intenta DELETE /v1/products/{uuid}
 *   2. Si SIIGO responde `delete_not_allowed` (tenía movimientos),
 *      cae a PUT con active:false
 *   3. Si el DELETE funciona, también borra el registro local (hard-delete).
 *
 * Filtros disponibles para no tocar productos ajenos:
 *   --patron=E2E,TEST     · solo refs/códigos que contengan esos strings
 *   --desde=2026-10-01    · solo productos creados desde esa fecha
 *   --limit=50            · tope defensivo
 *
 * Uso:
 *   php artisan siigo:borrar-productos-prueba --patron=E2E,RAIZ,FIX,PRUEBA,TEST
 *   php artisan siigo:borrar-productos-prueba --patron=E2E --confirmar
 */
class BorrarProductosPruebaSiigoCommand extends Command
{
    protected $signature = 'siigo:borrar-productos-prueba
                            {--patron=E2E,RAIZ,FIX,PRUEBA,TEST : Patrones de referencia a borrar (separados por coma)}
                            {--desde= : Fecha ISO (YYYY-MM-DD) · solo productos creados desde esa fecha}
                            {--limit=100 : Tope defensivo de productos a tocar}
                            {--confirmar : Ejecuta de verdad (default: dry-run)}';

    protected $description = 'Borra en SIIGO + ERP los productos de prueba creados por nosotros · dry-run por default';

    public function handle(): int
    {
        $patrones = array_filter(array_map('trim', explode(',', (string) $this->option('patron'))));
        $desde = $this->option('desde');
        $limit = max(1, (int) $this->option('limit'));
        $confirmar = (bool) $this->option('confirmar');

        $q = Producto::whereNotNull('siigo_id')
            ->where('siigo_id', 'NOT LIKE', 'fake-%');  // excluir seeds de prueba vieja

        if (! empty($patrones)) {
            $q->where(function ($w) use ($patrones) {
                foreach ($patrones as $p) {
                    $w->orWhere('referencia', 'LIKE', "%{$p}%")
                      ->orWhere('siigo_code', 'LIKE', "%{$p}%")
                      ->orWhere('nombre', 'LIKE', "%{$p}%");
                }
            });
        }
        if ($desde) {
            $q->where('created_at', '>=', $desde);
        }

        $candidatos = $q->orderBy('id')->limit($limit)->get();

        if ($candidatos->isEmpty()) {
            $this->warn('Nada que borrar · 0 productos coinciden con los filtros.');
            return self::SUCCESS;
        }

        $this->info("Candidatos: {$candidatos->count()} productos");
        $this->table(['ID', 'Referencia', 'Nombre', 'siigo_id', 'Creado'],
            $candidatos->take(10)->map(fn ($p) => [
                $p->id, $p->referencia, substr($p->nombre, 0, 40), substr($p->siigo_id, 0, 20).'…', $p->created_at?->format('Y-m-d H:i'),
            ])->toArray());
        if ($candidatos->count() > 10) $this->line("… y {$candidatos->count()} - 10 más");

        if (! $confirmar) {
            $this->newLine();
            $this->warn('MODO AUDIT · no se borró nada.');
            $this->line('Repite el comando con --confirmar para ejecutar.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn("A punto de borrar {$candidatos->count()} productos en SIIGO + ERP. Procediendo...");

        $client = new SiigoClient(SiigoConfig::current());
        $borrados = 0;
        $desactivados = 0;
        $errores = 0;

        $bar = $this->output->createProgressBar($candidatos->count());
        $bar->start();

        foreach ($candidatos as $p) {
            try {
                // 1. Intento DELETE
                $r = $client->request('DELETE', "/v1/products/{$p->siigo_id}");
                if ($r->successful() || $r->status() === 204) {
                    // Borrado real · intento hard-delete local, si FK falla → soft-delete.
                    try {
                        $p->forceDelete();
                    } catch (\Throwable) {
                        $p->delete();
                    }
                    $borrados++;
                    $this->log('exitoso', $p->id, "DELETE OK · {$p->referencia}");
                } else {
                    $body = $r->json() ?? [];
                    $noPermitido = false;
                    foreach (($body['Errors'] ?? []) as $e) {
                        if (($e['Code'] ?? null) === 'delete_not_allowed') {
                            $noPermitido = true;
                            break;
                        }
                    }

                    if ($noPermitido) {
                        // 2. Fallback a PUT active:false · minimal payload (SIIGO
                        // rechaza con 400 si volvemos a mandar todo el objeto).
                        $get = $client->request('GET', "/v1/products/{$p->siigo_id}");
                        if ($get->successful()) {
                            $src = $get->json();
                            $minimal = [
                                'code' => $src['code'] ?? null,
                                'name' => $src['name'] ?? null,
                                'account_group' => is_array($src['account_group'] ?? null) ? $src['account_group']['id'] : ($src['account_group'] ?? null),
                                'type' => $src['type'] ?? 'Product',
                                'stock_control' => $src['stock_control'] ?? true,
                                'active' => false,
                                'tax_classification' => $src['tax_classification'] ?? 'Taxed',
                                'tax_included' => $src['tax_included'] ?? false,
                                'unit' => is_array($src['unit'] ?? null) ? $src['unit']['code'] : ($src['unit'] ?? '94'),
                                'unit_label' => $src['unit_label'] ?? 'Unidad',
                            ];
                            $put = $client->request('PUT', "/v1/products/{$p->siigo_id}", array_filter($minimal, fn ($v) => $v !== null));
                            if ($put->successful()) {
                                try { $p->forceFill(['activo' => false])->save(); } catch (\Throwable) {}
                                try { $p->delete(); } catch (\Throwable) {}
                                $desactivados++;
                                $this->log('exitoso', $p->id, "active:false · {$p->referencia}");
                            } else {
                                $errores++;
                                $body = substr(json_encode($put->json()), 0, 200);
                                $this->log('fallido', $p->id, "PUT active:false falló · http={$put->status()} · {$body}");
                            }
                        } else {
                            $errores++;
                            $this->log('fallido', $p->id, "GET fallo pre-fallback · http={$get->status()}");
                        }
                    } else {
                        $errores++;
                        $this->log('fallido', $p->id, "DELETE falló · http={$r->status()} · body=" . substr(json_encode($body), 0, 150));
                    }
                }
            } catch (\Throwable $e) {
                $errores++;
                $this->log('fallido', $p->id, 'Excepción: ' . $e->getMessage());
            }

            $bar->advance();
            usleep(100000);  // 100ms entre requests · respeto rate limit SIIGO 60/min
        }
        $bar->finish();
        $this->newLine(2);

        $this->table(['Resultado', 'Total'], [
            ['Borrados (DELETE)', $borrados],
            ['Desactivados (active:false)', $desactivados],
            ['Errores', $errores],
        ]);

        return self::SUCCESS;
    }

    private function log(string $estado, int $pid, string $mensaje): void
    {
        SiigoSyncLog::create([
            'recurso' => 'productos',
            'estado' => $estado,
            'nuevos' => 0,
            'actualizados' => 0,
            'errores' => $estado === 'fallido' ? 1 : 0,
            'duracion_ms' => 0,
            'mensaje' => "[borrar-prueba] p={$pid} · {$mensaje}",
            'detalle' => ['accion' => 'borrar-prueba', 'producto_id' => $pid],
            'user_id' => null,
        ]);
    }
}
