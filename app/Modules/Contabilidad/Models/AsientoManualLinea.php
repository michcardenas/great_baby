<?php

namespace App\Modules\Contabilidad\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsientoManualLinea extends Model
{
    protected $table = 'asiento_manual_lineas';
    protected $guarded = ['id'];

    protected $casts = [
        'debe' => 'decimal:2',
        'haber' => 'decimal:2',
    ];

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(AsientoManual::class, 'asiento_manual_id');
    }

    // QA-FIX #3 · inmutabilidad de línea si el asiento ya fue aprobado o sincronizado.
    protected static function booted(): void
    {
        static::saving(function (self $l) {
            if (! $l->exists) return; // creación permitida
            $asiento = $l->asiento()->first();
            if (! $asiento) return;
            if (in_array($asiento->estado, ['aprobado', 'sincronizado'], true)) {
                throw new \RuntimeException("Línea del asiento #{$asiento->id} no editable en estado '{$asiento->estado}'.");
            }
        });
        static::deleting(function (self $l) {
            $asiento = $l->asiento()->first();
            if ($asiento && in_array($asiento->estado, ['aprobado', 'sincronizado'], true)) {
                throw new \RuntimeException("Línea del asiento #{$asiento->id} no borrable en estado '{$asiento->estado}'.");
            }
        });
    }
}
