<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyStock extends Model
{
    protected $fillable = [
        'bl_type',
        'sap_finish',
        'id_sap',
        'material_name',
        'part_no',
        'part_name',
        'type',
        'qty_unit',
        'qty_day',
        'stock_date',
        'stock_awal',
        'produksi_shift1',
        'produksi_shift2',
        'produksi_shift3',
        'out_shift1',
        'out_shift2',
        'out_shift3',
        'ng_shift1',
        'ng_shift2',
    ];

    protected $casts = [
        'stock_date' => 'date',
        'bl_type' => 'string',
    ];

    public function scopeBL1($query)
    {
        return $query->where('bl_type', 'BL1');
    }

    public function scopeBL2($query)
    {
        return $query->where('bl_type', 'BL2');
    }
    public function scopeForDate($query, $date)
    {
        return $query->where('stock_date', $date);
    }
    public function getTotalProduksiAttribute()
    {
        return $this->produksi_shift1 + $this->produksi_shift2 + $this->produksi_shift3;
    }

    public function getTotalOutAttribute()
    {
        return $this->out_shift1 + $this->out_shift2 + $this->out_shift3;
    }

    public function getSohAttribute()
    {
        return $this->stock_awal + $this->total_produksi - $this->total_out;
    }
}
