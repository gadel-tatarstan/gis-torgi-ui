<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GpzuData extends Model
{
    protected $fillable = [
        'lot_id',
        'file_id',
        'file_name',
        'permitted_uses',
        'utility_tables',
        'gas_page',
        'drawing_page',
    ];

    protected $casts = [
        'permitted_uses' => 'array',
        'utility_tables' => 'array',
    ];
}
