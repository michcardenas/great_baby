<?php

namespace App\Console\Commands;

use App\Modules\Cartera\Services\CobranzaWhatsApp;
use Illuminate\Console\Command;

class CobrarClientesCommand extends Command
{
    protected $signature = 'cartera:cobrar';

    protected $description = 'Ejecuta el barrido diario de cobranza WhatsApp por antigüedad.';

    public function handle(CobranzaWhatsApp $c): int
    {
        $r = $c->ejecutarBarrido();
        $this->info("Procesadas: {$r['procesadas']} · Enviadas: {$r['enviadas']} · Sin tel: {$r['sin_telefono']} · Escaladas 120+: {$r['escaladas']}");
        return self::SUCCESS;
    }
}
