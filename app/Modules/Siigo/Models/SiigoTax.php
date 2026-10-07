<?php

namespace App\Modules\Siigo\Models;

use Illuminate\Database\Eloquent\Model;

class SiigoTax extends Model
{
    protected $table = 'siigo_taxes';

    protected $fillable = ['siigo_id', 'type', 'name', 'percentage', 'active', 'synced_at'];

    protected $casts = [
        'siigo_id' => 'integer',
        'percentage' => 'float',
        'active' => 'boolean',
        'synced_at' => 'datetime',
    ];
}
