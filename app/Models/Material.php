<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    protected $fillable = [
        'sap_finish',
        'id_sap',
        'material_name',
        'part_no',
        'part_name',
    ];

    public function dailyStocks()
    {
        return $this->hasMany(DailyStock::class);
    }

    public function getStockForDate($date)
    {
        return $this->dailyStocks()
            ->where('stock_date', $date)
            ->first();
    }
}
