<?php

namespace App\Modules\Dropi\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class DropiDevolucion extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected $table = 'dropi_devoluciones';

    protected static function booted(): void
    {
        static::created(function (DropiDevolucion $d) {
            \App\Modules\Cartera\Actions\ContabilizarDevolucionDropi::run($d);
        });
    }

    protected $fillable = [
        'pedido_id', 'recibido_at', 'destino_inventario',
        'decision_por', 'genero_nota_credito', 'nota_credito_ari_id', 'notas',
    ];

    protected $casts = [
        'recibido_at' => 'datetime',
        'genero_nota_credito' => 'boolean',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(DropiPedido::class, 'pedido_id');
    }

    public function decidioAlistador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decision_por');
    }
}
