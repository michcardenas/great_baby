<?php

namespace App\Modules\Dropi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class DropiSancion extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'dropi_sanciones';

    protected $fillable = [
        'pedido_id', 'tipo',
        'monto_esperado', 'monto_recibido', 'diferencia',
        'detectada_at',
    ];

    protected $casts = [
        'monto_esperado' => 'decimal:2',
        'monto_recibido' => 'decimal:2',
        'diferencia' => 'decimal:2',
        'detectada_at' => 'datetime',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(DropiPedido::class, 'pedido_id');
    }
}
