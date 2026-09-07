<?php

namespace App\Modules\Compras\Models;

use App\Models\User;
use App\Modules\Dropi\Models\InventarioUbicacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class RecepcionCompra extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected $table = 'compras_recepciones';

    // Fix auditor #20: 'estado', 'confirmada_at', 'total_recibido' sólo se setean desde RecibirMercancia.
    protected $fillable = [
        'numero', 'orden_id', 'bodega_id', 'recibido_por',
        'fecha_recepcion', 'remision_proveedor', 'factura_proveedor', 'transportista',
        'observaciones',
    ];

    protected static array $backendOnly = ['estado', 'confirmada_at', 'total_recibido'];

    protected $casts = [
        'fecha_recepcion' => 'date',
        'confirmada_at' => 'datetime',
        'total_recibido' => 'decimal:2',
    ];

    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class, 'orden_id');
    }

    public function bodega(): BelongsTo
    {
        return $this->belongsTo(InventarioUbicacion::class, 'bodega_id');
    }

    public function receptor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recibido_por');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RecepcionCompraItem::class, 'recepcion_id');
    }

    public static function siguienteNumero(): string
    {
        $year = now()->year;
        $ultimo = static::query()
            ->where('numero', 'like', "REC-{$year}-%")
            ->orderByDesc('id')
            ->value('numero');

        $seq = $ultimo ? ((int) substr($ultimo, -6)) + 1 : 1;

        return sprintf('REC-%d-%06d', $year, $seq);
    }
}
