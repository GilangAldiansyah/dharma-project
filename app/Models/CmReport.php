<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmReport extends Model
{
    protected $fillable = [
        'jig_id',
        'pic_id',
        'report_date',
        'description',
        'photo',
        'status',
        'action',
        'closed_by',
        'closed_at',
    ];

    protected $casts = [
        'report_date' => 'date',
        'closed_at'   => 'datetime',
    ];

    public function jig(): BelongsTo
    {
        return $this->belongsTo(Jig::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function spareparts(): HasMany
    {
        return $this->hasMany(ReportSparepart::class, 'report_id')
                    ->where('report_type', 'cm');
    }
}
