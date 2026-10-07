<?php

namespace App\Console\Commands;

use App\Modules\Siigo\Clients\SiigoClient;
use App\Modules\Siigo\Models\SiigoTax;
use Illuminate\Console\Command;

/**
 * `php artisan siigo:sync-taxes` · catálogo /v1/taxes.
 *
 * Trae todos los taxes del tenant (IVA, Retefuente, ReteICA, etc.) y hace
 * upsert sobre `siigo_taxes`. El panel Reglas lo consulta para poblar los
 * selectores de tax_id_iva_19, tax_id_retefuente, etc. tras conectar la
 * cuenta real del cliente — así Aracely no pega ids a mano.
 */
class SyncSiigoTaxesCommand extends Command
{
    protected $signature = 'siigo:sync-taxes';

    protected $description = 'Sincroniza el catálogo /v1/taxes desde SIIGO (IVA, Retefuente, ReteICA, etc.)';

    public function handle(SiigoClient $client): int
    {
        $resp = $client->request('GET', '/v1/taxes');
        if (! $resp->ok()) {
            $this->error("HTTP {$resp->status()} · ".mb_substr((string) $resp->body(), 0, 200));
            return self::FAILURE;
        }
        $n = 0;
        foreach ($resp->json() ?? [] as $t) {
            SiigoTax::updateOrCreate(
                ['siigo_id' => (int) ($t['id'] ?? 0)],
                [
                    'type' => (string) ($t['type'] ?? ''),
                    'name' => mb_substr((string) ($t['name'] ?? ''), 0, 180),
                    'percentage' => (float) ($t['percentage'] ?? 0),
                    'active' => (bool) ($t['active'] ?? true),
                    'synced_at' => now(),
                ]
            );
            $n++;
        }
        $this->info("TOTAL taxes sincronizados: {$n}");
        return self::SUCCESS;
    }
}
