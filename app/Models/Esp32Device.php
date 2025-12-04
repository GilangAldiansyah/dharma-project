<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Esp32Device extends Model
{
    protected $fillable = [
        'device_id',
        'counter_a',
        'counter_b',
        'max_count',
        'relay_status',
        'error_b',
        'last_update',
    ];

    protected $casts = [
        'relay_status' => 'boolean',
        'error_b' => 'boolean',
        'last_update' => 'datetime',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(Esp32Log::class, 'device_id', 'device_id');
    }
}
