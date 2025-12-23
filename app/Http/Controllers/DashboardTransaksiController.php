<?php

namespace App\Http\Controllers;

use App\Models\TransaksiMaterial;
use App\Models\PengembalianMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardTransaksiController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->start_date ?? Carbon::now()->subDays(30)->format('Y-m-d');
        $endDate = $request->end_date ?? Carbon::now()->format('Y-m-d');

        // Total qty per satuan untuk material diambil
        $qtyPerSatuanDiambil = TransaksiMaterial::select(
                'materials.satuan',
                DB::raw('SUM(transaksi_materials.qty) as total_qty')
            )
            ->join('materials', 'transaksi_materials.material_id', '=', 'materials.id')
            ->whereBetween('transaksi_materials.tanggal', [$startDate, $endDate])
            ->groupBy('materials.satuan')
            ->orderBy('materials.satuan')
            ->get()
            ->map(function($item) {
                return [
                    'satuan' => $item->satuan,
                    'total_qty' => round($item->total_qty, 2)
                ];
            });

        // Total qty per satuan untuk material dikembalikan
        $qtyPerSatuanDikembalikan = PengembalianMaterial::select(
                'materials.satuan',
                DB::raw('SUM(pengembalian_materials.qty_pengembalian) as total_qty')
            )
            ->join('transaksi_materials', 'pengembalian_materials.transaksi_material_id', '=', 'transaksi_materials.id')
            ->join('materials', 'transaksi_materials.material_id', '=', 'materials.id')
            ->whereBetween('pengembalian_materials.tanggal_pengembalian', [$startDate, $endDate])
            ->groupBy('materials.satuan')
            ->orderBy('materials.satuan')
            ->get()
            ->map(function($item) {
                return [
                    'satuan' => $item->satuan,
                    'total_qty' => round($item->total_qty, 2)
                ];
            });

        // Hitung total untuk backward compatibility
        $totalDiambil = $qtyPerSatuanDiambil->sum('total_qty');
        $totalDikembalikan = $qtyPerSatuanDikembalikan->sum('total_qty');

        // Hitung terpakai per satuan
        $qtyPerSatuanTerpakai = collect();
        foreach($qtyPerSatuanDiambil as $diambil) {
            $dikembalikan = $qtyPerSatuanDikembalikan->firstWhere('satuan', $diambil['satuan']);
            $qtyDikembalikan = $dikembalikan ? $dikembalikan['total_qty'] : 0;

            $qtyPerSatuanTerpakai->push([
                'satuan' => $diambil['satuan'],
                'total_qty' => round($diambil['total_qty'] - $qtyDikembalikan, 2)
            ]);
        }

        $summary = [
            'total_transaksi' => TransaksiMaterial::whereBetween('tanggal', [$startDate, $endDate])->count(),
            'total_pengembalian' => PengembalianMaterial::whereBetween('tanggal_pengembalian', [$startDate, $endDate])->count(),
            'total_material_diambil' => round($totalDiambil, 2),
            'total_material_dikembalikan' => round($totalDikembalikan, 2),
            'total_material_terpakai' => round($totalDiambil - $totalDikembalikan, 2),
            // Tambahan breakdown per satuan
            'qty_per_satuan_diambil' => $qtyPerSatuanDiambil,
            'qty_per_satuan_dikembalikan' => $qtyPerSatuanDikembalikan,
            'qty_per_satuan_terpakai' => $qtyPerSatuanTerpakai,
        ];

        // Top 10 Materials by Frequency (untuk grafik)
        $topMaterialsByFrequency = TransaksiMaterial::select(
                'materials.material_id',
                'materials.nama_material',
                'materials.satuan',
                DB::raw('COUNT(transaksi_materials.id) as jumlah_pengambilan'),
                DB::raw('SUM(transaksi_materials.qty) as total_qty')
            )
            ->join('materials', 'transaksi_materials.material_id', '=', 'materials.id')
            ->whereBetween('transaksi_materials.tanggal', [$startDate, $endDate])
            ->groupBy('materials.id', 'materials.material_id', 'materials.nama_material', 'materials.satuan')
            ->orderBy('jumlah_pengambilan', 'desc')
            ->limit(10)
            ->get();

        // Top 10 Materials by Quantity (untuk grafik)
        $topMaterialsByQuantity = TransaksiMaterial::select(
                'materials.material_id',
                'materials.nama_material',
                'materials.satuan',
                DB::raw('COUNT(transaksi_materials.id) as jumlah_pengambilan'),
                DB::raw('SUM(transaksi_materials.qty) as total_qty')
            )
            ->join('materials', 'transaksi_materials.material_id', '=', 'materials.id')
            ->whereBetween('transaksi_materials.tanggal', [$startDate, $endDate])
            ->groupBy('materials.id', 'materials.material_id', 'materials.nama_material', 'materials.satuan')
            ->orderBy('total_qty', 'desc')
            ->limit(10)
            ->get();

        // SEMUA Materials by Frequency (untuk tabel)
        $allMaterialsByFrequency = TransaksiMaterial::select(
                'materials.material_id',
                'materials.nama_material',
                'materials.satuan',
                DB::raw('COUNT(transaksi_materials.id) as jumlah_pengambilan'),
                DB::raw('SUM(transaksi_materials.qty) as total_qty')
            )
            ->join('materials', 'transaksi_materials.material_id', '=', 'materials.id')
            ->whereBetween('transaksi_materials.tanggal', [$startDate, $endDate])
            ->groupBy('materials.id', 'materials.material_id', 'materials.nama_material', 'materials.satuan')
            ->orderBy('jumlah_pengambilan', 'desc')
            ->get();

        // SEMUA Materials by Quantity (untuk tabel)
        $allMaterialsByQuantity = TransaksiMaterial::select(
                'materials.material_id',
                'materials.nama_material',
                'materials.satuan',
                DB::raw('COUNT(transaksi_materials.id) as jumlah_pengambilan'),
                DB::raw('SUM(transaksi_materials.qty) as total_qty')
            )
            ->join('materials', 'transaksi_materials.material_id', '=', 'materials.id')
            ->whereBetween('transaksi_materials.tanggal', [$startDate, $endDate])
            ->groupBy('materials.id', 'materials.material_id', 'materials.nama_material', 'materials.satuan')
            ->orderBy('total_qty', 'desc')
            ->get();

        // Transaksi per Shift
        $transaksiPerShift = TransaksiMaterial::select(
                'shift',
                DB::raw('COUNT(*) as total_transaksi'),
                DB::raw('SUM(qty) as total_qty')
            )
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->groupBy('shift')
            ->orderBy('shift')
            ->get();

        // Trend 7 days
        $trendStart = Carbon::parse($endDate)->subDays(6)->format('Y-m-d');
        $transaksiTrend = TransaksiMaterial::select(
                DB::raw('DATE(tanggal) as tanggal'),
                DB::raw('COUNT(*) as total_transaksi'),
                DB::raw('SUM(qty) as total_qty')
            )
            ->whereBetween('tanggal', [$trendStart, $endDate])
            ->groupBy(DB::raw('DATE(tanggal)'))
            ->orderBy('tanggal')
            ->get();

        // Top 10 Material Pengembalian (untuk grafik)
        $topMaterialPengembalian = PengembalianMaterial::select(
                'materials.material_id',
                'materials.nama_material',
                'materials.satuan',
                DB::raw('COUNT(pengembalian_materials.id) as jumlah_pengembalian'),
                DB::raw('SUM(pengembalian_materials.qty_pengembalian) as total_qty_pengembalian')
            )
            ->join('transaksi_materials', 'pengembalian_materials.transaksi_material_id', '=', 'transaksi_materials.id')
            ->join('materials', 'transaksi_materials.material_id', '=', 'materials.id')
            ->whereBetween('pengembalian_materials.tanggal_pengembalian', [$startDate, $endDate])
            ->groupBy('materials.id', 'materials.material_id', 'materials.nama_material', 'materials.satuan')
            ->orderBy('jumlah_pengembalian', 'desc')
            ->limit(10)
            ->get();

        // SEMUA Material Pengembalian (untuk tabel)
        $allMaterialPengembalian = PengembalianMaterial::select(
                'materials.material_id',
                'materials.nama_material',
                'materials.satuan',
                DB::raw('COUNT(pengembalian_materials.id) as jumlah_pengembalian'),
                DB::raw('SUM(pengembalian_materials.qty_pengembalian) as total_qty_pengembalian')
            )
            ->join('transaksi_materials', 'pengembalian_materials.transaksi_material_id', '=', 'transaksi_materials.id')
            ->join('materials', 'transaksi_materials.material_id', '=', 'materials.id')
            ->whereBetween('pengembalian_materials.tanggal_pengembalian', [$startDate, $endDate])
            ->groupBy('materials.id', 'materials.material_id', 'materials.nama_material', 'materials.satuan')
            ->orderBy('jumlah_pengembalian', 'desc')
            ->get();

        return Inertia::render('Transaksi/Dashboard', [
            'summary' => $summary,
            'topMaterialsByFrequency' => $topMaterialsByFrequency,
            'topMaterialsByQuantity' => $topMaterialsByQuantity,
            'allMaterialsByFrequency' => $allMaterialsByFrequency,
            'allMaterialsByQuantity' => $allMaterialsByQuantity,
            'transaksiPerShift' => $transaksiPerShift,
            'transaksiTrend' => $transaksiTrend,
            'topMaterialPengembalian' => $topMaterialPengembalian,
            'allMaterialPengembalian' => $allMaterialPengembalian,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }
}
