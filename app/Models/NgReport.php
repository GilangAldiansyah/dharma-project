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
        'ng_image',
        'ng_images',
        'notes',
        'reported_by',
        'reported_at',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
        'ng_images' => 'array',
    ];

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($report) {
            if (empty($report->report_number)) {
                $report->report_number = 'NG-' . date('Ymd') . '-' . str_pad(static::whereDate('created_at', today())->count() + 1, 4, '0', STR_PAD_LEFT);
            }
        });
    }
}
