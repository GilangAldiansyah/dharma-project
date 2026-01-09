<?php

namespace App\Http\Controllers;

use App\Models\NgReport;
use App\Models\Part;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;


class NgReportController extends Controller
{
    private const TEMPLATE_PATH = 'templates/temporary_action_template.pdf';
    public function dashboard(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $templateExists = Storage::disk('public')->exists(self::TEMPLATE_PATH);
        $templateUrl = $templateExists ? Storage::url(self::TEMPLATE_PATH) : null;

        $ngByPart = NgReport::with('part')
            ->whereBetween('reported_at', [$start, $end])
            ->select('part_id', DB::raw('count(*) as total'))
            ->groupBy('part_id')
            ->orderByDesc('total')
            ->limit(15)
            ->get()
            ->map(function ($item) {
                return [
                    'part_name' => $item->part->part_name,
                    'part_code' => $item->part->part_code,
                    'total' => $item->total,
                ];
            });

        $ngBySupplier = NgReport::with('part.supplier')
            ->whereBetween('reported_at', [$start, $end])
            ->get()
            ->groupBy(function ($item) {
                return $item->part->supplier->supplier_name;
            })
            ->map(function ($items, $supplier) {
                return [
                    'supplier_name' => $supplier,
                    'total' => $items->count(),
                ];
            })
            ->sortByDesc('total')
            ->take(15)
            ->values();

        $ngByType = NgReport::whereBetween('reported_at', [$start, $end])
            ->get()
            ->pluck('ng_types')
            ->flatten()
            ->countBy()
            ->map(function ($count, $type) {
                $typeLabels = [
                    'fungsi' => 'Fungsi',
                    'dimensi' => 'Dimensi',
                    'tampilan' => 'Tampilan',
                ];
                return [
                    'type' => $type,
                    'label' => $typeLabels[$type] ?? $type,
                    'total' => $count,
                ];
            })
            ->values();

        $totalNg = NgReport::whereBetween('reported_at', [$start, $end])->count();
        $openNg = NgReport::whereBetween('reported_at', [$start, $end])
            ->where('status', NgReport::STATUS_OPEN)
            ->count();

        // Update: Count based on TA and PICA status
        $taSubmitted = NgReport::whereBetween('reported_at', [$start, $end])
            ->whereNotNull('ta_status')
            ->count();
        $picaSubmitted = NgReport::whereBetween('reported_at', [$start, $end])
            ->whereNotNull('pica_status')
            ->count();

        $closedNg = NgReport::whereBetween('reported_at', [$start, $end])
            ->where('status', NgReport::STATUS_CLOSED)
            ->count();

        $totalParts = NgReport::whereBetween('reported_at', [$start, $end])
            ->distinct('part_id')
            ->count();

        $totalSuppliers = NgReport::with('part.supplier')
            ->whereBetween('reported_at', [$start, $end])
            ->get()
            ->pluck('part.supplier_id')
            ->unique()
            ->count();

        $dailyTrend = NgReport::whereBetween('reported_at', [$start, $end])
            ->select(DB::raw('DATE(reported_at) as date'), DB::raw('count(*) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => Carbon::parse($item->date)->format('d M'),
                    'full_date' => Carbon::parse($item->date)->format('d F Y'),
                    'total' => $item->total,
                ];
            });

        $statusDistribution = [
            ['status' => 'Open', 'count' => $openNg, 'color' => 'red'],
            ['status' => 'TA Submitted', 'count' => $taSubmitted, 'color' => 'blue'],
            ['status' => 'PICA Submitted', 'count' => $picaSubmitted, 'color' => 'yellow'],
            ['status' => 'Closed', 'count' => $closedNg, 'color' => 'green'],
        ];

        $criticalParts = $ngByPart->take(5);
        $criticalSuppliers = $ngBySupplier->take(5);

        return Inertia::render('NgReports/Dashboard', [
            'ngByPart' => $ngByPart,
            'ngBySupplier' => $ngBySupplier,
            'ngByType' => $ngByType,
            'summary' => [
                'total_ng' => $totalNg,
                'open_ng' => $openNg,
                'ta_submitted' => $taSubmitted,
                'pica_submitted' => $picaSubmitted,
                'closed_ng' => $closedNg,
                'total_parts' => $totalParts,
                'total_suppliers' => $totalSuppliers,
            ],
            'dailyTrend' => $dailyTrend,
            'statusDistribution' => $statusDistribution,
            'criticalParts' => $criticalParts,
            'criticalSuppliers' => $criticalSuppliers,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'template_exists' => $templateExists,
            'template_url' => $templateUrl,
        ]);
    }

    public function exportDashboard(Request $request)
    {
        try {
            $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
            $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());

            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->endOfDay();

            $reports = NgReport::with(['part.supplier'])
                ->whereBetween('reported_at', [$start, $end])
                ->orderBy('reported_at', 'desc')
                ->get();

            $data = $reports->map(function ($report) {
                return [
                    'No. Laporan' => $report->report_number,
                    'Tanggal' => Carbon::parse($report->reported_at)->format('d/m/Y H:i'),
                    'Part Code' => $report->part->part_code,
                    'Part Name' => $report->part->part_name,
                    'Supplier' => $report->part->supplier->supplier_name,
                    'Jenis NG' => implode(', ', array_map('ucfirst', $report->ng_types)),
                    'Temporary Action' => $report->temporary_actions ? implode(', ', array_map('ucfirst', $report->temporary_actions)) : '-',
                    'TA Status' => $report->ta_status ? ucfirst($report->ta_status) : '-',
                    'PICA Status' => $report->pica_status ? ucfirst($report->pica_status) : '-',
                    'Status' => $report->status,
                    'Dilaporkan Oleh' => $report->reported_by,
                    'TA Submit' => $report->ta_submitted_at ? Carbon::parse($report->ta_submitted_at)->format('d/m/Y H:i') : '-',
                    'TA Submit By' => $report->ta_submitted_by ?? '-',
                    'PICA Upload' => $report->pica_uploaded_at ? Carbon::parse($report->pica_uploaded_at)->format('d/m/Y H:i') : '-',
                    'PICA Upload By' => $report->pica_uploaded_by ?? '-',
                    'Notes' => $report->notes ?? '-',
                ];
            })->toArray();

            return response()->json([
                'data' => $data,
                'filename' => 'NG_Reports_' . $startDate . '_to_' . $endDate . '.xlsx'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengekspor data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function uploadPicaTemplate(Request $request)
{
    $request->validate([
        'template' => 'required|file|mimes:pdf|max:10240', // max 10MB
    ]);

    try {
        if (Storage::disk('public')->exists(self::TEMPLATE_PATH)) {
            Storage::disk('public')->delete(self::TEMPLATE_PATH);
        }

        Storage::disk('public')->makeDirectory('templates');
        $request->file('template')->storeAs('templates', 'temporary_action_template.pdf', 'public');

        return redirect()->back()->with('success', 'Template PICA berhasil diupload!');
    } catch (\Exception $e) {
        return redirect()->back()->with('error', 'Gagal mengupload template: ' . $e->getMessage());
    }
}

public function deletePicaTemplate()
{
    try {
        if (Storage::disk('public')->exists(self::TEMPLATE_PATH)) {
            Storage::disk('public')->delete(self::TEMPLATE_PATH);
        }

        return redirect()->back()->with('success', 'Template PICA berhasil dihapus!');
    } catch (\Exception $e) {
        return redirect()->back()->with('error', 'Gagal menghapus template: ' . $e->getMessage());
    }
}


    public function index(Request $request)
    {
        $query = NgReport::with(['part.supplier']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('report_number', 'like', "%{$search}%")
                  ->orWhereHas('part', function ($partQuery) use ($search) {
                      $partQuery->where('part_name', 'like', "%{$search}%")
                                ->orWhere('part_code', 'like', "%{$search}%");
                  })
                  ->orWhereHas('part.supplier', function ($supplierQuery) use ($search) {
                      $supplierQuery->where('supplier_name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('ng_type') && $request->ng_type !== 'all') {
            $query->whereJsonContains('ng_types', $request->ng_type);
        }

        // Filter by TA status
        if ($request->filled('ta_status') && $request->ta_status !== 'all') {
            $query->where('ta_status', $request->ta_status);
        }

        // Filter by PICA status
        if ($request->filled('pica_status') && $request->pica_status !== 'all') {
            $query->where('pica_status', $request->pica_status);
        }

        $reports = $query->latest('reported_at')->paginate(10)->withQueryString();

        // Add deadline info to each report
        $reports->getCollection()->transform(function ($report) {
            $report->ta_deadline = $report->getTaDeadline()->format('d M Y H:i');
            $report->pica_deadline = $report->getPicaDeadline()->format('d M Y H:i');
            $report->is_ta_deadline_exceeded = $report->isTaDeadlineExceeded();
            $report->is_pica_deadline_exceeded = $report->isPicaDeadlineExceeded();
            $report->can_be_closed = $report->canBeClosed();
            return $report;
        });

        $parts = Part::with('supplier')
            ->select('id', 'part_code', 'part_name', 'supplier_id', 'product_images')
            ->get();

         $templateExists = Storage::disk('public')->exists(self::TEMPLATE_PATH);
        $templateUrl = $templateExists ? Storage::url(self::TEMPLATE_PATH) : null;

        return Inertia::render('NgReports/Index', [
            'reports' => $reports,
            'parts' => $parts,
            'filters' => [
                'search' => $request->search ?? '',
                'status' => $request->status ?? 'all',
                'ng_type' => $request->ng_type ?? 'all',
                'ta_status' => $request->ta_status ?? 'all',
                'pica_status' => $request->pica_status ?? 'all',
            ],
            'template_exists' => $templateExists,
            'template_url' => $templateUrl,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'part_id' => 'required|exists:parts,id',
            'ng_types' => 'required|array|min:1',
            'ng_types.*' => 'required|in:fungsi,dimensi,tampilan',
            'ng_images.*' => 'required|image|max:2048',
            'notes' => 'nullable|string',
            'reported_by' => 'required|string',
        ]);

        $validated['ng_images'] = [];

        if ($request->hasFile('ng_images')) {
            foreach ($request->file('ng_images') as $image) {
                $validated['ng_images'][] = $image->store('ng-reports', 'public');
            }
        }

        $validated['reported_at'] = now();
        $validated['status'] = NgReport::STATUS_OPEN;

        NgReport::create($validated);


        return redirect()->back()->with('success', 'Laporan NG berhasil dibuat!');
    }

    public function submitTemporaryAction(Request $request, NgReport $ngReport)
    {
        // Validate that TA hasn't been submitted yet or was rejected
        if ($ngReport->ta_status === NgReport::TA_STATUS_SUBMITTED ||
            $ngReport->ta_status === NgReport::TA_STATUS_APPROVED) {
            return redirect()->back()->with('error', 'Temporary Action sudah disubmit sebelumnya!');
        }

        $validated = $request->validate([
            'temporary_actions' => 'required|array|min:1',
            'temporary_actions.*' => 'required|in:repair,tukar_guling,sortir',
            'temporary_action_notes' => 'nullable|string',
            'ta_submitted_by' => 'required|string',
        ]);

        $ngReport->update([
            'temporary_actions' => $validated['temporary_actions'],
            'temporary_action_notes' => $validated['temporary_action_notes'],
            'ta_submitted_at' => now(),
            'ta_submitted_by' => $validated['ta_submitted_by'],
            'ta_status' => NgReport::TA_STATUS_SUBMITTED,
            'ta_reviewed_at' => null,
            'ta_reviewed_by' => null,
            'ta_rejection_reason' => null,
        ]);

        return redirect()->back()->with('success', 'Temporary Action berhasil disubmit!');
    }

    public function approveTemporaryAction(Request $request, NgReport $ngReport)
    {
        if ($ngReport->ta_status !== NgReport::TA_STATUS_SUBMITTED) {
            return redirect()->back()->with('error', 'Temporary Action belum disubmit atau sudah direview!');
        }

        $validated = $request->validate([
            'ta_reviewed_by' => 'required|string',
        ]);

        $ngReport->update([
            'ta_status' => NgReport::TA_STATUS_APPROVED,
            'ta_reviewed_at' => now(),
            'ta_reviewed_by' => $validated['ta_reviewed_by'],
            'ta_rejection_reason' => null,
        ]);

        return redirect()->back()->with('success', 'Temporary Action berhasil disetujui!');
    }

    public function rejectTemporaryAction(Request $request, NgReport $ngReport)
    {
        if ($ngReport->ta_status !== NgReport::TA_STATUS_SUBMITTED) {
            return redirect()->back()->with('error', 'Temporary Action belum disubmit atau sudah direview!');
        }

        $validated = $request->validate([
            'ta_reviewed_by' => 'required|string',
            'ta_rejection_reason' => 'required|string|min:10',
        ]);

        $ngReport->update([
            'ta_status' => NgReport::TA_STATUS_REJECTED,
            'ta_reviewed_at' => now(),
            'ta_reviewed_by' => $validated['ta_reviewed_by'],
            'ta_rejection_reason' => $validated['ta_rejection_reason'],
        ]);

        return redirect()->back()->with('success', 'Temporary Action ditolak. Silakan submit ulang dengan perbaikan.');
    }

    public function uploadPica(Request $request, NgReport $ngReport)
    {
        if (!$ngReport->ta_submitted_at) {
            return redirect()->back()->with('error', 'Harap submit Temporary Action terlebih dahulu sebelum upload PICA!');
        }

        if ($ngReport->pica_status === NgReport::PICA_STATUS_APPROVED) {
            return redirect()->back()->with('error', 'PICA sudah disetujui, tidak bisa diubah!');
        }

        $validated = $request->validate([
            'pica_document' => 'required|file|mimes:pdf|max:10240',
            'pica_uploaded_by' => 'required|string',
        ]);

        if ($ngReport->pica_document && Storage::disk('public')->exists($ngReport->pica_document)) {
            Storage::disk('public')->delete($ngReport->pica_document);
        }

        $picaPath = $request->file('pica_document')->store('pica-documents', 'public');

        $ngReport->update([
            'pica_document' => $picaPath,
            'pica_uploaded_at' => now(),
            'pica_uploaded_by' => $validated['pica_uploaded_by'],
            'pica_status' => NgReport::PICA_STATUS_SUBMITTED,
            'pica_reviewed_at' => null,
            'pica_reviewed_by' => null,
            'pica_rejection_reason' => null,
        ]);

        return redirect()->back()->with('success', 'PICA berhasil diupload!');
    }

    public function approvePica(Request $request, NgReport $ngReport)
    {
        if ($ngReport->pica_status !== NgReport::PICA_STATUS_SUBMITTED) {
            return redirect()->back()->with('error', 'PICA belum disubmit atau sudah direview!');
        }

        $validated = $request->validate([
            'pica_reviewed_by' => 'required|string',
        ]);

        $ngReport->update([
            'pica_status' => NgReport::PICA_STATUS_APPROVED,
            'pica_reviewed_at' => now(),
            'pica_reviewed_by' => $validated['pica_reviewed_by'],
            'pica_rejection_reason' => null,
        ]);

        return redirect()->back()->with('success', 'PICA berhasil disetujui!');
    }

    public function rejectPica(Request $request, NgReport $ngReport)
    {
        if ($ngReport->pica_status !== NgReport::PICA_STATUS_SUBMITTED) {
            return redirect()->back()->with('error', 'PICA belum disubmit atau sudah direview!');
        }

        $validated = $request->validate([
            'pica_reviewed_by' => 'required|string',
            'pica_rejection_reason' => 'required|string|min:10',
        ]);

        $ngReport->update([
            'pica_status' => NgReport::PICA_STATUS_REJECTED,
            'pica_reviewed_at' => now(),
            'pica_reviewed_by' => $validated['pica_reviewed_by'],
            'pica_rejection_reason' => $validated['pica_rejection_reason'],
        ]);

        return redirect()->back()->with('success', 'PICA ditolak. Silakan upload ulang dengan perbaikan.');
    }

    public function cancelPica(NgReport $ngReport)
    {
        if ($ngReport->pica_document && Storage::disk('public')->exists($ngReport->pica_document)) {
            Storage::disk('public')->delete($ngReport->pica_document);
        }

        $ngReport->update([
            'pica_document' => null,
            'pica_uploaded_at' => null,
            'pica_uploaded_by' => null,
            'pica_status' => null,
            'pica_reviewed_at' => null,
            'pica_reviewed_by' => null,
            'pica_rejection_reason' => null,
        ]);

        return redirect()->back()->with('success', 'PICA berhasil dibatalkan!');
    }

    public function downloadTemplate()
    {
        $templatePath = storage_path('app/public/' . self::TEMPLATE_PATH);

        if (!file_exists($templatePath)) {
            return redirect()->back()->with('error', 'Template belum diupload! Silakan hubungi admin.');
        }

        return response()->download($templatePath, 'Template-Temporary-Action.pdf');
    }

    public function closeReport(NgReport $ngReport)
    {
        if (!$ngReport->canBeClosed()) {
            return redirect()->back()->with('error', 'Laporan hanya bisa ditutup setelah Temporary Action dan PICA disetujui!');
        }

        $ngReport->update([
            'status' => NgReport::STATUS_CLOSED,
        ]);

        return redirect()->back()->with('success', 'Laporan berhasil ditutup!');
    }

    public function destroy(NgReport $ngReport)
    {
        // Delete NG images
        if (!empty($ngReport->ng_images) && is_array($ngReport->ng_images)) {
            foreach ($ngReport->ng_images as $image) {
                if ($image && Storage::disk('public')->exists($image)) {
                    Storage::disk('public')->delete($image);
                }
            }
        }

        // Delete PICA document
        if ($ngReport->pica_document && Storage::disk('public')->exists($ngReport->pica_document)) {
            Storage::disk('public')->delete($ngReport->pica_document);
        }

        $ngReport->delete();

        return redirect()->back()->with('success', 'Laporan NG berhasil dihapus!');
    }
}
