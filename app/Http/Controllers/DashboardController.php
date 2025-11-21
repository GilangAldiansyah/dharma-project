<?php

namespace App\Http\Controllers;

use App\Models\DailyStock;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));

        // Get all stock data for the selected date
        $stockData = DailyStock::where('stock_date', $date)
            ->orderBy('bl_type')
            ->orderBy('id')
            ->get()
            ->map(function ($stock) {
                // ✅ Calculate coverage days with new formula: SOH / (Qty per Day × Qty per Unit)
                $qtyDayActual = $stock->qty_day * $stock->qty_unit;

                $coverageDays = $qtyDayActual > 0
                    ? floor($stock->soh / $qtyDayActual)
                    : 999; // Jika tidak ada kebutuhan = unlimited

                // ✅ FIXED LOGIC: 3 kategori saja (Critical, Normal, Overstock)
                $status = 'normal';

                if ($qtyDayActual == 0) {
                    // Tidak ada kebutuhan harian = OVERSTOCK
                    $status = 'overstock';
                } elseif ($stock->soh < $qtyDayActual) {
                    // Stok kurang dari kebutuhan 1 hari = CRITICAL (PRIORITAS UTAMA)
                    $status = 'critical';
                } elseif ($coverageDays >= 15) {
                    // Coverage 15+ hari = OVERSTOCK
                    $status = 'overstock';
                }
                // Selain itu (coverage 1-14 hari dan SOH >= qty_day_actual) = NORMAL

                return [
                    'id' => $stock->id,
                    'bl_type' => $stock->bl_type,
                    'sap_finish' => $stock->sap_finish,
                    'id_sap' => $stock->id_sap,
                    'material_name' => $stock->material_name,
                    'part_no' => $stock->part_no,
                    'part_name' => $stock->part_name,
                    'type' => $stock->type,
                    'qty_unit' => $stock->qty_unit,
                    'qty_day' => $stock->qty_day,
                    'qty_day_actual' => $qtyDayActual, // Total kebutuhan per hari
                    'stock_awal' => $stock->stock_awal,
                    'total_produksi' => $stock->total_produksi,
                    'total_out' => $stock->total_out,
                    'ng_shift1' => $stock->ng_shift1,
                    'ng_shift2' => $stock->ng_shift2,
                    'ng_shift3' => $stock->ng_shift3 ?? 0,
                    'soh' => $stock->soh,
                    'coverage_days' => $coverageDays,
                    'status' => $status,
                ];
            });

        // Calculate statistics
        $statistics = $this->calculateStatistics($stockData);

        return Inertia::render('Dashboard/Index', [
            'stockData' => $stockData,
            'statistics' => $statistics,
            'selectedDate' => $date,
        ]);
    }

    private function calculateStatistics($stockData)
    {
        $critical = $stockData->filter(fn($item) => $item['status'] === 'critical')->count();
        $warning = $stockData->filter(fn($item) => $item['status'] === 'warning')->count();
        $normal = $stockData->filter(fn($item) => $item['status'] === 'normal')->count();
        $overstock = $stockData->filter(fn($item) => $item['status'] === 'overstock')->count();

        $totalSOH = $stockData->sum('soh');
        $totalOut = $stockData->sum('total_out');
        $totalProduksi = $stockData->sum('total_produksi');
        $totalStockAwal = $stockData->sum('stock_awal');
        $totalNG = $stockData->sum(fn($item) =>
            ($item['ng_shift1'] ?? 0) + ($item['ng_shift2'] ?? 0) + ($item['ng_shift3'] ?? 0)
        );

        $avgCoverage = $stockData->count() > 0
            ? round($stockData->avg('coverage_days'), 1)
            : 0;

        // Get low stock items (critical + warning)
        $lowStockItems = $stockData->filter(fn($item) =>
            $item['status'] === 'critical' || $item['status'] === 'warning'
        )->sortBy('coverage_days')->values();

        // Get overstock items
        $overstockItems = $stockData->filter(fn($item) =>
            $item['status'] === 'overstock'
        )->sortByDesc('coverage_days')->values();

        // Calculate by BL Type
        $bl1Count = $stockData->filter(fn($item) => $item['bl_type'] === 'BL1')->count();
        $bl2Count = $stockData->filter(fn($item) => $item['bl_type'] === 'BL2')->count();

        return [
            'critical' => $critical,
            'warning' => $warning,
            'normal' => $normal,
            'overstock' => $overstock,
            'totalSOH' => $totalSOH,
            'totalOut' => $totalOut,
            'totalProduksi' => $totalProduksi,
            'totalStockAwal' => $totalStockAwal,
            'totalNG' => $totalNG,
            'avgCoverage' => $avgCoverage,
            'lowStockItems' => $lowStockItems,
            'overstockItems' => $overstockItems,
            'bl1Count' => $bl1Count,
            'bl2Count' => $bl2Count,
            'totalItems' => $stockData->count(),
        ];
    }

    public function export(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));

        $stockData = DailyStock::where('stock_date', $date)
            ->orderBy('bl_type')
            ->orderBy('id')
            ->get()
            ->map(function ($stock) {
                // ✅ Apply new formula
                $qtyDayActual = $stock->qty_day * $stock->qty_unit;

                $coverageDays = $qtyDayActual > 0
                    ? floor($stock->soh / $qtyDayActual)
                    : 999;

                // ✅ Apply same fixed logic (3 kategori)
                $status = 'normal';

                if ($qtyDayActual == 0) {
                    $status = 'overstock';
                } elseif ($stock->soh < $qtyDayActual) {
                    $status = 'critical';
                } elseif ($coverageDays >= 15) {
                    $status = 'overstock';
                }

                return [
                    'BL Type' => $stock->bl_type,
                    'SAP Finish' => $stock->sap_finish,
                    'ID SAP' => $stock->id_sap,
                    'Material Name' => $stock->material_name,
                    'Part No' => $stock->part_no,
                    'Part Name' => $stock->part_name,
                    'Type' => $stock->type,
                    'Qty/Unit' => $stock->qty_unit,
                    'Qty/Day' => $stock->qty_day,
                    'Qty/Day Actual' => $qtyDayActual,
                    'Stock Awal' => $stock->stock_awal,
                    'Total Produksi' => $stock->total_produksi,
                    'Total Out' => $stock->total_out,
                    'SOH' => $stock->soh,
                    'Coverage Days' => $coverageDays,
                    'Status' => strtoupper($status),
                ];
            });

        $filename = "stock_dashboard_{$date}.csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($stockData) {
            $file = fopen('php://output', 'w');

            // Add BOM for UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Headers
            fputcsv($file, array_keys($stockData->first()));

            // Data
            foreach ($stockData as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function getStockTrend(Request $request)
    {
        $endDate = $request->input('date', now()->format('Y-m-d'));
        $startDate = Carbon::parse($endDate)->subDays(6)->format('Y-m-d');

        $trendData = DailyStock::whereBetween('stock_date', [$startDate, $endDate])
            ->select('stock_date', DB::raw('SUM(soh) as total_soh'), DB::raw('SUM(total_produksi) as total_produksi'), DB::raw('SUM(total_out) as total_out'))
            ->groupBy('stock_date')
            ->orderBy('stock_date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $trendData
        ]);
    }

    public function getCriticalAlerts(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));

        $criticalItems = DailyStock::where('stock_date', $date)
            ->get()
            ->filter(function($stock) {
                // ✅ Apply new formula
                $qtyDayActual = $stock->qty_day * $stock->qty_unit;
                // ✅ Hanya yang SOH < qty_day_actual yang dianggap critical
                return $stock->soh < $qtyDayActual && $qtyDayActual > 0;
            })
            ->map(function($stock) {
                $qtyDayActual = $stock->qty_day * $stock->qty_unit;

                $coverageDays = $qtyDayActual > 0
                    ? floor($stock->soh / $qtyDayActual)
                    : 999;

                return [
                    'id' => $stock->id,
                    'bl_type' => $stock->bl_type,
                    'material_name' => $stock->material_name,
                    'id_sap' => $stock->id_sap,
                    'soh' => $stock->soh,
                    'qty_day' => $stock->qty_day,
                    'qty_unit' => $stock->qty_unit,
                    'qty_day_actual' => $qtyDayActual,
                    'coverage_days' => $coverageDays,
                ];
            })
            ->sortBy('coverage_days')
            ->values();

        return response()->json([
            'success' => true,
            'data' => $criticalItems
        ]);
    }
}
