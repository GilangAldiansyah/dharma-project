<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PmSchedule extends Model
{
    protected $fillable = ['jig_id', 'interval', 'tahun', 'target_week', 'is_active'];

    protected $casts = [
        'is_active'   => 'boolean',
        'target_week' => 'integer',
    ];

    public function jig()     { return $this->belongsTo(Jig::class); }
    public function reports() { return $this->hasMany(PmReport::class); }
}
