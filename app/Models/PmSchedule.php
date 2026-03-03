<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmSchedule extends Model
{
    protected $fillable = ['jig_id', 'interval', 'tahun', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'tahun'     => 'integer',
    ];

    public function jig(): BelongsTo
    {
        return $this->belongsTo(Jig::class);
    }

    public function pmReports(): HasMany
    {
        return $this->hasMany(PmReport::class);
    }
}
