<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DieShopReport extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'report_no',
        'activity_type',
        'pic_name',
        'report_date',
        'die_part_id',
        'repair_process',
        'problem_description',
        'cause',
        'repair_action',
        'photos',
        'status',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'photos' => 'array',
        'report_date' => 'date',
        'completed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($report) {
            if (empty($report->report_no)) {
                $report->report_no = self::generateReportNo();
            }
        });
    }

    public static function generateReportNo()
    {
        $date = now()->format('Ymd');
        $prefix = 'DIE-' . $date . '-';

        $lastReport = self::where('report_no', 'like', $prefix . '%')
            ->orderBy('report_no', 'desc')
            ->lockForUpdate()
            ->first();

        if ($lastReport) {
            $sequence = intval(substr($lastReport->report_no, -4)) + 1;
        } else {
            $sequence = 1;
        }

        return $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
    public function calculateDuration()
    {
        if ($this->status !== 'completed' || !$this->completed_at) {
            return null;
        }

        $diffInMinutes = $this->created_at->diffInMinutes($this->completed_at);
        $diffInHours = $this->created_at->diffInHours($this->completed_at);
        $diffInDays = $this->created_at->diffInDays($this->completed_at);

        // Less than 1 hour: show in minutes
        if ($diffInHours < 1) {
            return $diffInMinutes;
        }

        if ($diffInDays < 1) {
            return $diffInHours;
        }

        return $diffInDays;
    }

    public function getDurationFormatted()
    {
        if ($this->status !== 'completed' || !$this->completed_at) {
            return 'Belum selesai';
        }

        $diffInMinutes = $this->created_at->diffInMinutes($this->completed_at);
        $diffInHours = $this->created_at->diffInHours($this->completed_at);
        $diffInDays = $this->created_at->diffInDays($this->completed_at);

        if ($diffInHours < 1) {
            return $diffInMinutes . ' menit';
        }

        if ($diffInDays < 1) {
            $remainingMinutes = $diffInMinutes % 60;
            if ($remainingMinutes > 0) {
                return $diffInHours . ' jam ' . $remainingMinutes . ' menit';
            }
            return $diffInHours . ' jam';
        }

        $remainingHours = $diffInHours % 24;
        if ($remainingHours > 0) {
            return $diffInDays . ' hari ' . $remainingHours . ' jam';
        }

        return $diffInDays . ' hari';
    }

    public function getDurationUnit()
    {
        if ($this->status !== 'completed' || !$this->completed_at) {
            return null;
        }

        $diffInHours = $this->created_at->diffInHours($this->completed_at);
        $diffInDays = $this->created_at->diffInDays($this->completed_at);

        if ($diffInHours < 1) {
            return 'menit';
        } elseif ($diffInDays < 1) {
            return 'jam';
        } else {
            return 'hari';
        }
    }

    public function diePart()
    {
        return $this->belongsTo(DiePart::class);
    }

    public function spareparts()
    {
        return $this->hasMany(DieShopSparepart::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
