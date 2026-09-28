<?php

namespace App\Modules\Siigo\Observers;

use App\Modules\Cartera\Models\PagoProveedor;
use App\Modules\Siigo\Jobs\PushPagoProveedorASiigo;
use Illuminate\Support\Facades\DB;

class PagoProveedorObserver
{
    public function created(PagoProveedor $p): void
    {
        if ($p->siigo_voucher_id) return;
        $id = $p->id;
        DB::afterCommit(function () use ($id) {
            PushPagoProveedorASiigo::dispatch($id);
        });
    }
}
