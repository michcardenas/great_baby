<?php

namespace App\Console\Commands;

use App\Modules\Crm\Actions\SegmentarClientes;
use Illuminate\Console\Command;

class SegmentarClientesCommand extends Command
{
    protected $signature = 'crm:segmentar-clientes {--contacto=}';
    protected $description = 'Recalcula segmento (VIP/Frecuente/Dormido/Inactivo/En riesgo) para todos los clientes';

    public function handle(): int
    {
        $n = SegmentarClientes::run($this->option('contacto') ? (int) $this->option('contacto') : null);
        $this->info("Segmentados {$n} clientes.");
        return self::SUCCESS;
    }
}
