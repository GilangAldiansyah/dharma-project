<?php

namespace App\Http\Controllers;

use App\Models\NgReport;
use App\Models\Part;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NgReportController extends Controller
{
    public function index(Request $request)
    {
        $query = NgReport::with(['part.supplier']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('report_number', 'like', "%{$search}%")
                  ->orWhereHas('part', function ($q) use ($search) {
                      $q->where('part_name', 'like', "%{$search}%");
                  });
        }

        $reports = $query->latest()->paginate(10);
        $parts = Part::with('supplier')->select('id', 'part_code', 'part_name', 'supplier_id')->get();

        return Inertia::render('NgReports/Index', [
            'reports' => $reports,
            'parts' => $parts,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'part_id' => 'required|exists:parts,id',
            'ng_image' => 'required|image|max:2048',
            'ng_images.*' => 'nullable|image|max:2048',
            'notes' => 'nullable',
            'reported_by' => 'required',
        ]);

        if ($request->hasFile('ng_image')) {
            $validated['ng_image'] = $request->file('ng_image')->store('ng-reports', 'public');
        }

        if ($request->hasFile('ng_images')) {
            $images = [];
            foreach ($request->file('ng_images') as $image) {
                $images[] = $image->store('ng-reports', 'public');
            }
            $validated['ng_images'] = $images;
        }

        $validated['reported_at'] = now();

        NgReport::create($validated);

        return redirect()->back();
    }

    public function destroy(NgReport $ngReport)
    {
        if ($ngReport->ng_image) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($ngReport->ng_image);
        }
        $ngReport->delete();
        return redirect()->back();
    }
}
