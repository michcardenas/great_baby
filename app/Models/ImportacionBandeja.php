<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportacionBandeja extends Model
{
    protected $table = 'importaciones_bandeja';

    protected $guarded = ['id'];

    protected $casts = [
        'log' => 'array',
        'iniciada_at' => 'datetime',
        'terminada_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function porcentaje(): int
    {
        if ($this->total_filas <= 0) {
            return 0;
        }
        return (int) round(($this->procesadas / $this->total_filas) * 100);
    }
}
