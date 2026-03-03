<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportSparepart extends Model
{
    protected $fillable = ['report_type', 'report_id', 'sparepart_id', 'qty'];

    protected $casts = [
        'qty' => 'decimal:2',
    ];

    public function sparepart(): BelongsTo
    {
        return $this->belongsTo(Sparepart::class);
    }

    public function pmReport(): BelongsTo
    {
        return $this->belongsTo(PmReport::class, 'report_id');
    }

    public function cmReport(): BelongsTo
    {
        return $this->belongsTo(CmReport::class, 'report_id');
    }
}
