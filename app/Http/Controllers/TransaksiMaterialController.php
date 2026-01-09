<?php

namespace App\Http\Controllers;

use App\Models\TransaksiMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
Use App\Helpers\DateHelper;

class TransaksiMaterialController extends Controller
{
    public function index(Request $request)
    {
        $transaksi = TransaksiMaterial::with(['material', 'partMaterial', 'user', 'pengembalian'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;
                $query->where('transaksi_id', 'like', "%{$search}%")
                    ->orWhere('pic', 'like', "%{$search}%")
                    ->orWhereHas('material', function ($q) use ($search) {
                        $q->where('nama_material', 'like', "%{$search}%");
                    });
            })
            ->when($request->filled('shift'), function ($query) use ($request) {
                $query->where('shift', $request->shift);
            })
            ->when($request->filled('date_from'), function ($query) use ($request) {
                $query->whereDate('tanggal', '>=', $request->date_from);
            })
            ->when($request->filled('date_to'), function ($query) use ($request) {
                $query->whereDate('tanggal', '<=', $request->date_to);
            })
            ->when($request->filled('has_return'), function ($query) use ($request) {
                if ($request->has_return == '1') {
                    $query->whereHas('pengembalian');
                } elseif ($request->has_return == '0') {
                    $query->whereDoesntHave('pengembalian');
                }
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $transaksi->getCollection()->transform(function ($item) {
            $item->total_pengembalian = $item->total_pengembalian;
            $item->sisa_pengembalian = $item->sisa_pengembalian;
            $item->tanggal_pengembalian_terakhir = $item->pengembalian()
                ->orderBy('tanggal_pengembalian', 'desc')
                ->value('tanggal_pengembalian');
            return $item;
        });

        $statsQuery = TransaksiMaterial::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;
                $query->where('transaksi_id', 'like', "%{$search}%")
                    ->orWhere('pic', 'like', "%{$search}%")
                    ->orWhereHas('material', function ($q) use ($search) {
                        $q->where('nama_material', 'like', "%{$search}%");
                    });
            })
            ->when($request->filled('shift'), function ($query) use ($request) {
                $query->where('shift', $request->shift);
            })
            ->when($request->filled('date_from'), function ($query) use ($request) {
                $query->whereDate('tanggal', '>=', $request->date_from);
            })
            ->when($request->filled('date_to'), function ($query) use ($request) {
                $query->whereDate('tanggal', '<=', $request->date_to);
            })
            ->when($request->filled('has_return'), function ($query) use ($request) {
                if ($request->has_return == '1') {
                    $query->whereHas('pengembalian');
                } elseif ($request->has_return == '0') {
                    $query->whereDoesntHave('pengembalian');
                }
            });

        $statistics = [
            'total' => $statsQuery->count(),
            'with_return' => (clone $statsQuery)->whereHas('pengembalian')->count(),
            'shift_1' => (clone $statsQuery)->where('shift', 1)->count(),
            'shift_2' => (clone $statsQuery)->where('shift', 2)->count(),
            'shift_3' => (clone $statsQuery)->where('shift', 3)->count(),
        ];

        return Inertia::render('Transaksi/Index', [
            'transaksi' => $transaksi,
            'statistics' => $statistics,
            'filters' => [
                'search' => $request->search,
                'shift' => $request->shift,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'has_return' => $request->has_return,
            ],
            'effectiveDate' => DateHelper::getEffectiveDate()->format('Y-m-d'),
            'currentShift' => DateHelper::getCurrentShift(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Transaksi/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tanggal' => 'required|date',
            'shift' => 'required|integer|in:1,2,3',
            'pic' => 'required|string|max:100',
            'material_id' => 'required|exists:materials,id',
            'part_material_id' => 'nullable|exists:part_materials,id',
            'qty' => 'required|numeric|min:0.01',
            'foto.*' => 'nullable|image|max:5120',
        ]);

        $today = now()->format('ymd');
        $lastTransaksi = TransaksiMaterial::whereDate('created_at', now()->toDateString())
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastTransaksi ? (intval(substr($lastTransaksi->transaksi_id, -4)) + 1) : 1;
        $validated['transaksi_id'] = 'TRX-' . $today . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);

        $validated['user_id'] = Auth::id();

        if ($request->hasFile('foto')) {
            $fotoPaths = [];
            foreach ($request->file('foto') as $foto) {
                $path = $foto->store('transaksi-materials', 'public');
                $fotoPaths[] = $path;
            }
            $validated['foto'] = $fotoPaths;
        }

        TransaksiMaterial::create($validated);

        return redirect()->route('transaksi.index')
            ->with('success', 'Transaksi berhasil dicatat');
    }

    public function show(TransaksiMaterial $transaksi)
    {
        $transaksi->load(['material', 'partMaterial', 'user', 'pengembalian.user']);

        return Inertia::render('Transaksi/Show', [
            'transaksi' => $transaksi,
        ]);
    }

    public function exportData(Request $request)
    {
        $transaksi = TransaksiMaterial::with(['material', 'partMaterial', 'user', 'pengembalian'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;
                $query->where('transaksi_id', 'like', "%{$search}%")
                    ->orWhere('pic', 'like', "%{$search}%")
                    ->orWhereHas('material', function ($q) use ($search) {
                        $q->where('nama_material', 'like', "%{$search}%");
                    });
            })
            ->when($request->filled('shift'), function ($query) use ($request) {
                $query->where('shift', $request->shift);
            })
            ->when($request->filled('date_from'), function ($query) use ($request) {
                $query->whereDate('tanggal', '>=', $request->date_from);
            })
            ->when($request->filled('date_to'), function ($query) use ($request) {
                $query->whereDate('tanggal', '<=', $request->date_to);
            })
            ->when($request->filled('has_return'), function ($query) use ($request) {
                if ($request->has_return == '1') {
                    $query->whereHas('pengembalian');
                } elseif ($request->has_return == '0') {
                    $query->whereDoesntHave('pengembalian');
                }
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $transaksi->transform(function ($item) {
            $item->total_pengembalian = $item->total_pengembalian;
            $item->sisa_pengembalian = $item->sisa_pengembalian;
            $item->tanggal_pengembalian_terakhir = $item->pengembalian()
                ->orderBy('tanggal_pengembalian', 'desc')
                ->value('tanggal_pengembalian');
            return $item;
        });

        return response()->json($transaksi);
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $transaksi = TransaksiMaterial::findOrFail($id);

            if ($transaksi->foto && is_array($transaksi->foto)) {
                foreach ($transaksi->foto as $foto) {
                    if (Storage::disk('public')->exists($foto)) {
                        Storage::disk('public')->delete($foto);
                    }
                }
            }

            foreach ($transaksi->pengembalian as $pengembalian) {
                if ($pengembalian->foto && is_array($pengembalian->foto)) {
                    foreach ($pengembalian->foto as $foto) {
                        if (Storage::disk('public')->exists($foto)) {
                            Storage::disk('public')->delete($foto);
                        }
                    }
                }
                $pengembalian->forceDelete();
            }

            $deleted = $transaksi->forceDelete();

            if (!$deleted) {
                throw new \Exception('Gagal menghapus transaksi dari database');
            }

            DB::commit();

            return redirect()->route('transaksi.index')
                ->with('success', 'Transaksi berhasil dihapus secara permanen');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting transaksi', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('transaksi.index')
                ->with('error', 'Gagal menghapus transaksi: ' . $e->getMessage());
        }
    }

    public function deleteMultiple(Request $request)
    {
        try {
            DB::beginTransaction();

            $validated = $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:transaksi_materials,id'
            ]);

            $transaksiList = TransaksiMaterial::whereIn('id', $validated['ids'])->get();

            if ($transaksiList->isEmpty()) {
                throw new \Exception('Tidak ada transaksi yang ditemukan');
            }

            $deletedCount = 0;
            foreach ($transaksiList as $transaksi) {
                if ($transaksi->foto && is_array($transaksi->foto)) {
                    foreach ($transaksi->foto as $foto) {
                        if (Storage::disk('public')->exists($foto)) {
                            Storage::disk('public')->delete($foto);
                        }
                    }
                }

                foreach ($transaksi->pengembalian as $pengembalian) {
                    if ($pengembalian->foto && is_array($pengembalian->foto)) {
                        foreach ($pengembalian->foto as $foto) {
                            if (Storage::disk('public')->exists($foto)) {
                                Storage::disk('public')->delete($foto);
                            }
                        }
                    }
                    $pengembalian->forceDelete();
                }

                $transaksi->forceDelete();
                $deletedCount++;
            }

            DB::commit();

            return redirect()->route('transaksi.index')
                ->with('success', $deletedCount . ' transaksi berhasil dihapus secara permanen');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting multiple transaksi', [
                'error' => $e->getMessage()
            ]);

            return redirect()->route('transaksi.index')
                ->with('error', 'Gagal menghapus transaksi: ' . $e->getMessage());
        }
    }

    public function history(Request $request)
    {
        $materialId = $request->material_id;
        $partMaterialId = $request->part_material_id;

        $transaksi = TransaksiMaterial::with(['material', 'partMaterial', 'user', 'pengembalian'])
            ->when($materialId, function ($query, $materialId) {
                $query->where('material_id', $materialId);
            })
            ->when($partMaterialId, function ($query, $partMaterialId) {
                $query->where('part_material_id', $partMaterialId);
            })
            ->orderBy('tanggal', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Transaksi/History', [
            'transaksi' => $transaksi,
            'filters' => [
                'material_id' => $materialId,
                'part_material_id' => $partMaterialId,
            ],
        ]);
    }
}
