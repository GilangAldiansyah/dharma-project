<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmReport extends Model
{
    protected $fillable = [
        'jig_id', 'pic_id', 'report_date', 'description',
        'penyebab', 'perbaikan',
        'photo', 'photo_perbaikan',
        'status', 'action', 'closed_by', 'closed_at',
    ];

    protected $casts = [
        'report_date' => 'date',
        'closed_at'   => 'datetime',
    ];

    public function jig()        { return $this->belongsTo(Jig::class); }
    public function pic()        { return $this->belongsTo(User::class, 'pic_id'); }
    public function closedBy()   { return $this->belongsTo(User::class, 'closed_by'); }
    public function spareparts() { return $this->hasMany(ReportSparepart::class, 'report_id')->where('report_type', 'cm'); }
}
