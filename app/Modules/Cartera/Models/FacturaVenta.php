<?php

namespace App\Modules\Cartera\Models;

use App\Models\Contacto;
use App\Models\User;
use App\Modules\Cartera\Enums\EstadoFactura;
use App\Modules\Cartera\Enums\TramoAntiguedad;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class FacturaVenta extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected $table = 'facturas_venta';

    /**
     * Excluidos de auditoría: metadata cruda de SIIGO / QR / token que
     * duplicarlos en `audits` es ruidoso y filtra datos sensibles.
     */
    protected array $auditExclude = [
        'siigo_response', 'qr_html', 'qr_url', 'token_publico', 'emitiendo_at',
    ];

    protected $hidden = ['siigo_response', 'token_publico'];

    protected static function booted(): void
    {
        // El asiento contable se registra al SALVAR una factura con ítems y total>0.
        // Antes se disparaba en `created` con la factura vacía → asiento falso.
        static::saved(function (FacturaVenta $f) {
            if (! $f->wasRecentlyCreated) {
                return; // solo al crear, no en cada update
            }
            if ((float) $f->total <= 0) {
                return; // sin monto no hay asiento
            }
            if ($f->items()->count() === 0) {
                return; // sin ítems no hay asiento
            }
            // Idempotencia: si ya hay asiento origen=esta factura, no volver a generar
            if (\App\Modules\Cartera\Models\MovimientoContable::where('origen_type', static::class)
                ->where('origen_id', $f->id)->exists()) {
                return;
            }
            (new \App\Modules\Cartera\Actions\RegistrarAsientoContable())->factura($f);
        });
    }

    protected $fillable = [
        'numero', 'contacto_id', 'fecha_emision', 'fecha_vencimiento',
        'estado', 'subtotal', 'descuento', 'impuestos', 'total', 'saldo',
        'ari_factura_id', 'cufe', 'ari_enviada_at',
        'siigo_id', 'numero_siigo', 'stamp_status', 'siigo_response',
        'qr_url', 'qr_html', 'token_publico', 'es_electronica', 'emitida_at', 'emitiendo_at',
        'origen_type', 'origen_id',
        'vendedor_id', 'observaciones',
    ];

    protected $casts = [
        'estado' => EstadoFactura::class,
        'fecha_emision' => 'date',
        'fecha_vencimiento' => 'date',
        'ari_enviada_at' => 'datetime',
        'emitida_at' => 'datetime',
        'emitiendo_at' => 'datetime',
        'es_electronica' => 'boolean',
        'siigo_response' => 'array',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'impuestos' => 'decimal:2',
        'total' => 'decimal:2',
        'saldo' => 'decimal:2',
    ];

    public function contacto(): BelongsTo
    {
        return $this->belongsTo(Contacto::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FacturaVentaItem::class, 'factura_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(PagoVenta::class, 'factura_id');
    }

    public function cobranzas(): HasMany
    {
        return $this->hasMany(CobranzaRegistro::class, 'factura_id');
    }

    public function origen(): MorphTo
    {
        return $this->morphTo('origen', 'origen_type', 'origen_id');
    }

    public function diasMora(): int
    {
        if ($this->estado === EstadoFactura::Pagada || $this->estado === EstadoFactura::Anulada) {
            return 0;
        }
        return (int) now()->startOfDay()->diffInDays($this->fecha_vencimiento->startOfDay(), false) * -1;
    }

    public function tramo(): TramoAntiguedad
    {
        return TramoAntiguedad::delDias($this->diasMora());
    }

    /**
     * Recalcula saldo y estado a partir de pagos.
     * Idempotente — se puede llamar varias veces.
     * NO revive facturas anuladas (auditor L2 flujo D).
     */
    public function recalcular(): void
    {
        if ($this->estado === EstadoFactura::Anulada) {
            return;
        }

        $totalPagado = (float) $this->pagos()->sum('monto_aplicado');
        $this->saldo = max(0, (float) $this->total - $totalPagado);

        if ($this->saldo <= 0.009) {
            $this->estado = EstadoFactura::Pagada;
        } elseif ($totalPagado > 0) {
            $this->estado = EstadoFactura::Abonada;
        } elseif ($this->fecha_vencimiento->lt(now()->startOfDay())) {
            $this->estado = EstadoFactura::Vencida;
        } else {
            $this->estado = EstadoFactura::Pendiente;
        }

        $this->save();
    }
}
