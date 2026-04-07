<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Esp32Part extends Model
{
    protected $fillable = [
        'device_id',
        'part_id',
        'part_name',
        'max_count',
        'max_stroke',
        'cycle_time',
        'production_started_at',
    ];

    protected $casts = [
        'max_count'  => 'integer',
        'max_stroke' => 'integer',
        'cycle_time' => 'integer',
        'production_started_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Esp32Device::class, 'device_id', 'device_id');
    }
}
