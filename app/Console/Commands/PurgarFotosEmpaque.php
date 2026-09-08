<?php

namespace App\Console\Commands;

use App\Modules\Dropi\Models\EmpaqueRegistro;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Purga fotos de empaque más viejas que `empaque.retention_foto_dias`.
 * Cumple con Habeas Data (minimización de datos personales).
 *
 * Reglas:
 *  - Solo empaques cerrados (completado/anulado) — NUNCA borra evidencia de un en_curso.
 *  - Storage::delete devuelve bool: si falla, NO limpiamos el foto_path para no huerfanar el registro.
 *  - Cada borrado deja constancia en logs (auditable) — quién purgó qué.
 */
class PurgarFotosEmpaque extends Command
{
    protected $signature = 'gb:purgar-fotos-empaque {--dry-run : Solo mostrar cuántas se borrarían} {--reset-intentos : Resetea el contador de fallos para reintentar fotos previamente descartadas}';
    protected $description = 'Purga fotos de empaque antiguas según reglas de retención (Habeas Data)';

    public function handle(): int
    {
        if ($this->option('reset-intentos')) {
            $n = EmpaqueRegistro::where('foto_purga_intentos', '>=', 5)
                ->update(['foto_purga_intentos' => 0, 'foto_purga_ultimo_fallo_at' => null]);
            $this->info("✅ Reseteados {$n} registros. Corré el comando de nuevo para reintentar.");
            return self::SUCCESS;
        }

        $dias = (int) setting('empaque.retention_foto_dias', 180);
        if ($dias <= 0) {
            $this->warn('empaque.retention_foto_dias inválido, aborto.');
            return self::FAILURE;
        }
        $limite = now()->subDays($dias);

        // Excluir fotos que fallaron 5+ veces (evita loop infinito de warnings — Sec F7).
        $maxIntentos = 5;
        $registros = EmpaqueRegistro::whereNotNull('foto_path')
            ->where('foto_at', '<=', $limite)
            ->whereIn('estado', ['completado', 'anulado'])
            ->where('foto_purga_intentos', '<', $maxIntentos)
            ->get(['id', 'foto_path', 'operario_id', 'pedido_id', 'foto_purga_intentos']);

        $this->info("Encontradas {$registros->count()} fotos elegibles (>{$dias} días, estado terminal).");

        if ($this->option('dry-run')) {
            $registros->take(10)->each(fn ($r) => $this->line(" · #{$r->id} → {$r->foto_path}"));
            return self::SUCCESS;
        }

        $borradas = 0;
        $errores = 0;
        foreach ($registros as $r) {
            try {
                $existia = Storage::disk('local')->exists($r->foto_path);
                if ($existia) {
                    $ok = Storage::disk('local')->delete($r->foto_path);
                    if (! $ok) {
                        // Incrementar intento y grabar timestamp — tras 5 se excluye
                        // permanentemente (arriba en el where), evitando loop de warnings.
                        $r->update([
                            'foto_purga_intentos' => (int) $r->foto_purga_intentos + 1,
                            'foto_purga_ultimo_fallo_at' => now(),
                        ]);
                        Log::warning('[Purga fotos] Storage::delete devolvió false', [
                            'registro' => $r->id, 'path' => $r->foto_path,
                            'intento' => $r->foto_purga_intentos + 1, 'max' => $maxIntentos,
                        ]);
                        $errores++;
                        continue;
                    }
                }
                $r->update(['foto_path' => null, 'foto_at' => null]);
                Log::info('[Purga fotos] Foto purgada (Habeas Data)', [
                    'registro' => $r->id,
                    'operario_id' => $r->operario_id,
                    'pedido_id' => $r->pedido_id,
                    'retention_dias' => $dias,
                ]);
                $borradas++;
            } catch (\Throwable $e) {
                $errores++;
                // Incrementar intentos también en excepción, no solo en delete=false.
                try {
                    $r->update([
                        'foto_purga_intentos' => (int) $r->foto_purga_intentos + 1,
                        'foto_purga_ultimo_fallo_at' => now(),
                    ]);
                } catch (\Throwable $ignored) {}
                $this->error("Error borrando #{$r->id}: {$e->getMessage()}");
                Log::error('[Purga fotos] Excepción', ['registro' => $r->id, 'error' => $e->getMessage()]);
            }
        }

        $this->info("✅ {$borradas} fotos purgadas · {$errores} errores.");
        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }
}
