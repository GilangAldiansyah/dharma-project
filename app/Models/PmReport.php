<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmReport extends Model
{
    protected $fillable = [
        'pm_schedule_id',
        'pic_id',
        'planned_week_start',
        'planned_week_end',
        'actual_date',
        'photo',
        'condition',
        'notes',
        'status',
    ];

    protected $casts = [
        'planned_week_start' => 'date',
        'planned_week_end'   => 'date',
        'actual_date'        => 'date',
    ];

    public function pmSchedule(): BelongsTo
    {
        return $this->belongsTo(PmSchedule::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    public function spareparts(): HasMany
    {
        return $this->hasMany(ReportSparepart::class, 'report_id')
                    ->where('report_type', 'pm');
    }

    public function isLate(): bool
    {
        if ($this->actual_date) {
            return $this->actual_date->isAfter($this->planned_week_end);
        }
        return Carbon::today()->isAfter($this->planned_week_end);
    }
}
