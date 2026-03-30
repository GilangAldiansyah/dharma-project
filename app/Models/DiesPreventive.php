<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DiesPreventive extends Model
{
    protected $table = 'dies_preventive';

    protected $fillable = [
        'report_no',
        'dies_id',
        'process_id',
        'pic_name',
        'report_date',
        'stroke_at_maintenance',
        'repair_action',
        'photos',
        'pic_dies',
        'condition',
        'nok_closed_by',
        'nok_closed_at',
        'nok_notes',
        'status',
        'scheduled_date',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'photos'                => 'array',
        'report_date'           => 'date',
        'scheduled_date'        => 'date',
        'completed_at'          => 'datetime',
        'nok_closed_at'         => 'datetime',
        'stroke_at_maintenance' => 'integer',
    ];

    public function dies()
    {
        return $this->belongsTo(Dies::class, 'dies_id', 'id_sap');
    }

    public function process()
    {
        return $this->belongsTo(DiesProcess::class, 'process_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function nokClosedBy()
    {
        return $this->belongsTo(User::class, 'nok_closed_by');
    }

    public function spareparts()
    {
        return $this->hasMany(DiesHistorySparepart::class, 'maintenance_id')
            ->where('tipe', 'preventive');
    }
}
