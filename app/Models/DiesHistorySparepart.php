<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class DiesHistorySparepart extends Model
{
    protected $fillable = [
        'tipe',
        'maintenance_id',
        'sparepart_id',
        'dies_id',
        'quantity',
        'notes',
        'created_by',
    ];

    protected $table = 'dies_history_sparepart';

    protected $casts = [
        'quantity'       => 'integer',
        'maintenance_id' => 'integer',
    ];

    public function sparepart()
    {
        return $this->belongsTo(DiesSparepart::class, 'sparepart_id');
    }

    public function dies()
    {
        return $this->belongsTo(Dies::class, 'dies_id', 'id_sap');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function maintenance()
    {
        return match($this->tipe) {
            'preventive' => $this->belongsTo(DiesPreventive::class, 'maintenance_id'),
            'corrective' => $this->belongsTo(DiesCorrective::class, 'maintenance_id'),
            default      => null,
        };
    }
}
