<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DiesPreventive extends Model
{
    protected $table = 'dies_preventive';
    protected $fillable = [
        'report_no', 'dies_id', 'pic_name', 'report_date',
        'stroke_at_maintenance', 'repair_process', 'repair_action',
        'photos', 'status', 'scheduled_date', 'completed_at', 'created_by',
    ];
    protected $casts = [
        'photos'         => 'array',
        'report_date'    => 'date',
        'scheduled_date' => 'date',
        'completed_at'   => 'datetime',
        'stroke_at_stroke' => 'integer',
    ];
    public function dies()
    {
        return $this->belongsTo(Dies::class, 'dies_id', 'id_sap');
    }
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function spareparts()
    {
        return $this->hasMany(DiesHistorySparepart::class, 'maintenance_id')
            ->where('tipe', 'preventive');
    }
}
