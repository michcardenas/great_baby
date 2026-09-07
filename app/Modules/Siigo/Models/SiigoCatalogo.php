<?php

namespace App\Modules\Siigo\Models;

use Illuminate\Database\Eloquent\Model;

class SiigoCatalogo extends Model
{
    protected $table = 'siigo_catalogos';

    protected $fillable = ['tipo', 'codigo', 'nombre', 'payload'];

    protected $casts = ['payload' => 'array'];

    public function scopeTipo($q, string $tipo) { return $q->where('tipo', $tipo); }
}
