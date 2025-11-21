<?php

namespace App\Http\Controllers;

use App\Models\Part;
use App\Models\ProductionPlan;
use App\Models\ProductionActual;
use App\Models\ProductionProblem;
use Illuminate\Http\Request;
use Inertia\Inertia;
class ProductionPlanController extends Controller
{
    public function index(Request $request)
    {
        $query = ProductionPlan::with(['part', 'actuals']);

        if ($request->has('date') && $request->date) {
            $query->whereDate('plan_date', $request->date);
        }

        if ($request->has('shift') && $request->shift) {
            $query->where('shift', $request->shift);
        }

        if ($request->has('line_type') && $request->line_type) {
            $query->whereHas('part', function($q) use ($request) {
                $q->where('line_type', $request->line_type);
            });
        }

        $plans = $query->latest('plan_date')->get()->map(function($plan) {
            // Get actuals data
            $cycle1 = $plan->actuals->where('cycle_number', 1)->first();
            $cycle2 = $plan->actuals->where('cycle_number', 2)->first();

            // Convert Carbon to string properly
            $planDate = is_string($plan->plan_date) ? $plan->plan_date : $plan->plan_date->format('Y-m-d');

            return [
                'id' => $plan->id,
                'order_number' => $plan->order_number ?? '',
                'target_qty' => $plan->target_qty ?? 0,
                'plan_date' => $planDate,
                'shift' => $plan->shift,
                'part' => $plan->part,

                // Cycle 1
                'cycle1_start' => $cycle1 && $cycle1->start_time ? date('H:i', strtotime($cycle1->start_time)) : '',
                'cycle1_end' => $cycle1 && $cycle1->end_time ? date('H:i', strtotime($cycle1->end_time)) : '',
                'cycle1_qty' => $cycle1 ? $cycle1->qty_produced : null,

                // Cycle 2
                'cycle2_start' => $cycle2 && $cycle2->start_time ? date('H:i', strtotime($cycle2->start_time)) : '',
                'cycle2_end' => $cycle2 && $cycle2->end_time ? date('H:i', strtotime($cycle2->end_time)) : '',
                'cycle2_qty' => $cycle2 ? $cycle2->qty_produced : null,

                // Totals
                'total_actual' => $plan->actuals->sum('qty_produced'),
                'variance' => $plan->actuals->sum('qty_produced') - $plan->target_qty,
            ];
        });

        // Get all parts for dropdown
        $parts = Part::orderBy('material_number')->get();

        return Inertia::render('Production/Index', [
            'plans' => $plans,
            'parts' => $parts,
            'filters' => $request->only(['date', 'shift', 'line_type']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'part_id' => 'required|exists:parts,id',
            'order_number' => 'nullable|string',
            'target_qty' => 'nullable|integer|min:0',
            'plan_date' => 'required|date',
            'shift' => 'required|in:1,2,3',
        ]);

        // Set default target_qty if not provided
        if (!isset($validated['target_qty'])) {
            $validated['target_qty'] = 0;
        }

        $plan = ProductionPlan::create($validated);

        return redirect()->back()->with('success', 'Production plan created');
    }

    public function update(Request $request, ProductionPlan $productionPlan)
{
    // Update basic info - handle target_qty properly
    $updated = false;

    if ($request->has('order_number')) {
        $productionPlan->order_number = $request->order_number;
        $updated = true;
    }

    if ($request->has('target_qty')) {
        // Accept 0 as valid value, only reject null/empty
        $productionPlan->target_qty = $request->target_qty !== null ? $request->target_qty : 0;
        $updated = true;
    }

    if ($updated) {
        $productionPlan->save();
    }

    $planDateStr = is_string($productionPlan->plan_date)
        ? $productionPlan->plan_date
        : $productionPlan->plan_date->format('Y-m-d');

    // Update or create Cycle 1 - FIXED: Check if ANY cycle1 field exists
    if ($request->has('cycle1_start') || $request->has('cycle1_end') || $request->has('cycle1_qty')) {
        ProductionActual::updateOrCreate(
            [
                'production_plan_id' => $productionPlan->id,
                'cycle_number' => 1,
            ],
            [
                'start_time' => $request->cycle1_start
                    ? $planDateStr . ' ' . $request->cycle1_start . ':00'
                    : null,
                'end_time' => $request->cycle1_end
                    ? $planDateStr . ' ' . $request->cycle1_end . ':00'
                    : null,
                'qty_produced' => $request->cycle1_qty ?? 0,
            ]
        );
    }

    // Update or create Cycle 2 - FIXED: Check if ANY cycle2 field exists
    if ($request->has('cycle2_start') || $request->has('cycle2_end') || $request->has('cycle2_qty')) {
        ProductionActual::updateOrCreate(
            [
                'production_plan_id' => $productionPlan->id,
                'cycle_number' => 2,
            ],
            [
                'start_time' => $request->cycle2_start
                    ? $planDateStr . ' ' . $request->cycle2_start . ':00'
                    : null,
                'end_time' => $request->cycle2_end
                    ? $planDateStr . ' ' . $request->cycle2_end . ':00'
                    : null,
                'qty_produced' => $request->cycle2_qty ?? 0,
            ]
        );
    }

    return redirect()->back();
}

    public function show(ProductionPlan $plan)
    {
        $plan->load(['part', 'actuals', 'problems']);

        return Inertia::render('Production/Show', [
            'plan' => $plan,
        ]);
    }

    public function destroy(ProductionPlan $productionPlan)
    {
        $productionPlan->delete();
        return redirect()->back()->with('success', 'Deleted');
    }
}
