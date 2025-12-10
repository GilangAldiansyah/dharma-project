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
        'pic_name',
        'report_date',
        'shift',
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

    /**
     * Calculate duration in total seconds
     */
    public function calculateDurationSeconds()
    {
        if ($this->status !== 'completed' || !$this->completed_at) {
            return null;
        }

        return $this->created_at->diffInSeconds($this->completed_at);
    }

    /**
     * Get duration formatted as HH:MM:SS
     */
    public function getDurationFormatted()
    {
        if ($this->status !== 'completed' || !$this->completed_at) {
            return null;
        }

        $totalSeconds = $this->created_at->diffInSeconds($this->completed_at);

        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    /**
     * Get human-readable duration
     */
    public function getDurationHuman()
    {
        if ($this->status !== 'completed' || !$this->completed_at) {
            return 'Belum selesai';
        }

        $totalSeconds = $this->created_at->diffInSeconds($this->completed_at);

        $days = floor($totalSeconds / 86400);
        $hours = floor(($totalSeconds % 86400) / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);

        $parts = [];

        if ($days > 0) {
            $parts[] = $days . ' hari';
        }
        if ($hours > 0) {
            $parts[] = $hours . ' jam';
        }
        if ($minutes > 0 || empty($parts)) {
            $parts[] = $minutes . ' menit';
        }

        return implode(' ', $parts);
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
