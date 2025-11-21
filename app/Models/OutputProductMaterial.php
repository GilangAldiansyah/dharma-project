<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutputProductMaterial extends Model
{
    protected $fillable = [
        'output_product_id',
        'sap_no',
        'qty_per_unit'
    ];

    public function outputProduct()
    {
        return $this->belongsTo(OutputProduct::class);
    }
}
