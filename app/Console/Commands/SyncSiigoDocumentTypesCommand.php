<?php

namespace App\Console\Commands;

use App\Modules\Siigo\Clients\SiigoClient;
use App\Modules\Siigo\Models\SiigoDocumentType;
use Illuminate\Console\Command;

/**
 * `php artisan siigo:sync-document-types` · catálogo /v1/document-types.
 *
 * Trae todos los tipos activos de los 7 segmentos que SIIGO expone y hace
 * upsert sobre `siigo_document_types`. El panel Reglas lo consulta para
 * poblar los selectores de doc_type_compra, doc_type_nc_compra, etc. tras
 * conectar la cuenta real del cliente.
 */
class SyncSiigoDocumentTypesCommand extends Command
{
    protected $signature = 'siigo:sync-document-types {--only=}';

    protected $description = 'Sincroniza el catálogo de document-types desde SIIGO (FC/FV/NC/ND/RP/DS/CC)';

    private const TYPES = ['FC', 'FV', 'NC', 'ND', 'RP', 'DS', 'CC', 'RC'];

    public function handle(SiigoClient $client): int
    {
        $only = $this->option('only');
        $types = $only ? array_map('strtoupper', explode(',', $only)) : self::TYPES;
        $total = 0;

        foreach ($types as $type) {
            $resp = $client->request('GET', '/v1/document-types', ['type' => $type]);
            if (! $resp->ok()) {
                $this->warn("type={$type} HTTP {$resp->status()} — omitido");
                continue;
            }
            $items = $resp->json() ?? [];
            $n = 0;
            foreach ($items as $d) {
                SiigoDocumentType::updateOrCreate(
                    ['type' => $type, 'siigo_id' => (int) ($d['id'] ?? 0)],
                    [
                        'code' => (string) ($d['code'] ?? ''),
                        'name' => mb_substr((string) ($d['name'] ?? ''), 0, 180),
                        'active' => (bool) ($d['active'] ?? true),
                        'cost_center' => (bool) ($d['cost_center'] ?? false),
                        'cost_center_mandatory' => (bool) ($d['cost_center_mandatory'] ?? false),
                        'automatic_number' => (bool) ($d['automatic_number'] ?? false),
                        'consecutive' => isset($d['consecutive']) ? (int) $d['consecutive'] : null,
                        'synced_at' => now(),
                    ]
                );
                $n++;
            }
            $this->info("type={$type} · {$n} documentos sincronizados");
            $total += $n;
        }
        $this->line("TOTAL: {$total}");
        return self::SUCCESS;
    }
}
