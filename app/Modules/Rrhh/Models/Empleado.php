<?php

namespace App\Modules\Rrhh\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Empleado extends Model
{
    use SoftDeletes;
    protected $table = 'rrhh_empleados';
    protected $fillable = [
        'candidato_id', 'nombre', 'tipo_documento', 'numero_documento', 'email', 'telefono',
        'cargo', 'area', 'tipo_contrato', 'salario', 'fecha_ingreso', 'fecha_retiro',
        'estado', 'estado_induccion', 'notas',
    ];
    protected $casts = ['fecha_ingreso' => 'date', 'fecha_retiro' => 'date', 'salario' => 'decimal:2'];
    public function candidato(): BelongsTo { return $this->belongsTo(Candidato::class); }
    public function documentos(): HasMany { return $this->hasMany(DocumentoEmpleado::class); }
}
