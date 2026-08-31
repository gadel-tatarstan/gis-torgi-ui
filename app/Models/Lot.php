<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lot extends Model
{
    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'notice_number',
        'lot_number',
        'bidd_form_code',
        'bidd_form_name',
        'lot_name',
        'lot_description',
        'price_min',
        'price_min_exact',
        'price_step',
        'deposit',
        'bidd_end_time',
        'auction_start_date',
        'bidd_start_time',
        'lot_images',
        'permitted_use',
        'cadastral_number',
        'area',
        'area_unit',
        'etp_code',
        'etp_url',
        'estate_address',
        'custom_address',
        'create_date',
        'notice_first_version_publication_date',
        'lot_vat_name',
        'lot_status',
        'version_id',
        'lat',
        'lon',
        'market_price',
        'is_viewed',
        'is_not_interested',
        'on_board',
        'yg_task_id',
        'comment',
        'lot_attachments',
        'notice_attachments',
        'characteristics_raw',
        'attributes_raw',
    ];

    protected $casts = [
        'price_min' => 'decimal:2',
        'price_step' => 'decimal:2',
        'deposit' => 'decimal:2',
        'area' => 'decimal:2',
        'lat' => 'decimal:7',
        'lon' => 'decimal:7',
        'lot_images' => 'array',
        'lot_attachments' => 'array',
        'notice_attachments' => 'array',
        'characteristics_raw' => 'array',
        'attributes_raw' => 'array',
        'bidd_end_time' => 'datetime',
        'auction_start_date' => 'datetime',
        'bidd_start_time' => 'datetime',
        'create_date' => 'datetime',
        'notice_first_version_publication_date' => 'datetime',
        'is_viewed' => 'boolean',
        'is_not_interested' => 'boolean',
        'on_board' => 'boolean',
    ];

    public function etp(): BelongsTo
    {
        return $this->belongsTo(Etp::class, 'etp_code', 'code');
    }

    public function scopeNotOlderThanDays($query, int $days = 20): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
