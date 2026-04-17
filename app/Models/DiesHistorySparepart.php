<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiesHistorySparepart extends Model
{
    protected $table = 'dies_history_sparepart';

    protected $fillable = [
        'tipe',
        'maintenance_id',
        'sparepart_id',
        'dies_id',
        'io_id',
        'quantity',
        'notes',
        'created_by',
        'sap_confirmed_at',
        'sap_confirmed_by',
    ];

    protected $casts = [
        'sap_confirmed_at' => 'datetime',
    ];


    public function sparepart()
    {
        return $this->belongsTo(DiesSparepart::class, 'sparepart_id');
    }

    public function dies()
    {
        return $this->belongsTo(Dies::class, 'dies_id', 'id_sap');
    }

    public function io()
    {
        return $this->belongsTo(DiesIo::class, 'io_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
    public function sapConfirmedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'sap_confirmed_by');
    }
}
