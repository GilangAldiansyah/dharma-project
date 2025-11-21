<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProductionActual extends Model
{
    protected $fillable = [
        'production_plan_id',
        'cycle_number',
        'start_time',
        'end_time',
        'qty_produced',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function productionPlan()
    {
        return $this->belongsTo(ProductionPlan::class);
    }
}
