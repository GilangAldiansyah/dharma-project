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
    public function dashboard(Request $request)
    {
        // Get filter parameters
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());

        // Validate and parse dates
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        // NG by Part
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

        // NG by Supplier
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

        // Summary Statistics
        $totalNg = NgReport::whereBetween('reported_at', [$start, $end])->count();
        $openNg = NgReport::whereBetween('reported_at', [$start, $end])
            ->where('status', NgReport::STATUS_OPEN)
            ->count();
        $picaSubmitted = NgReport::whereBetween('reported_at', [$start, $end])
            ->where('status', NgReport::STATUS_PICA_SUBMITTED)
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

        // Daily Trend
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

        // Status Distribution
        $statusDistribution = [
            ['status' => 'Open', 'count' => $openNg, 'color' => 'red'],
            ['status' => 'PICA Submitted', 'count' => $picaSubmitted, 'color' => 'yellow'],
            ['status' => 'Closed', 'count' => $closedNg, 'color' => 'green'],
        ];

        // Top 5 Critical Parts (parts with most NG)
        $criticalParts = $ngByPart->take(5);

        // Top 5 Critical Suppliers
        $criticalSuppliers = $ngBySupplier->take(5);

        return Inertia::render('NgReports/Dashboard', [
            'ngByPart' => $ngByPart,
            'ngBySupplier' => $ngBySupplier,
            'summary' => [
                'total_ng' => $totalNg,
                'open_ng' => $openNg,
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
        ]);
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

        $reports = $query->latest('reported_at')->paginate(10)->withQueryString();

        $parts = Part::with('supplier')
            ->select('id', 'part_code', 'part_name', 'supplier_id', 'product_images')
            ->get();

        return Inertia::render('NgReports/Index', [
            'reports' => $reports,
            'parts' => $parts,
            'filters' => [
                'search' => $request->search ?? '',
                'status' => $request->status ?? 'all',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'part_id' => 'required|exists:parts,id',
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

    public function uploadPica(Request $request, NgReport $ngReport)
    {
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
            'status' => NgReport::STATUS_PICA_SUBMITTED,
        ]);

        return redirect()->back()->with('success', 'PICA berhasil diupload!');
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
            'status' => NgReport::STATUS_OPEN,
        ]);

        return redirect()->back()->with('success', 'PICA berhasil dibatalkan! Silakan upload ulang PICA yang sudah direvisi.');
    }

    public function closeReport(NgReport $ngReport)
    {
        $ngReport->update([
            'status' => NgReport::STATUS_CLOSED,
        ]);

        return redirect()->back()->with('success', 'Laporan berhasil ditutup!');
    }

    public function destroy(NgReport $ngReport)
    {
        if (!empty($ngReport->ng_images) && is_array($ngReport->ng_images)) {
            foreach ($ngReport->ng_images as $image) {
                if ($image && Storage::disk('public')->exists($image)) {
                    Storage::disk('public')->delete($image);
                }
            }
        }

        if ($ngReport->pica_document && Storage::disk('public')->exists($ngReport->pica_document)) {
            Storage::disk('public')->delete($ngReport->pica_document);
        }

        $ngReport->delete();

        return redirect()->back()->with('success', 'Laporan NG berhasil dihapus!');
    }
}
