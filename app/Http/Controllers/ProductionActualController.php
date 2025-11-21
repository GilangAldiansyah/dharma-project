<?php

namespace App\Http\Controllers;

use App\Models\Part;
use App\Models\ProductionPlan;
use App\Models\ProductionActual;
use App\Models\ProductionProblem;
use Illuminate\Http\Request;
use Inertia\Inertia;
class ProductionActualController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'production_plan_id' => 'required|exists:production_plans,id',
            'cycle_number' => 'required|integer|in:1,2',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'qty_produced' => 'required|integer|min:0',
        ]);

        $actual = ProductionActual::create($validated);

        return redirect()->back()->with('success', 'Production actual recorded');
    }

    public function update(Request $request, ProductionActual $actual)
    {
        $validated = $request->validate([
            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date',
            'qty_produced' => 'sometimes|integer|min:0',
        ]);

        $actual->update($validated);

        return redirect()->back()->with('success', 'Production actual updated');
    }
}
