<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiesSparepart extends Model
{
    protected $fillable = [
        'sparepart_code',
        'sparepart_name',
        'unit',
        'stok',
        'stok_minimum'
    ];

    protected $casts = [
        'stok' => 'integer',
    ];

    public function histories()
    {
        return $this->hasMany(DiesHistorySparepart::class, 'sparepart_id');
    }
}
