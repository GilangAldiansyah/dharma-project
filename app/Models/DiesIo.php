<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiesIo extends Model
{
    protected $table = 'dies_io';

    protected $fillable = [
        'nama',
        'cc',
        'io_number',
        'keterangan',
    ];

    public function spareparts()
    {
        return $this->hasMany(DiesHistorySparepart::class, 'io_id');
    }
}
