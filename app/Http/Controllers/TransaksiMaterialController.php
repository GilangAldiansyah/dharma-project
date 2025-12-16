<?php

namespace App\Http\Controllers;

use App\Models\TransaksiMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class TransaksiMaterialController extends Controller
{
    public function index(Request $request)
    {
        $transaksi = TransaksiMaterial::with(['material', 'partMaterial', 'user'])
            ->when($request->search, function ($query, $search) {
                $query->where('transaksi_id', 'like', "%{$search}%")
                    ->orWhereHas('material', function ($q) use ($search) {
                        $q->where('nama_material', 'like', "%{$search}%");
                    });
            })
            ->when($request->shift, function ($query, $shift) {
                $query->where('shift', $shift);
            })
            ->when($request->date_from, function ($query, $date) {
                $query->whereDate('tanggal', '>=', $date);
            })
            ->when($request->date_to, function ($query, $date) {
                $query->whereDate('tanggal', '<=', $date);
            })
            ->orderBy('tanggal', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Transaksi/Index', [
            'transaksi' => $transaksi,
            'filters' => [
                'search' => $request->search,
                'shift' => $request->shift,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
            ],
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
            'material_id' => 'required|exists:materials,id',
            'part_material_id' => 'nullable|exists:part_materials,id',
            'qty' => 'required|numeric|min:0.01',
            'foto.*' => 'nullable|image|max:5120',
        ]);

        // Generate ID Transaksi
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
        $transaksi->load(['material', 'partMaterial', 'user']);

        return Inertia::render('Transaksi/Show', [
            'transaksi' => $transaksi,
        ]);
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            // Find transaksi by ID
            $transaksi = TransaksiMaterial::findOrFail($id);

            Log::info('Deleting transaksi', ['id' => $id, 'transaksi_id' => $transaksi->transaksi_id]);

            // Delete photos from storage
            if ($transaksi->foto && is_array($transaksi->foto)) {
                foreach ($transaksi->foto as $foto) {
                    if (Storage::disk('public')->exists($foto)) {
                        Storage::disk('public')->delete($foto);
                        Log::info('Deleted photo', ['path' => $foto]);
                    }
                }
            }

            // Force delete (permanent delete even if using soft deletes)
            $deleted = $transaksi->forceDelete();

            if (!$deleted) {
                throw new \Exception('Gagal menghapus transaksi dari database');
            }

            Log::info('Transaksi deleted successfully', ['id' => $id]);

            DB::commit();

            return redirect()->route('transaksi.index')
                ->with('success', 'Transaksi berhasil dihapus secara permanen');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting transaksi', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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

            Log::info('Deleting multiple transaksi', ['ids' => $validated['ids']]);

            $transaksiList = TransaksiMaterial::whereIn('id', $validated['ids'])->get();

            if ($transaksiList->isEmpty()) {
                throw new \Exception('Tidak ada transaksi yang ditemukan');
            }

            $deletedCount = 0;
            foreach ($transaksiList as $transaksi) {
                // Delete photos from storage
                if ($transaksi->foto && is_array($transaksi->foto)) {
                    foreach ($transaksi->foto as $foto) {
                        if (Storage::disk('public')->exists($foto)) {
                            Storage::disk('public')->delete($foto);
                        }
                    }
                }

                // Force delete
                $transaksi->forceDelete();
                $deletedCount++;
            }

            // Verify deletion
            $remaining = TransaksiMaterial::whereIn('id', $validated['ids'])->count();
            if ($remaining > 0) {
                throw new \Exception('Masih ada ' . $remaining . ' transaksi yang belum terhapus');
            }

            Log::info('Multiple transaksi deleted successfully', ['count' => $deletedCount]);

            DB::commit();

            return redirect()->route('transaksi.index')
                ->with('success', $deletedCount . ' transaksi berhasil dihapus secara permanen');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting multiple transaksi', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('transaksi.index')
                ->with('error', 'Gagal menghapus transaksi: ' . $e->getMessage());
        }
    }

    public function history(Request $request)
    {
        $materialId = $request->material_id;
        $partMaterialId = $request->part_material_id;

        $transaksi = TransaksiMaterial::with(['material', 'partMaterial', 'user'])
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
