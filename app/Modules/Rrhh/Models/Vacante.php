<?php

namespace App\Modules\Rrhh\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vacante extends Model
{
    use SoftDeletes;
    protected $table = 'rrhh_vacantes';
    protected $fillable = [
        'titulo', 'area', 'descripcion', 'requisitos', 'salario_min', 'salario_max',
        'modalidad', 'tipo_contrato', 'estado', 'fecha_apertura', 'fecha_cierre', 'creado_por',
    ];
    protected $casts = ['fecha_apertura' => 'date', 'fecha_cierre' => 'date'];
    public function candidatos(): HasMany { return $this->hasMany(Candidato::class); }
}
