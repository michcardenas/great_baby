<?php

namespace App\Modules\Cartera\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Sprint 4 · B.2 · Notas Débito.
 * Numeración: ND-{consecutivo} via SiguienteConsecutivoFactura::run('ND').
 * Inmutabilidad DIAN igual que NotaCredito.
 */
class NotaDebito extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected $table = 'notas_debito';
    protected $guarded = ['id'];
    protected $hidden = ['siigo_response'];
    protected array $auditExclude = ['siigo_response'];

    protected $casts = [
        'valor' => 'decimal:2',
        'emitida_at' => 'datetime',
        'aceptada_dian_at' => 'datetime',
        'siigo_response' => 'array',
    ];

    public function factura(): BelongsTo
    {
        return $this->belongsTo(FacturaVenta::class);
    }

    public function numeroCompleto(): string
    {
        return $this->prefijo . '-' . str_pad((string) $this->numero, 4, '0', STR_PAD_LEFT);
    }

    protected static function booted(): void
    {
        static::saving(function (self $nd) {
            if (! $nd->exists || ! $nd->getOriginal('emitida_at')) return;
            $inmutables = ['numero', 'prefijo', 'cufe', 'siigo_id', 'emitida_at', 'valor', 'factura_id'];
            foreach ($inmutables as $campo) {
                if ($nd->isDirty($campo)) {
                    throw new \RuntimeException(
                        "NotaDebito {$nd->numeroCompleto()} ya emitida: campo '{$campo}' es inmutable (violación DIAN)."
                    );
                }
            }
        });
    }
}
