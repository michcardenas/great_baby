<?php

namespace App\Modules\Contabilidad\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Conciliación bancaria diaria por cuenta. Se exporta a SIIGO como asiento (borrador).
 */
class ConciliacionBancaria extends Model
{
    protected $table = 'conciliaciones_bancarias';

    protected $fillable = [
        'fecha', 'banco', 'cuenta_puc',
        'saldo_extracto', 'saldo_sistema', 'diferencia',
        'estado', 'notas',
        'siigo_id', 'siigo_borrador', 'siigo_exportado_at',
        'user_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'saldo_extracto' => 'decimal:2',
        'saldo_sistema' => 'decimal:2',
        'diferencia' => 'decimal:2',
        'siigo_borrador' => 'bool',
        'siigo_exportado_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (ConciliacionBancaria $c) {
            $c->diferencia = round((float) $c->saldo_extracto - (float) $c->saldo_sistema, 2);
        });
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
