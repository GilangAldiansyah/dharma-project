<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Dies extends Model
{
    protected $primaryKey = 'id_sap';
    protected $keyType    = 'string';
    public $incrementing  = false;

    protected $fillable = [
        'id_sap',
        'no_part',
        'nama_dies',
        'line',
        'kategori',
        'status',
        'is_common',
        'cav',
        'forecast_per_day',
        'total_stroke',
        'bstb_updated_at',
    ];

    protected $casts = [
        'is_common'       => 'boolean',
        'cav'             => 'integer',
        'forecast_per_day'=> 'integer',
        'total_stroke'    => 'integer',
        'bstb_updated_at' => 'datetime',
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
