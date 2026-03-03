<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Jig extends Model
{
    protected $fillable = ['type', 'name', 'kategori', 'line', 'pic_id'];

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    public function pmSchedules(): HasMany
    {
        return $this->hasMany(PmSchedule::class);
    }

    public function cmReports(): HasMany
    {
        return $this->hasMany(CmReport::class);
    }
}
