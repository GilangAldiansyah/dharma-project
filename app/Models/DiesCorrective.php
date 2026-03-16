<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DiesCorrective extends Model
{
    protected $table = 'dies_corrective';
    protected $fillable = [
        'report_no',
        'dies_id',
        'pic_name',
        'report_date',
        'stroke_at_maintenance',
        'problem_description',
        'cause',
        'repair_action',
        'action',
        'photos',
        'status',
        'closed_by',
        'closed_at',
        'created_by',
    ];
    protected $casts = [
        'photos'     => 'array',
        'report_date'=> 'date',
        'closed_at'  => 'datetime',
        'stroke_at_maintenance' => 'integer',
    ];
    public function dies()
    {
        return $this->belongsTo(Dies::class, 'dies_id', 'id_sap');
    }
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
    public function spareparts()
    {
        return $this->hasMany(DiesHistorySparepart::class, 'maintenance_id')
            ->where('tipe', 'corrective');
    }
}
