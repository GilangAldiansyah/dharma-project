<?php

namespace App\Http\Controllers;

use App\Models\DieShopReport;
use App\Models\DiePart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DieShopReportController extends Controller
{
    public function index(Request $request)
    {
        $query = DieShopReport::with(['diePart', 'spareparts', 'creator']);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('report_no', 'like', "%{$request->search}%")
                  ->orWhere('pic_name', 'like', "%{$request->search}%")
                  ->orWhereHas('diePart', function ($q) use ($request) {
                      $q->where('part_name', 'like', "%{$request->search}%");
                  });
            });
        }

        if ($request->activity_type) {
            $query->where('activity_type', $request->activity_type);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->date_from) {
            $query->whereDate('report_date', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('report_date', '<=', $request->date_to);
        }

        $reports = $query->latest('report_date')->paginate(15);

        $reports->getCollection()->transform(function ($report) {
            $report->duration_value = $report->calculateDuration();
            $report->duration_unit = $report->getDurationUnit();
            $report->duration_formatted = $report->getDurationFormatted();
            return $report;
        });

        return Inertia::render('DieShop/Reports/Index', [
            'reports' => $reports,
            'filters' => $request->only(['search', 'activity_type', 'status', 'date_from', 'date_to']),
        ]);
    }

    public function create()
    {
        $dieParts = DiePart::where('status', 'active')->get();

        return Inertia::render('DieShop/Reports/Create', [
            'dieParts' => $dieParts,
        ]);
    }

    public function store(Request $request)
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
            'photos.*' => 'nullable|image|max:5120', // 5MB max
            'status' => 'required|in:pending,in_progress,completed',
            'spareparts' => 'nullable|array',
            'spareparts.*.sparepart_name' => 'required|string',
            'spareparts.*.sparepart_code' => 'nullable|string',
            'spareparts.*.quantity' => 'required|integer|min:1',
            'spareparts.*.notes' => 'nullable|string',
        ]);

        $photoPaths = [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('die-shop/photos', 'public');
                $photoPaths[] = $path;
            }
        }

        $validated['photos'] = $photoPaths;
        $validated['created_by'] = auth()->id();

        $validated['repair_process'] = $validated['repair_process'] ?: '';
        $validated['problem_description'] = $validated['problem_description'] ?: '';
        $validated['cause'] = $validated['cause'] ?: '';
        $validated['repair_action'] = $validated['repair_action'] ?: '';

        if ($validated['status'] === 'completed') {
            $validated['completed_at'] = now();
        }

        $report = DieShopReport::create($validated);

        if (!empty($validated['spareparts'])) {
            foreach ($validated['spareparts'] as $sparepart) {
                $report->spareparts()->create($sparepart);
            }
        }

        return redirect()->route('die-shop-reports.index')
            ->with('success', 'Laporan Die Shop berhasil dibuat');
    }

    public function show(DieShopReport $dieShopReport)
    {
        $dieShopReport->load(['diePart', 'spareparts', 'creator']);
        $dieShopReport->duration_value = $dieShopReport->calculateDuration();
        $dieShopReport->duration_unit = $dieShopReport->getDurationUnit();
        $dieShopReport->duration_formatted = $dieShopReport->getDurationFormatted();

        return Inertia::render('DieShop/Reports/Show', [
            'report' => $dieShopReport,
        ]);
    }

    public function edit(DieShopReport $dieShopReport)
    {
        $dieShopReport->load('spareparts');
        $dieParts = DiePart::where('status', 'active')->get();

        return Inertia::render('DieShop/Reports/Edit', [
            'report' => $dieShopReport,
            'dieParts' => $dieParts,
        ]);
    }

    public function update(Request $request, DieShopReport $dieShopReport)
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
            'photos.*' => 'nullable|image|max:5120',
            'status' => 'required|in:pending,in_progress,completed',
            'existing_photos' => 'nullable|array',
            'existing_photos.*' => 'string',
            'spareparts' => 'nullable|array',
            'spareparts.*.sparepart_name' => 'required|string',
            'spareparts.*.sparepart_code' => 'nullable|string',
            'spareparts.*.quantity' => 'required|integer|min:1',
            'spareparts.*.notes' => 'nullable|string',
        ]);

        $photoPaths = $validated['existing_photos'] ?? [];

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('die-shop/photos', 'public');
                $photoPaths[] = $path;
            }
        }

        $oldPhotos = $dieShopReport->photos ?? [];
        $removedPhotos = array_diff($oldPhotos, $photoPaths);
        foreach ($removedPhotos as $removedPhoto) {
            Storage::disk('public')->delete($removedPhoto);
        }

        $validated['photos'] = $photoPaths;

        if ($validated['status'] === 'completed' && $dieShopReport->status !== 'completed') {
            $validated['completed_at'] = now();
        }

        if ($validated['status'] !== 'completed' && $dieShopReport->status === 'completed') {
            $validated['completed_at'] = null;
        }

        $dieShopReport->update($validated);

        if (isset($validated['spareparts'])) {
            $dieShopReport->spareparts()->delete();
            foreach ($validated['spareparts'] as $sparepart) {
                $dieShopReport->spareparts()->create($sparepart);
            }
        }

        return redirect()->route('die-shop-reports.index')
            ->with('success', 'Laporan Die Shop berhasil diupdate');
    }

    public function destroy(DieShopReport $dieShopReport)
    {
        if ($dieShopReport->photos) {
            foreach ($dieShopReport->photos as $photo) {
                Storage::disk('public')->delete($photo);
            }
        }

        $dieShopReport->delete();

        return redirect()->route('die-shop-reports.index')
            ->with('success', 'Laporan Die Shop berhasil dihapus');
    }
}
