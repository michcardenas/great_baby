<?php

namespace App\Modules\Compras\Models;

use App\Models\Contacto;
use App\Modules\Compras\Enums\ConceptoGastoImportacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GastoImportacion extends Model
{
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
