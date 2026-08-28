<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Etp extends Model
{
    protected $fillable = [
        'code',
        'name',
        'published',
        'site',
        'short_name',
        'icon_url',
        'icon_file_name',
        'key_etp',
        'yg_sticker_id',
        'order',
    ];

    protected $casts = [
        'published' => 'boolean',
    ];
}
