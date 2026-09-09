<?php

namespace App\Modules\Compras\Models;

use App\Models\Contacto;
use App\Modules\Compras\Enums\ConceptoGastoImportacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class GastoImportacion extends Model implements AuditableContract
{
    // Re-audit M2 PATRÓN K (DATOS-A5) · SoftDeletes + Auditable en gasto:
    //   Antes: eliminar un gasto post-liquidación borraba la evidencia contable
    //   de flete/arancel — el asiento 1435/2205 quedaba sin soporte. Ahora se
    //   soft-borra (DIAN 5 años) y se audita el cambio.
    use SoftDeletes, Auditable;

    protected $table = 'compras_importacion_gastos';

    protected $fillable = [
        'importacion_id', 'proveedor_id',
        'concepto', 'descripcion',
        'moneda', 'monto', 'monto_base',
        'capitalizable', 'metodo_prorrateo',
        'factura_proveedor', 'fecha',
    ];

    protected $casts = [
        'concepto' => ConceptoGastoImportacion::class,
        'monto' => 'decimal:2',
        'monto_base' => 'decimal:2',
        'capitalizable' => 'boolean',
        'fecha' => 'date',
    ];

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(Importacion::class, 'importacion_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Contacto::class, 'proveedor_id');
    }
}
