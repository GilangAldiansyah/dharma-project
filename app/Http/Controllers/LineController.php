<?php

namespace App\Http\Controllers;

use App\Models\Line;
use App\Models\Machine;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LineController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Line::withCount(['machines', 'maintenanceReports', 'operations']);

        // Filter by search
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('line_name', 'like', "%{$request->search}%")
                ->orWhere('line_code', 'like', "%{$request->search}%")
                ->orWhere('plant', 'like', "%{$request->search}%");
            });
        }

        // Filter by plant
        if ($request->plant) {
            $query->where('plant', $request->plant);
        }

        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        $lines = $query->orderBy('plant')
                    ->orderBy('line_code')
                    ->paginate(20)
                    ->withQueryString();

        // Add calculated fields - MTTR dan MTBF sudah otomatis dimuat dari $appends
        $lines->getCollection()->transform(function ($line) {
            $line->average_mttr = $line->average_mttr; // Akan trigger accessor
            $line->average_mtbf = $line->average_mtbf; // Akan trigger accessor
            $line->total_line_stops = $line->total_line_stops;
            $line->active_reports_count = $line->active_reports_count;
            return $line;
        });

        // Statistics
        $stats = [
            'total_lines' => Line::count(),
            'operating' => Line::where('status', 'operating')->count(),
            'stopped' => Line::where('status', 'stopped')->count(),
            'maintenance' => Line::where('status', 'maintenance')->count(),
        ];

        // Get unique plants
        $plants = Line::distinct()->pluck('plant');

        return Inertia::render('Maintenance/Lines', [
            'lines' => $lines,
            'stats' => $stats,
            'plants' => $plants,
            'filters' => [
                'search' => $request->search,
                'plant' => $request->plant,
                'status' => $request->status,
            ],
        ]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'line_name' => 'required|string|max:255',
            'plant' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $validated['line_code'] = Line::generateLineCode($validated['plant']);
        $validated['qr_code']   = $validated['line_code'];
        $validated['status'] = 'stopped';

        Line::create($validated);

        return back()->with('success', 'Line berhasil ditambahkan!');
    }

    /**
     * Update a line
     */
    public function update(Request $request, int $id)
    {
        $line = Line::findOrFail($id);

        $validated = $request->validate([
            'line_name' => 'required|string|max:255',
            'plant' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $line->update($validated);

        return back()->with('success', 'Line berhasil diupdate!');
    }

    /**
     * Delete a line
     */
    public function destroy(int $id)
    {
        $line = Line::findOrFail($id);

        // Check if line has machines
        if ($line->machines()->count() > 0) {
            return back()->with('error', 'Tidak dapat menghapus line yang memiliki mesin!');
        }

        $line->delete();

        return back()->with('success', 'Line berhasil dihapus!');
    }

    /**
     * Scan QR code for line
     */
    public function scanQrCode(Request $request)
    {
        $validated = $request->validate([
            'qr_code' => 'required|string',
        ]);

        $line = Line::where('qr_code', $validated['qr_code'])->first();

        if (!$line) {
            return response()->json([
                'success' => false,
                'message' => 'Line dengan QR Code ini tidak ditemukan',
            ], 404);
        }

        // Load machines in this line
        $machines = $line->machines()->get();

        return response()->json([
            'success' => true,
            'data' => [
                'line' => [
                    'id' => $line->id,
                    'line_code' => $line->line_code,
                    'line_name' => $line->line_name,
                    'plant' => $line->plant,
                    'status' => $line->status,
                    'qr_code' => $line->qr_code,
                ],
                'machines' => $machines->map(function ($machine) {
                    return [
                        'id' => $machine->id,
                        'machine_name' => $machine->machine_name,
                        'machine_type' => $machine->machine_type,
                        'barcode' => $machine->barcode,
                    ];
                }),
            ],
        ]);
    }
}
