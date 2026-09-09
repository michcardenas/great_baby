<?php

namespace App\Modules\Cartera\Models;

use App\Modules\Dropi\Models\DropiDevolucion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Raíz D (DATOS C2) · Entidad de primer nivel para Notas Crédito.
 * DIAN exige que el emisor mantenga registro consecutivo local con CUFE.
 * Antes el módulo trataba la NC como side-effect de la devolución Dropi.
 *
 * Numeración: `NC-{consecutivo}` via SiguienteConsecutivoFactura::run('NC-', 4, rango).
 */
class NotaCredito extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected $table = 'notas_credito';

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

    public function devolucionDropi(): BelongsTo
    {
        return $this->belongsTo(DropiDevolucion::class, 'devolucion_dropi_id');
    }

    /** Consecutivo completo: prefijo-numero (con padding). */
    public function numeroCompleto(): string
    {
        return $this->prefijo . '-' . str_pad((string) $this->numero, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Re-audit RAÍZ F + SEG N3 + DATOS #6 · inmutabilidad fiscal post-emisión.
     * Mismo patrón que FacturaVenta — bloquea cambios a campos DIAN si ya
     * `emitida_at`. Impide que un futuro endpoint que reciba $request->all()
     * pueda re-escribir cufe/numero/estado ya declarados a DIAN.
     */
    protected static function booted(): void
    {
        static::saving(function (self $nc) {
            if (! $nc->exists || ! $nc->getOriginal('emitida_at')) return;

            $inmutables = ['numero', 'prefijo', 'cufe', 'siigo_id', 'emitida_at', 'valor', 'factura_id'];
            foreach ($inmutables as $campo) {
                if ($nc->isDirty($campo)) {
                    throw new \RuntimeException(
                        "NotaCredito {$nc->numeroCompleto()} ya emitida: campo '{$campo}' es inmutable (violación DIAN)."
                    );
                }
            }
        });
    }
}
