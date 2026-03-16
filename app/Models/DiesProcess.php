<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiesProcess extends Model
{
    protected $fillable = [
        'dies_id',
        'process_name',
        'tonase',
    ];

    public function dies()
    {
        return $this->belongsTo(Dies::class, 'dies_id', 'id_sap');
    }
}
