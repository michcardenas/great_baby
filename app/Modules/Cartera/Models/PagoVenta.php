<?php

namespace App\Modules\Cartera\Models;

use App\Models\Contacto;
use App\Models\User;
use App\Modules\Cartera\Enums\ClasificacionDiferencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class PagoVenta extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected $table = 'pagos_venta';

    // Los hooks created/deleted/restored viven en PagoVentaObserver
    // (registrado en AppServiceProvider). Se movió fuera de booted() porque
    // el trait OwenIt\Auditing\Auditable intercepta algunos eventos.

    protected $fillable = [
        'factura_id', 'contacto_id', 'fecha',
        'monto_recibido', 'monto_aplicado', 'diferencia',
        'clasificacion_diferencia', 'medio_pago', 'referencia', 'banco',
        'registrado_por', 'notas',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto_recibido' => 'decimal:2',
        'monto_aplicado' => 'decimal:2',
        'diferencia' => 'decimal:2',
        'clasificacion_diferencia' => ClasificacionDiferencia::class,
    ];

    public function factura(): BelongsTo
    {
        return $this->belongsTo(FacturaVenta::class, 'factura_id');
    }

    public function contacto(): BelongsTo
    {
        return $this->belongsTo(Contacto::class);
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
