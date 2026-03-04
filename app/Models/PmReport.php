<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PmReport extends Model
{
    protected $fillable = [
        'pm_schedule_id', 'pic_id',
        'planned_week_start', 'planned_week_end',
        'actual_date', 'photo', 'photo_sparepart',
        'condition', 'notes', 'status',
        'nok_closed_by', 'nok_closed_at',
    ];

    protected $casts = [
        'planned_week_start' => 'date',
        'planned_week_end'   => 'date',
        'actual_date'        => 'date',
        'nok_closed_at'      => 'datetime',
    ];

    public function pmSchedule()  { return $this->belongsTo(PmSchedule::class); }
    public function pic()         { return $this->belongsTo(User::class, 'pic_id'); }
    public function nokClosedBy() { return $this->belongsTo(User::class, 'nok_closed_by'); }
    public function spareparts()  { return $this->hasMany(ReportSparepart::class, 'report_id')->where('report_type', 'pm'); }
}
