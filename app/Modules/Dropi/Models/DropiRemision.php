<?php

namespace App\Modules\Dropi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class DropiRemision extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'dropi_remisiones';

    protected $fillable = [
        'pedido_id', 'consecutivo', 'valor_proveedor',
        'ari_lote_id', 'ari_factura_id',
        'enviada_ari_at', 'cufe', 'estado_dian',
    ];

    protected $casts = [
        'valor_proveedor' => 'decimal:2',
        'enviada_ari_at' => 'datetime',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(DropiPedido::class, 'pedido_id');
    }
}
