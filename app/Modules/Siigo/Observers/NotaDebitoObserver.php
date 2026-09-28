<?php

namespace App\Modules\Siigo\Observers;

use App\Modules\Cartera\Models\NotaDebito;
use App\Modules\Siigo\Jobs\PushNotaDebitoASiigo;
use Illuminate\Support\Facades\DB;

class NotaDebitoObserver
{
    public function created(NotaDebito $nd): void
    {
        if ($nd->siigo_id) return;
        $id = $nd->id;
        DB::afterCommit(function () use ($id) {
            PushNotaDebitoASiigo::dispatch($id);
            \App\Modules\Notificaciones\Jobs\NotificarCadenaValorJob::dispatch('nd-emitida', $id);
        });
    }
}
