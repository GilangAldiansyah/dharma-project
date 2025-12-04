<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NgReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_number',
        'part_id',
        'ng_images',
        'notes',
        'reported_by',
        'reported_at',
        'pica_document',
        'pica_uploaded_at',
        'pica_uploaded_by',
        'status',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
        'pica_uploaded_at' => 'datetime',
        'ng_images' => 'array',
    ];

    // Status constants
    const STATUS_OPEN = 'open';
    const STATUS_PICA_SUBMITTED = 'pica_submitted';
    const STATUS_CLOSED = 'closed';

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($report) {
            if (!$report->report_number) {
                $report->report_number = 'NG-' . date('Ymd') . '-' . str_pad(static::whereDate('created_at', today())->count() + 1, 4, '0', STR_PAD_LEFT);
            }
            if (!$report->reported_at) {
                $report->reported_at = now();
            }
            if (!$report->status) {
                $report->status = self::STATUS_OPEN;
            }
        });
    }

    public function getNgImageAttribute()
    {
        return $this->ng_images[0] ?? null;
    }

    public function getStatusBadgeAttribute()
    {
        return [
            'open' => ['label' => 'Open', 'color' => 'red'],
            'pica_submitted' => ['label' => 'PICA Submitted', 'color' => 'yellow'],
            'closed' => ['label' => 'Closed', 'color' => 'green'],
        ][$this->status] ?? ['label' => 'Unknown', 'color' => 'gray'];
    }
}
