<?php

namespace App\Modules\Dropi\Models;

use App\Models\User;
use App\Modules\Dropi\Enums\EstadoCorte;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class DropiCorte extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'dropi_cortes';

    protected $fillable = [
        'fecha', 'numero', 'estado',
        'pedidos_totales', 'pedidos_pendientes_inv', 'pedidos_despachados',
        'cerrado_por', 'cerrado_at',
        'manifiesto_hash', 'manifiesto_pdf_path', 'snapshot_json',
    ];

    protected $casts = [
        'fecha' => 'date',
        'estado' => EstadoCorte::class,
        'cerrado_at' => 'datetime',
        'pedidos_totales' => 'integer',
        'pedidos_pendientes_inv' => 'integer',
        'pedidos_despachados' => 'integer',
        'snapshot_json' => 'array',
    ];

    public function pedidos(): HasMany
    {
        return $this->hasMany(DropiPedido::class, 'corte_id');
    }

    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }

    public function etiqueta(): string
    {
        return $this->fecha->format('Y-m-d') . ' — Corte ' . $this->numero;
    }
}
