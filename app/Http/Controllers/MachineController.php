<?php

namespace App\Http\Controllers;

use App\Models\Line;
use App\Models\Machine;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MachineController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Machine::where('is_archived', false)
            ->with(['lineModel' => function ($query) {
                $query->where('is_archived', false)
                      ->select('id', 'line_code', 'line_name', 'plant');
            }]);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('machine_name', 'like', "%{$request->search}%")
                  ->orWhere('barcode', 'like', "%{$request->search}%")
                  ->orWhere('plant', 'like', "%{$request->search}%")
                  ->orWhere('line', 'like', "%{$request->search}%")
                  ->orWhere('machine_type', 'like', "%{$request->search}%");
            });
        }

        if ($request->plant) {
            $query->where('plant', $request->plant);
        }

        if ($request->line_id) {
            $query->where('line_id', $request->line_id);
        }

        $machines = $query->orderBy('plant')
                         ->orderBy('line')
                         ->orderBy('machine_name')
                         ->paginate(20)
                         ->withQueryString();

        $machines->getCollection()->transform(function ($machine) {
            $machine->total_reports = $machine->maintenanceReports()->count();
            $machine->active_reports = $machine->activeReports()->count();

            if ($machine->lineModel) {
                $machine->lineModel->append(['average_mttr', 'average_mtbf']);
            }

            return $machine;
        });

        $stats = [
            'total_machines' => Machine::where('is_archived', false)->count(),
            'total_reports' => Machine::where('is_archived', false)
                ->withCount('maintenanceReports')
                ->get()
                ->sum('maintenance_reports_count'),
        ];

        $lines = Line::where('is_archived', false)
            ->orderBy('plant')
            ->orderBy('line_code')
            ->get()
            ->map(function ($line) {
                $line->append(['average_mttr', 'average_mtbf']);
                return $line;
            });

        $plants = Machine::where('is_archived', false)
            ->distinct()
            ->pluck('plant');

        return Inertia::render('Maintenance/Mesin', [
            'machines' => $machines,
            'stats' => $stats,
            'lines' => $lines,
            'plants' => $plants,
            'filters' => [
                'search' => $request->search,
                'plant' => $request->plant,
                'line_id' => $request->line_id,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'line_id' => 'required|exists:lines,id',
            'machine_name' => 'required|string|max:100',
            'barcode' => 'required|string|max:100|unique:machines,barcode',
            'machine_type' => 'nullable|string|max:50',
        ]);

        $line = Line::where('is_archived', false)->findOrFail($validated['line_id']);

        $validated['plant'] = $line->plant;
        $validated['line'] = $line->line_code;
        $validated['is_archived'] = false;

        Machine::create($validated);

        return back()->with('success', 'Mesin berhasil ditambahkan!');
    }

    public function update(Request $request, int $id)
    {
        $machine = Machine::where('is_archived', false)->findOrFail($id);

        $validated = $request->validate([
            'line_id' => 'required|exists:lines,id',
            'machine_name' => 'required|string|max:100',
            'barcode' => 'required|string|max:100|unique:machines,barcode,' . $id,
            'machine_type' => 'nullable|string|max:50',
        ]);

        $line = Line::where('is_archived', false)->findOrFail($validated['line_id']);

        $validated['plant'] = $line->plant;
        $validated['line'] = $line->line_code;

        $machine->update($validated);

        return back()->with('success', 'Mesin berhasil diupdate!');
    }

    public function destroy(int $id)
    {
        $machine = Machine::where('is_archived', false)->findOrFail($id);

        if ($machine->maintenanceReports()->count() > 0) {
            return back()->with('error', 'Tidak dapat menghapus mesin yang memiliki laporan maintenance!');
        }

        Machine::where('parent_machine_id', $id)->delete();

        $machine->delete();

        return back()->with('success', 'Mesin berhasil dihapus!');
    }

    public function metrics(int $id)
    {
        $machine = Machine::where('is_archived', false)
            ->with(['maintenanceReports' => function ($query) {
                $query->where('status', 'Selesai')
                      ->orderBy('completed_at', 'desc')
                      ->limit(10);
            }])
            ->findOrFail($id);

        return response()->json([
            'machine' => $machine,
            'mttr_formatted' => $machine->mttr_formatted,
            'total_downtime_minutes' => $machine->total_downtime ?? 0,
            'recent_reports' => $machine->maintenanceReports,
        ]);
    }
}
