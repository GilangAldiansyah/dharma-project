<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class NgReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_number',
        'part_id',
        'ng_types',
        'ng_images',
        'notes',
        'reported_by',
        'reported_at',
        'temporary_actions',
        'temporary_action_notes',
        'ta_submitted_at',
        'ta_submitted_by',
        'ta_status',
        'ta_reviewed_at',
        'ta_reviewed_by',
        'ta_rejection_reason',
        'pica_document',
        'pica_uploaded_at',
        'pica_uploaded_by',
        'pica_status',
        'pica_reviewed_at',
        'pica_reviewed_by',
        'pica_rejection_reason',
        'status',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
        'ta_submitted_at' => 'datetime',
        'ta_reviewed_at' => 'datetime',
        'pica_uploaded_at' => 'datetime',
        'pica_reviewed_at' => 'datetime',
        'ng_images' => 'array',
        'ng_types' => 'array',
        'temporary_actions' => 'array',
    ];

    const STATUS_OPEN = 'open';
    const STATUS_CLOSED = 'closed';

    const TA_STATUS_SUBMITTED = 'submitted';
    const TA_STATUS_APPROVED = 'approved';
    const TA_STATUS_REJECTED = 'rejected';

    const PICA_STATUS_SUBMITTED = 'submitted';
    const PICA_STATUS_APPROVED = 'approved';
    const PICA_STATUS_REJECTED = 'rejected';
    const TYPE_FUNGSI = 'fungsi';
    const TYPE_DIMENSI = 'dimensi';
    const TYPE_TAMPILAN = 'tampilan';
    const TA_TYPE_REPAIR = 'repair';
    const TA_TYPE_TUKAR_GULING = 'tukar_guling';
    const TA_TYPE_SORTIR = 'sortir';

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    protected static function boot()
{
    parent::boot();

    static::creating(function ($report) {
        if (!$report->report_number) {
            $prefix = 'NG-' . date('Ymd') . '-';

            $lastReport = static::where('report_number', 'LIKE', $prefix . '%')
                ->orderByRaw('CAST(SUBSTRING(report_number, -4) AS UNSIGNED) DESC')
                ->first();

            if ($lastReport) {
                $lastNumber = (int) substr($lastReport->report_number, -4);
                $nextNumber = $lastNumber + 1;
            } else {
                $nextNumber = 1;
            }

            $report->report_number = $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        }
        if (!$report->reported_at) {
            $report->reported_at = now();
        }
        if (!$report->status) {
            $report->status = self::STATUS_OPEN;
        }
    });
}
    public function isTaDeadlineExceeded(): bool
    {
        if ($this->ta_submitted_at || $this->status === self::STATUS_CLOSED) {
            return false;
        }
        return Carbon::parse($this->reported_at)->addDay()->isPast();
    }

    public function isPicaDeadlineExceeded(): bool
    {
        if ($this->pica_uploaded_at || $this->status === self::STATUS_CLOSED) {
            return false;
        }
        return Carbon::parse($this->reported_at)->addDays(3)->isPast();
    }

    public function getTaDeadline(): Carbon
    {
        return Carbon::parse($this->reported_at)->addDay();
    }

    public function getPicaDeadline(): Carbon
    {
        return Carbon::parse($this->reported_at)->addDays(3);
    }

    public function canBeClosed(): bool
    {
        return $this->ta_status === self::TA_STATUS_APPROVED
            && $this->pica_status === self::PICA_STATUS_APPROVED
            && $this->status !== self::STATUS_CLOSED;
    }

    public function hasNgType($type): bool
    {
        return in_array($type, $this->ng_types ?? []);
    }

    public function hasTemporaryAction($type): bool
    {
        return in_array($type, $this->temporary_actions ?? []);
    }

    public function getTaStatusConfigAttribute(): array
    {
        $configs = [
            'submitted' => [
                'label' => 'Menunggu Review',
                'color' => 'yellow',
                'icon' => 'clock'
            ],
            'approved' => [
                'label' => 'Disetujui',
                'color' => 'green',
                'icon' => 'check-circle'
            ],
            'rejected' => [
                'label' => 'Ditolak',
                'color' => 'red',
                'icon' => 'x-circle'
            ],
        ];

        return $configs[$this->ta_status] ?? [
            'label' => 'Belum Submit',
            'color' => 'gray',
            'icon' => 'alert-circle'
        ];
    }

    public function getPicaStatusConfigAttribute(): array
    {
        $configs = [
            'submitted' => [
                'label' => 'Menunggu Review',
                'color' => 'yellow',
                'icon' => 'clock'
            ],
            'approved' => [
                'label' => 'Disetujui',
                'color' => 'green',
                'icon' => 'check-circle'
            ],
            'rejected' => [
                'label' => 'Ditolak',
                'color' => 'red',
                'icon' => 'x-circle'
            ],
        ];

        return $configs[$this->pica_status] ?? [
            'label' => 'Belum Upload',
            'color' => 'gray',
            'icon' => 'alert-circle'
        ];
    }

    // Scope: Filter by TA status
    public function scopeByTaStatus($query, $taStatus)
    {
        if ($taStatus && $taStatus !== 'all') {
            return $query->where('ta_status', $taStatus);
        }
        return $query;
    }

    // Scope: Filter by PICA status
    public function scopeByPicaStatus($query, $picaStatus)
    {
        if ($picaStatus && $picaStatus !== 'all') {
            return $query->where('pica_status', $picaStatus);
        }
        return $query;
    }

    // Scope: TA deadline exceeded
    public function scopeTaDeadlineExceeded($query)
    {
        return $query->whereNull('ta_submitted_at')
            ->where('status', '!=', self::STATUS_CLOSED)
            ->where('reported_at', '<', Carbon::now()->subDay());
    }

    // Scope: PICA deadline exceeded
    public function scopePicaDeadlineExceeded($query)
    {
        return $query->whereNull('pica_uploaded_at')
            ->where('status', '!=', self::STATUS_CLOSED)
            ->where('reported_at', '<', Carbon::now()->subDays(3));
    }
}
