<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProductionProblem extends Model
{
    protected $fillable = [
        'production_plan_id',
        'problem_description',
        'qty_loss',
        'problem_time',
    ];

    protected $casts = [
        'problem_time' => 'datetime',
    ];

    public function productionPlan()
    {
        return $this->belongsTo(ProductionPlan::class);
    }
}
