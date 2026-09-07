<?php

namespace App\Modules\Cartera\Models;

use App\Models\Contacto;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class SolicitudCredito extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'solicitudes_credito';

    protected $fillable = [
        'contacto_id', 'monto_pedido', 'motivo_retencion', 'snapshot_credito',
        'estado', 'nivel_actual',
        'solicitada_por', 'resuelta_por', 'resolucion_notas', 'resuelta_at',
    ];

    protected $casts = [
        'monto_pedido' => 'decimal:2',
        'snapshot_credito' => 'array',
        'resuelta_at' => 'datetime',
    ];

    public function contacto(): BelongsTo { return $this->belongsTo(Contacto::class); }
    public function solicitante(): BelongsTo { return $this->belongsTo(User::class, 'solicitada_por'); }
    public function resolutor(): BelongsTo { return $this->belongsTo(User::class, 'resuelta_por'); }
}
