<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SparepartHistory extends Model
{
    protected $fillable = [
        'sparepart_id', 'tipe', 'report_type', 'report_id',
        'qty', 'stok_before', 'stok_after', 'notes', 'user_id',
    ];

    protected $casts = [
        'qty'          => 'float',
        'stok_before'  => 'float',
        'stok_after'   => 'float',
    ];

    public function sparepart() { return $this->belongsTo(Sparepart::class); }
    public function user()      { return $this->belongsTo(User::class); }
}
