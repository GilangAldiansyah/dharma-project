<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmReport extends Model
{
    protected $fillable = [
        'jig_id', 'pic_id', 'report_date', 'description',
        'penyebab', 'perbaikan',
        'photo', 'photo_perbaikan',
        'status', 'action', 'closed_by', 'closed_at',
    ];

    protected $casts = [
        'report_date' => 'datetime',
        'closed_at'   => 'datetime',
    ];

    public function jig()        { return $this->belongsTo(Jig::class); }
    public function pic()        { return $this->belongsTo(User::class, 'pic_id'); }
    public function closedBy()   { return $this->belongsTo(User::class, 'closed_by'); }
    public function spareparts() { return $this->hasMany(ReportSparepart::class, 'report_id')->where('report_type', 'cm'); }

    public function getRepairDurationAttribute(): ?string
    {
        if (!$this->closed_at || !$this->report_date) return null;

        $minutes = (int) $this->report_date->diffInMinutes($this->closed_at);
        $hours   = intdiv($minutes, 60);
        $mins    = $minutes % 60;

        if ($hours > 0 && $mins > 0) return "{$hours}j {$mins}m";
        if ($hours > 0)              return "{$hours}j";
        return "{$mins}m";
    }
}
