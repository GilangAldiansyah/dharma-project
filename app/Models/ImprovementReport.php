<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImprovementReport extends Model
{
    protected $fillable = [
        'jig_id',
        'pic_id',
        'report_date',
        'description',
        'penyebab',
        'perbaikan',
        'photo',
        'photo_perbaikan',
        'status',
        'action',
        'closed_by',
        'closed_at',
    ];

    protected $casts = [
        'report_date' => 'datetime',
        'closed_at'   => 'datetime',
    ];

    public function jig()
    {
        return $this->belongsTo(Jig::class);
    }

    public function pic()
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function spareparts()
    {
        return $this->hasMany(ReportSparepart::class, 'report_id')
            ->where('report_type', 'improvement');
    }

    public function getSparepartsAttribute()
    {
        return $this->spareparts()->with('sparepart')->get();
    }

    public function getRepairDurationAttribute(): ?string
    {
        if (!$this->report_date || !$this->closed_at) return null;

        $diff    = $this->report_date->diff($this->closed_at);
        $parts   = [];

        if ($diff->days > 0)  $parts[] = $diff->days . 'h';
        if ($diff->h > 0)     $parts[] = $diff->h . 'j';
        if ($diff->i > 0)     $parts[] = $diff->i . 'm';
        if (empty($parts))    $parts[] = $diff->s . 'd';

        return implode(' ', $parts);
    }
}
