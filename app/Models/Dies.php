<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dies extends Model
{
    protected $primaryKey = 'id_sap';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id_sap',
        'no_part',
        'nama_dies',
        'line',
        'kategori',
        'status',
        'is_common',
        'std_stroke',
        'freq_maintenance',
        'freq_maintenance_day',
        'cav',
        'forecast_per_day',
        'current_stroke',
        'total_stroke',
        'last_mtc_date',
        'bstb_updated_at',
    ];

    protected $casts = [
        'is_common'            => 'boolean',
        'std_stroke'           => 'integer',
        'freq_maintenance_day' => 'integer',
        'cav'                  => 'integer',
        'forecast_per_day'     => 'integer',
        'current_stroke'       => 'integer',
        'total_stroke'         => 'integer',
        'last_mtc_date'        => 'date',
        'bstb_updated_at'      => 'datetime',
    ];

    public function processes()
    {
        return $this->hasMany(DiesProcess::class, 'dies_id', 'id_sap');
    }

    public function preventives()
    {
        return $this->hasMany(DiesPreventive::class, 'dies_id', 'id_sap');
    }

    public function correctives()
    {
        return $this->hasMany(DiesCorrective::class, 'dies_id', 'id_sap');
    }

    public function historySpareparts()
    {
        return $this->hasMany(DiesHistorySparepart::class, 'dies_id', 'id_sap');
    }
}
