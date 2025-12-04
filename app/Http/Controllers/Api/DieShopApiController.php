<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiePart;
use App\Models\DieShopReport;
use App\Models\DieShopSparepart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class DieShopApiController extends Controller
{
    public function getDieParts()
    {
        $dieParts = DiePart::where('status', 'active')
            ->latest()
            ->get();

        return response()->json(['data' => $dieParts]);
    }

    public function createDiePart(Request $request)
    {
        $validated = $request->validate([
            'part_no' => 'required|string|unique:die_parts,part_no',
            'part_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
        ]);

        $validated['status'] = 'active';

        $diePart = DiePart::create($validated);

        return response()->json([
            'message' => 'Die Part berhasil ditambahkan',
            'data' => $diePart
        ], 201);
    }

    public function getDieShopReports(Request $request)
    {
        $query = DieShopReport::with(['diePart', 'spareparts']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('activity_type')) {
            $query->where('activity_type', $request->activity_type);
        }

        if ($request->has('date_from')) {
            $query->whereDate('report_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('report_date', '<=', $request->date_to);
        }

        $reports = $query->latest('report_date')->get();

        $reports->transform(function ($report) {
            $report->duration_value = $report->calculateDuration();
            $report->duration_unit = $report->getDurationUnit();
            $report->duration_formatted = $report->getDurationFormatted();
            return $report;
        });

        return response()->json(['data' => $reports]);
    }

    public function createDieShopReport(Request $request)
    {
        $validated = $request->validate([
            'activity_type' => 'required|in:corrective,preventive',
            'pic_name' => 'required|string|max:255',
            'report_date' => 'required|date',
            'die_part_id' => 'required|exists:die_parts,id',
            'repair_process' => 'nullable|string',
            'problem_description' => 'nullable|string',
            'cause' => 'nullable|string',
            'repair_action' => 'nullable|string',
            'photos' => 'nullable|array',
            'photos.*' => 'image|max:5120',
            'status' => 'required|in:pending,in_progress,completed',
            'spareparts' => 'nullable|array',
            'spareparts.*.sparepart_name' => 'required|string',
            'spareparts.*.sparepart_code' => 'nullable|string',
            'spareparts.*.quantity' => 'required|integer|min:1',
            'spareparts.*.notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $photoPaths = [];
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $path = $photo->store('die-shop/photos', 'public');
                    $photoPaths[] = $path;
                }
            }

            $validated['repair_process'] = $validated['repair_process'] ?: '';
            $validated['problem_description'] = $validated['problem_description'] ?: '';
            $validated['cause'] = $validated['cause'] ?: '';
            $validated['repair_action'] = $validated['repair_action'] ?: '';

            $validated['photos'] = $photoPaths;

            if ($validated['status'] === 'completed') {
                $validated['completed_at'] = now();
            }

            $report = DieShopReport::create($validated);

            if (!empty($validated['spareparts'])) {
                foreach ($validated['spareparts'] as $sparepart) {
                    $report->spareparts()->create($sparepart);
                }
            }

            $report->load(['diePart', 'spareparts']);

            $report->duration_value = $report->calculateDuration();
            $report->duration_unit = $report->getDurationUnit();
            $report->duration_formatted = $report->getDurationFormatted();

            DB::commit();

            return response()->json([
                'message' => 'Laporan Die Shop berhasil dibuat',
                'data' => $report
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal membuat laporan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateDieShopReport(Request $request, $id)
    {
        $report = DieShopReport::findOrFail($id);

        $validated = $request->validate([
            'activity_type' => 'required|in:corrective,preventive',
            'pic_name' => 'required|string|max:255',
            'report_date' => 'required|date',
            'die_part_id' => 'required|exists:die_parts,id',
            'repair_process' => 'nullable|string',
            'problem_description' => 'nullable|string',
            'cause' => 'nullable|string',
            'repair_action' => 'nullable|string',
            'photos' => 'nullable|array',
            'photos.*' => 'image|max:5120',
            'status' => 'required|in:pending,in_progress,completed',
            'spareparts' => 'nullable|array',
            'spareparts.*.sparepart_name' => 'required|string',
            'spareparts.*.sparepart_code' => 'nullable|string',
            'spareparts.*.quantity' => 'required|integer|min:1',
            'spareparts.*.notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $photoPaths = $report->photos ?? [];
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $path = $photo->store('die-shop/photos', 'public');
                    $photoPaths[] = $path;
                }
            }

            $validated['repair_process'] = $validated['repair_process'] ?: '';
            $validated['problem_description'] = $validated['problem_description'] ?: '';
            $validated['cause'] = $validated['cause'] ?: '';
            $validated['repair_action'] = $validated['repair_action'] ?: '';

            $validated['photos'] = $photoPaths;

            if ($validated['status'] === 'completed' && $report->status !== 'completed') {
                $validated['completed_at'] = now();
            }

            if ($validated['status'] !== 'completed' && $report->status === 'completed') {
                $validated['completed_at'] = null;
            }

            $report->update($validated);

            if (isset($validated['spareparts'])) {
                $report->spareparts()->delete();
                foreach ($validated['spareparts'] as $sparepart) {
                    $report->spareparts()->create($sparepart);
                }
            }

            $report->load(['diePart', 'spareparts']);

            $report->duration_value = $report->calculateDuration();
            $report->duration_unit = $report->getDurationUnit();
            $report->duration_formatted = $report->getDurationFormatted();

            DB::commit();

            return response()->json([
                'message' => 'Laporan Die Shop berhasil diupdate',
                'data' => $report
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal update laporan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteDieShopReport($id)
    {
        $report = DieShopReport::findOrFail($id);

        if ($report->photos) {
            foreach ($report->photos as $photo) {
                Storage::disk('public')->delete($photo);
            }
        }

        $report->delete();

        return response()->json(['message' => 'Laporan Die Shop berhasil dihapus']);
    }

    public function getDashboardStats(Request $request)
    {
        $month = $request->month ?? now()->format('Y-m');
        $startDate = \Carbon\Carbon::parse($month)->startOfMonth();
        $endDate = \Carbon\Carbon::parse($month)->endOfMonth();

        $stats = [
            'total_reports' => DieShopReport::whereBetween('report_date', [$startDate, $endDate])->count(),
            'pending_reports' => DieShopReport::whereBetween('report_date', [$startDate, $endDate])
                ->where('status', 'pending')->count(),
            'in_progress_reports' => DieShopReport::whereBetween('report_date', [$startDate, $endDate])
                ->where('status', 'in_progress')->count(),
            'completed_reports' => DieShopReport::whereBetween('report_date', [$startDate, $endDate])
                ->where('status', 'completed')->count(),
            'corrective_reports' => DieShopReport::whereBetween('report_date', [$startDate, $endDate])
                ->where('activity_type', 'corrective')->count(),
            'preventive_reports' => DieShopReport::whereBetween('report_date', [$startDate, $endDate])
                ->where('activity_type', 'preventive')->count(),
            'active_die_parts' => DiePart::where('status', 'active')->count(),
        ];

        $recentReports = DieShopReport::with(['diePart', 'spareparts'])
            ->whereBetween('report_date', [$startDate, $endDate])
            ->latest('report_date')
            ->limit(10)
            ->get()
            ->map(function ($report) {
                $report->duration_value = $report->calculateDuration();
                $report->duration_unit = $report->getDurationUnit();
                $report->duration_formatted = $report->getDurationFormatted();
                return $report;
            });

        return response()->json([
            'statistics' => $stats,
            'recent_reports' => $recentReports,
        ]);
    }
}
