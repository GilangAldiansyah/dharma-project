<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DieShopSparepart extends Model
{
    use HasFactory;

    protected $fillable = [
        'die_shop_report_id',
        'sparepart_name',
        'sparepart_code',
        'quantity',
        'notes',
    ];

    public function dieShopReport()
    {
        return $this->belongsTo(DieShopReport::class);
    }
}
