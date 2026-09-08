<?php

namespace App\Models;

use App\Modules\Cartera\Models\CondicionCredito;
use App\Modules\Cartera\Models\FacturaVenta;
use App\Modules\Catalogo\Models\ListaPrecios;
use App\Modules\Portal\Models\PedidoCliente;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Contacto unificado — cliente, proveedor, empleado, vendedor dropi.
 * Los flags booleanos definen los roles que cumple.
 * Implementa Authenticatable para el portal B2B (guard 'cliente').
 */
class Contacto extends Model implements AuditableContract, AuthenticatableContract, CanResetPasswordContract
{
    use Auditable, Authenticatable, CanResetPassword, SoftDeletes;

    protected $table = 'contactos';

    // QA-D Bloque2: fillable con campos operativos. Los REALMENTE sensibles del portal (password,
    // portal_habilitado, reset_token) SE QUITAN del fillable — deben setearse por forceFill()
    // en código admin explícito. Evita `Contacto::update($request->all())` que otorgue portal.
    protected $fillable = [
        'tipo_documento', 'numero_documento', 'nombre_completo', 'razon_social',
        'email', 'telefono', 'direccion', 'ciudad', 'departamento',
        'es_cliente', 'es_cliente_b2b', 'es_proveedor', 'es_empleado', 'es_vendedor_dropi',
        'regimen_iva', 'retenciones_default', 'activo',
        // CRM segmentación (setea el motor, no el request web)
        'segmento', 'segmento_score', 'ticket_promedio', 'ultima_compra_at',
        'total_comprado_ytd', 'segmentado_at',
        // Portal B2B — operativos, no auth
        'lista_precios_id', 'ultimo_login_at',
        // password, portal_habilitado, reset_token, reset_token_at INTENCIONALMENTE OMITIDOS.
    ];

    protected $hidden = ['password', 'remember_token', 'reset_token'];

    protected $casts = [
        'es_cliente' => 'boolean',
        'es_cliente_b2b' => 'boolean',
        'es_proveedor' => 'boolean',
        'es_empleado' => 'boolean',
        'es_vendedor_dropi' => 'boolean',
        'activo' => 'boolean',
        'retenciones_default' => 'array',
        'portal_habilitado' => 'boolean',
        'ultimo_login_at' => 'datetime',
        'reset_token_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function listaPrecios(): BelongsTo
    {
        return $this->belongsTo(ListaPrecios::class, 'lista_precios_id');
    }

    public function pedidosB2b(): HasMany
    {
        return $this->hasMany(PedidoCliente::class, 'contacto_id');
    }

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
