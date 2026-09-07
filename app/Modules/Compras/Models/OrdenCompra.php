<?php

namespace App\Modules\Compras\Models;

use App\Models\Contacto;
use App\Models\User;
use App\Modules\Cartera\Models\CondicionCredito;
use App\Modules\Compras\Enums\EstadoOrdenCompra;
use App\Modules\Dropi\Models\InventarioUbicacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class OrdenCompra extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected $table = 'compras_ordenes';

    // Fix auditor #20: 'estado', 'aprobado_por', 'aprobado_at', 'subtotal', 'iva', 'total'
    // sólo se setean desde Actions/backend, nunca por payload directo.
    protected $fillable = [
        'numero', 'proveedor_id', 'bodega_id', 'condicion_credito_id',
        'tipo', 'moneda', 'tasa_cambio',
        'fecha_emision', 'fecha_esperada',
        'descuento', 'reteica', 'retefuente', 'reteiva',
        'creado_por', 'observaciones', 'meta',
    ];

    /** Campos controlados sólo por Actions (RecalcularTotalesOC, AprobarOrdenCompra, RecibirMercancia). */
    protected static array $backendOnly = ['estado', 'aprobado_por', 'aprobado_at', 'subtotal', 'iva', 'total'];

    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_esperada' => 'date',
        'aprobado_at' => 'datetime',
        'estado' => EstadoOrdenCompra::class,
        'meta' => 'array',
        'tasa_cambio' => 'decimal:6',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'iva' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Contacto::class, 'proveedor_id');
    }

    public function bodega(): BelongsTo
    {
        return $this->belongsTo(InventarioUbicacion::class, 'bodega_id');
    }

    public function condicionCredito(): BelongsTo
    {
        return $this->belongsTo(CondicionCredito::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrdenCompraItem::class, 'orden_id');
    }

    public function recepciones(): HasMany
    {
        return $this->hasMany(RecepcionCompra::class, 'orden_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function esImportacion(): bool
    {
        return $this->tipo === 'importacion';
    }

    public function porcentajeRecibido(): float
    {
        $total = $this->items->sum('cantidad');
        if ($total <= 0) {
            return 0;
        }

        return round($this->items->sum('cantidad_recibida') / $total * 100, 1);
    }

    public static function siguienteNumero(): string
    {
        $year = now()->year;
        $ultimo = static::query()
            ->where('numero', 'like', "OC-{$year}-%")
            ->orderByDesc('id')
            ->value('numero');

        $seq = $ultimo ? ((int) substr($ultimo, -6)) + 1 : 1;

        return sprintf('OC-%d-%06d', $year, $seq);
    }
}
