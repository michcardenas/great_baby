<?php

namespace App\Modules\Rrhh\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoEmpleado extends Model
{
    protected $table = 'rrhh_documentos';
    protected $fillable = ['empleado_id', 'tipo', 'nombre', 'archivo_path', 'fecha_vencimiento'];
    protected $casts = ['fecha_vencimiento' => 'date'];
    public function empleado(): BelongsTo { return $this->belongsTo(Empleado::class); }
}
