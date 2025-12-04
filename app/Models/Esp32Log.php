<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Esp32Log extends Model
{
    protected $fillable = [
        'device_id',
        'counter_a',
        'counter_b',
        'max_count',
        'relay_status',
        'error_b',
        'logged_at',
    ];

    protected $casts = [
        'relay_status' => 'boolean',
        'error_b' => 'boolean',
        'logged_at' => 'datetime',
    ];
}
