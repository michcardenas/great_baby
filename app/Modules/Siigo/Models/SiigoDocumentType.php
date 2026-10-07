<?php

namespace App\Modules\Siigo\Models;

use Illuminate\Database\Eloquent\Model;

class SiigoDocumentType extends Model
{
    protected $table = 'siigo_document_types';

    protected $fillable = [
        'type', 'siigo_id', 'code', 'name', 'active',
        'cost_center', 'cost_center_mandatory', 'automatic_number',
        'consecutive', 'synced_at',
    ];

    protected $casts = [
        'siigo_id' => 'integer',
        'active' => 'boolean',
        'cost_center' => 'boolean',
        'cost_center_mandatory' => 'boolean',
        'automatic_number' => 'boolean',
        'consecutive' => 'integer',
        'synced_at' => 'datetime',
    ];
}
