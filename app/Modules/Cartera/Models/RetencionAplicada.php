<?php

namespace App\Modules\Cartera\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Sprint 4 · B.3 · Registro de una retención aplicada a un documento (FC/pago).
 * Log auditable para certificados anuales de retención al proveedor.
 */
class RetencionAplicada extends Model
{
    protected $table = 'retenciones_aplicadas';
    protected $fillable = [
        'origen_type', 'origen_id', 'config_id', 'tipo',
        'base', 'tarifa_pct', 'valor', 'cuenta_puc', 'siigo_id',
    ];
    protected $casts = [
        'base' => 'decimal:2',
        'tarifa_pct' => 'decimal:3',
        'valor' => 'decimal:2',
    ];

    public function origen(): MorphTo { return $this->morphTo(); }
    public function config(): BelongsTo { return $this->belongsTo(RetencionConfig::class, 'config_id'); }
}
