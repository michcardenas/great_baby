<?php

namespace App\Modules\Dropi\Models;

use App\Modules\Dropi\Enums\TipoMovimientoWallet;
use App\Modules\Dropi\Listeners\RegistrarAsientoWalletDropi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class DropiWalletMovimiento extends Model implements AuditableContract
{
    // Re-audit DR-η (DATOS-C2) · SoftDeletes por retención DIAN.
    use Auditable, SoftDeletes;

    protected static function booted(): void
    {
        // P4 · Cada wallet movement contabiliza automáticamente.
        // Re-audit DR-θ (FUNC-M2) · listener ya no hard-delete asientos —
        //   ahora genera ajustes signo inverso. Aquí igual re-lanzamos si
        //   cambian los campos que impactan contabilidad.
        static::created(function (self $m) {
            (new RegistrarAsientoWalletDropi())->handle($m);
        });
        static::updated(function (self $m) {
            // Ampliado (DATOS-M9) para incluir `dropi_movimiento_id` y `categoria`
            //   — antes no cubiertos y podían desfasar el asiento.
            if ($m->wasChanged(['monto', 'tipo', 'fecha', 'pedido_id', 'dropi_movimiento_id', 'categoria'])) {
                (new RegistrarAsientoWalletDropi())->handle($m);
            }
        });
    }

    protected $table = 'dropi_wallet_movimientos';

    // Re-audit DR-β (DATOS-C1) · $guarded. Antes: mass-assign de `monto`, `tipo`
    //   permitía inflar saldo desde payload de form. Ahora sólo por Actions
    //   (ConciliarWalletDropi, controller walletCrear con validación).
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'fecha' => 'date',
        'tipo' => TipoMovimientoWallet::class,
        'monto' => 'decimal:2',
        'fuente' => 'array',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(DropiPedido::class, 'pedido_id');
    }
}
