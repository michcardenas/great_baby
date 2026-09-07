<?php

namespace App\Models;

use App\Modules\Cartera\Models\CondicionCredito;
use App\Modules\Cartera\Models\FacturaVenta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Contacto unificado — cliente, proveedor, empleado, vendedor dropi.
 * Los flags booleanos definen los roles que cumple.
 */
class Contacto extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected $table = 'contactos';

    protected $fillable = [
        'tipo_documento', 'numero_documento', 'nombre_completo', 'razon_social',
        'email', 'telefono', 'direccion', 'ciudad', 'departamento',
        'es_cliente', 'es_cliente_b2b', 'es_proveedor', 'es_empleado', 'es_vendedor_dropi',
        'regimen_iva', 'retenciones_default', 'activo',
    ];

    protected $casts = [
        'es_cliente' => 'boolean',
        'es_cliente_b2b' => 'boolean',
        'es_proveedor' => 'boolean',
        'es_empleado' => 'boolean',
        'es_vendedor_dropi' => 'boolean',
        'activo' => 'boolean',
        'retenciones_default' => 'array',
    ];

    public function condicionVigente(): HasOne
    {
        return $this->hasOne(CondicionCredito::class)
            ->where('activa', true)
            ->latest('vigente_desde');
    }

    public function condiciones(): HasMany
    {
        return $this->hasMany(CondicionCredito::class);
    }

    public function facturas(): HasMany
    {
        return $this->hasMany(FacturaVenta::class);
    }

    public function nombreDisplay(): string
    {
        return $this->razon_social ?: $this->nombre_completo;
    }
}
