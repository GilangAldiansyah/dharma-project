<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutputProduct extends Model
{
    protected $fillable = [
        'type',
        'penanggung_jawab',
        'sap_no',
        'product_unit',
        'qty_day',
        'stock_date',
        'out_shift1',
        'out_shift2',
        'out_shift3',
        'ng_shift1',
        'ng_shift2',
        'ng_shift3',
        'total',
    ];

    protected $casts = [
        'stock_date' => 'date',
    ];

    // Auto calculate total
    public function getTotalAttribute()
    {
        return $this->out_shift1 + $this->out_shift2 + $this->out_shift3;
    }

    public function materials()
{
    return $this->hasMany(OutputProductMaterial::class, 'output_product_id');
}
}
