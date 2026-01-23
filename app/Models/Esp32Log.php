<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Esp32Log extends Model
{
    protected $fillable = [
        'device_id',
        'counter_a',
        'counter_b',
        'reject',
        'cycle_time',
        'max_count',
        'max_stroke',
        'loading_time',
        'production_started_at',
        'relay_status',
        'error_b',
        'logged_at',
    ];

    protected $casts = [
        'relay_status' => 'boolean',
        'error_b' => 'boolean',
        'logged_at' => 'datetime',
        'production_started_at' => 'datetime',
    ];

    protected $appends = ['has_counter_b'];

    public function getHasCounterBAttribute(): bool
    {
        return $this->counter_b > 0 || $this->max_stroke > 0;
    }
}
