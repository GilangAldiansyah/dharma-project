<?php

namespace App\Http\Controllers;

use App\Models\Part;
use App\Models\ProductionPlan;
use App\Models\ProductionActual;
use App\Models\ProductionProblem;
use Illuminate\Http\Request;
use Inertia\Inertia;
class ProductionProblemController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'production_plan_id' => 'required|exists:production_plans,id',
            'problem_description' => 'required|string',
            'qty_loss' => 'required|integer|min:0',
            'problem_time' => 'required|date',
        ]);

        $problem = ProductionProblem::create($validated);

        return redirect()->back()->with('success', 'Problem recorded');
    }
}
