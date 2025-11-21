<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class ProductionPlan extends Model
{
    protected $fillable = [
        'part_id',
        'order_number',
        'target_qty',
        'plan_date',
        'shift',
    ];

    protected $casts = [
        'plan_date' => 'date',
    ];

    protected $appends = ['total_produced', 'variance']; // Tambahkan ini

    public function part()
    {
        return $this->belongsTo(Part::class);
    }

    public function actuals(): HasMany
    {
        return $this->hasMany(ProductionActual::class);
    }

    public function problems(): HasMany
    {
        return $this->hasMany(ProductionProblem::class);
    }

    // Helper untuk total produksi
    public function getTotalProducedAttribute()
    {
        return $this->actuals->sum('qty_produced');
    }

    // Helper untuk variance
    public function getVarianceAttribute()
    {
        return $this->getTotalProducedAttribute() - $this->target_qty;
    }
}
