<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DiesCorrective extends Model
{
    protected $table = 'dies_corrective';
    protected $fillable = [
        'report_no',
        'dies_id',
        'process_id',
        'pic_name',
        'report_date',
        'stroke_at_maintenance',
        'repair_duration_minutes',
        'machine_duration_minutes',
        'problem_description',
        'cause',
        'repair_action',
        'action',
        'photos',
        'status',
        'closed_by',
        'closed_at',
        'off_machine_at',
        'created_by',
    ];
    protected $casts = [
        'photos'                   => 'array',
        'report_date'              => 'datetime',
        'closed_at'                => 'datetime',
        'off_machine_at'           => 'datetime',
        'stroke_at_maintenance'    => 'integer',
        'repair_duration_minutes'  => 'integer',
        'machine_duration_minutes' => 'integer',
    ];
    protected $appends = ['repair_duration', 'machine_duration'];

    public function getRepairDurationAttribute(): string|null
    {
        if (!$this->repair_duration_minutes) return null;
        $h = intdiv($this->repair_duration_minutes, 60);
        $m = $this->repair_duration_minutes % 60;
        if ($h > 0 && $m > 0) return "{$h}j {$m}m";
        if ($h > 0) return "{$h} jam";
        return "{$m} menit";
    }

    public function getMachineDurationAttribute(): string|null
    {
        if (!$this->machine_duration_minutes) return null;
        $h = intdiv($this->machine_duration_minutes, 60);
        $m = $this->machine_duration_minutes % 60;
        if ($h > 0 && $m > 0) return "{$h}j {$m}m";
        if ($h > 0) return "{$h} jam";
        return "{$m} menit";
    }

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
