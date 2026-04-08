<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DiesProcess extends Model
{
    protected $fillable = [
        'dies_id',
        'no_proses',
        'process_name',
        'tonase',
        'std_stroke',
        'current_stroke',
        'last_mtc_date',
    ];

    protected $casts = [
        'no_proses'      => 'integer',
        'std_stroke'     => 'integer',
        'current_stroke' => 'integer',
        'last_mtc_date'  => 'date',
        'tonase'         => 'float',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('ordered', fn($q) => $q->orderBy('no_proses'));
    }

    public function dies()
    {
        return $this->belongsTo(Dies::class, 'dies_id', 'id_sap');
    }

    public function getPctAttribute(): float
    {
        if (!$this->std_stroke) return 0;
        return round($this->current_stroke / $this->std_stroke * 100, 1);
    }

    public function getRemainingAttribute(): int
    {
        return max(0, $this->std_stroke - $this->current_stroke);
    }
}
