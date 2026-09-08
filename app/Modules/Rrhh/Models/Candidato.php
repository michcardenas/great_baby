<?php

namespace App\Modules\Rrhh\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Candidato extends Model
{
    use SoftDeletes;
    protected $table = 'rrhh_candidatos';
    protected $fillable = ['vacante_id', 'nombre', 'email', 'telefono', 'cv_path', 'etapa', 'calificacion', 'notas'];
    public function vacante(): BelongsTo { return $this->belongsTo(Vacante::class); }
}
