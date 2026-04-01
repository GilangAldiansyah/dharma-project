<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiesCorrectiveRepairSession extends Model
{
    protected $table = 'dies_corrective_repair_sessions';

    protected $fillable = [
        'corrective_id',
        'started_at',
        'ended_at',
        'duration_minutes',
        'created_by',
    ];

    protected $casts = [
        'started_at'       => 'datetime',
        'ended_at'         => 'datetime',
        'duration_minutes' => 'integer',
    ];

    public function corrective()
    {
        return $this->belongsTo(DiesCorrective::class, 'corrective_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
