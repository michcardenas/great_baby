<?php

namespace App\Modules\Cartera\Models;

use App\Models\Contacto;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class CondicionCredito extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'condiciones_credito';

    protected $fillable = [
        'contacto_id', 'cupo', 'plazo_dias',
        'descuento_pronto_pago_pct', 'plazo_pronto_pago_dias',
        'flete_asumido_gb', 'activa',
        'vigente_desde', 'vigente_hasta',
        'aprobada_por', 'notas',
    ];

    protected $casts = [
        'cupo' => 'decimal:2',
        'descuento_pronto_pago_pct' => 'decimal:2',
        'plazo_dias' => 'integer',
        'plazo_pronto_pago_dias' => 'integer',
        'flete_asumido_gb' => 'boolean',
        'activa' => 'boolean',
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
    ];

    public function contacto(): BelongsTo
    {
        return $this->belongsTo(Contacto::class);
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobada_por');
    }
}
