<?php

namespace App\Modules\Crm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComisionCalculada extends Model
{
    protected $table = 'comisiones_calculadas';

    protected $fillable = [
        'vendedor_id', 'anio', 'mes',
        'total_facturado', 'total_cobrado', 'base_comisionable',
        'porcentaje_aplicado', 'comision', 'bono_meta', 'total_a_pagar',
        'detalle_facturas', 'estado', 'calculada_at', 'aprobada_at', 'pagada_at',
        'aprobada_por', 'notas',
    ];

    protected $casts = [
        'anio' => 'integer',
        'mes' => 'integer',
        'total_facturado' => 'decimal:2',
        'total_cobrado' => 'decimal:2',
        'base_comisionable' => 'decimal:2',
        'porcentaje_aplicado' => 'decimal:2',
        'comision' => 'decimal:2',
        'bono_meta' => 'decimal:2',
        'total_a_pagar' => 'decimal:2',
        'detalle_facturas' => 'array',
        'calculada_at' => 'datetime',
        'aprobada_at' => 'datetime',
        'pagada_at' => 'datetime',
    ];

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobada_por');
    }

    public function periodoLabel(): string
    {
        $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        return $meses[$this->mes] . ' ' . $this->anio;
    }
}
