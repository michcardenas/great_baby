<?php

namespace App\Modules\Compras\Models;

use App\Models\User;
use App\Modules\Compras\Enums\EstadoImportacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Importacion extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected $table = 'compras_importaciones';

    // Fix auditor #20: 'estado', 'liquidada_por', 'valor_*_costo' sólo se setean desde LiquidarImportacion.
    protected $fillable = [
        'numero', 'contenedor', 'bl_awb',
        'proveedor_pais', 'puerto_origen', 'puerto_destino', 'incoterm',
        'moneda_origen', 'tasa_cambio_liquidacion',
        'fecha_zarpe', 'eta', 'fecha_llegada', 'fecha_nacionalizacion', 'fecha_liquidacion',
        'observaciones', 'creado_por',
    ];

    protected static array $backendOnly = ['estado', 'liquidada_por',
        'valor_fob', 'valor_gastos', 'valor_arancel', 'valor_iva_importacion', 'valor_total_costo'];

    protected $casts = [
        'fecha_zarpe' => 'date',
        'eta' => 'date',
        'fecha_llegada' => 'date',
        'fecha_nacionalizacion' => 'date',
        'fecha_liquidacion' => 'date',
        'estado' => EstadoImportacion::class,
        'tasa_cambio_liquidacion' => 'decimal:6',
        'valor_fob' => 'decimal:2',
        'valor_gastos' => 'decimal:2',
        'valor_arancel' => 'decimal:2',
        'valor_iva_importacion' => 'decimal:2',
        'valor_total_costo' => 'decimal:2',
    ];

    public function ordenes(): BelongsToMany
    {
        return $this->belongsToMany(OrdenCompra::class, 'compras_importacion_ordenes', 'importacion_id', 'orden_id')
            ->withPivot('peso_kg', 'volumen_m3')
            ->withTimestamps();
    }

    public function gastos(): HasMany
    {
        return $this->hasMany(GastoImportacion::class, 'importacion_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(ImportacionLinea::class, 'importacion_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function liquidador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'liquidada_por');
    }

    public static function siguienteNumero(): string
    {
        $year = now()->year;
        $ultimo = static::query()
            ->where('numero', 'like', "IMP-{$year}-%")
            ->orderByDesc('id')
            ->value('numero');

        $seq = $ultimo ? ((int) substr($ultimo, -6)) + 1 : 1;

        return sprintf('IMP-%d-%06d', $year, $seq);
    }

    public function diasEnTransito(): ?int
    {
        if (! $this->fecha_zarpe) {
            return null;
        }
        $fin = $this->fecha_llegada ?? now();

        return $this->fecha_zarpe->diffInDays($fin);
    }
}
