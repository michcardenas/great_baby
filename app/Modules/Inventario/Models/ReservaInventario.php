<?php

namespace App\Modules\Inventario\Models;

use App\Models\User;
use App\Modules\Dropi\Models\InventarioUbicacion;
use App\Modules\Dropi\Models\Producto;
use App\Modules\Dropi\Models\ProductoVariante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

// Re-audit M3 PATRÓN η (DATOS-C7) · Auditable + $guarded — antes bulk
//   update ->update(['activa'=>false]) desactivaba reservas sin traza DIAN.
class ReservaInventario extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'reservas_inventario';

    protected $guarded = ['id'];

    protected $casts = [
        'cantidad' => 'integer',
        'expira_at' => 'datetime',
        'activa' => 'boolean',
    ];

    // C-F1 · Una reserva apunta a variante (granular) O a producto (agregado).
    public function variante(): BelongsTo
    {
        return $this->belongsTo(ProductoVariante::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(InventarioUbicacion::class);
    }

    /** ¿Reserva sobre producto agregado (sin desglose por variante)? */
    public function esAgregada(): bool
    {
        return $this->variante_id === null && $this->producto_id !== null;
    }

    public function origen(): MorphTo
    {
        return $this->morphTo('origen', 'origen_type', 'origen_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
