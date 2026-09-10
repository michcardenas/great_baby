<?php

namespace App\Modules\Dropi\Models;

use App\Models\User;
use App\Modules\Dropi\Enums\EstadoCorte;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class DropiCorte extends Model implements AuditableContract
{
    // Re-audit DR-η (DATOS-C2) · SoftDeletes por retención DIAN 5 años.
    use Auditable, SoftDeletes;

    protected $table = 'dropi_cortes';

    // Re-audit DR-β (DATOS-C1) · $guarded. Antes: `$corte->update(['estado'=>'abierto'])`
    //   REABRÍA un corte cerrado bypasseando el hash. Ahora estado/cerrado_*/
    //   manifiesto_hash sólo se escriben por Action canónica (CerrarCorte).
    protected $guarded = ['id', 'estado', 'cerrado_por', 'cerrado_at', 'manifiesto_hash', 'created_at', 'updated_at', 'deleted_at'];

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
