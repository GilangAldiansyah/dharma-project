<?php

namespace App\Http\Controllers;

use App\Models\PengembalianMaterial;
use App\Models\TransaksiMaterial;
use App\Helpers\DateHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PengembalianMaterialController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'transaksi_material_id' => 'required|exists:transaksi_materials,id',
            'tanggal_pengembalian' => 'required|date',
            'pic' => 'required|string|max:100',
            'qty_pengembalian' => 'required|numeric|min:0.01',
            'keterangan' => 'nullable|string|max:500',
            'foto.*' => 'nullable|image|max:5120',
        ]);

        if (!DateHelper::isValidPengembalianDate($validated['tanggal_pengembalian'])) {
            return back()->withErrors([
                'tanggal_pengembalian' => 'Tanggal pengembalian tidak valid. Tidak boleh lebih dari tanggal efektif saat ini.'
            ]);
        }

        $transaksi = TransaksiMaterial::findOrFail($validated['transaksi_material_id']);
        $totalPengembalian = PengembalianMaterial::getTotalPengembalianQty($validated['transaksi_material_id']);
        $sisaQty = $transaksi->qty - $totalPengembalian;

        if ($validated['qty_pengembalian'] > $sisaQty) {
            return back()->withErrors([
                'qty_pengembalian' => "Quantity pengembalian melebihi sisa yang bisa dikembalikan. Sisa: {$sisaQty} {$transaksi->material->satuan}"
            ]);
        }

        DB::beginTransaction();
        try {
            $today = now()->format('ymd');
            $lastPengembalian = PengembalianMaterial::whereDate('created_at', now()->toDateString())
                ->orderBy('id', 'desc')
                ->first();

            $sequence = $lastPengembalian ? (intval(substr($lastPengembalian->pengembalian_id, -4)) + 1) : 1;
            $validated['pengembalian_id'] = 'PGM-' . $today . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);

            $validated['user_id'] = Auth::id();

            if ($request->hasFile('foto')) {
                $fotoPaths = [];
                foreach ($request->file('foto') as $foto) {
                    $path = $foto->store('pengembalian-materials', 'public');
                    $fotoPaths[] = $path;
                }
                $validated['foto'] = $fotoPaths;
            }

            PengembalianMaterial::create($validated);

            DB::commit();

            return redirect()->route('transaksi.index')
                ->with('success', 'Pengembalian material berhasil dicatat');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating pengembalian', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->withErrors([
                'error' => 'Gagal mencatat pengembalian: ' . $e->getMessage()
            ]);
        }
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $pengembalian = PengembalianMaterial::findOrFail($id);

            Log::info('Deleting pengembalian', ['id' => $id, 'pengembalian_id' => $pengembalian->pengembalian_id]);

            if ($pengembalian->foto && is_array($pengembalian->foto)) {
                foreach ($pengembalian->foto as $foto) {
                    if (Storage::disk('public')->exists($foto)) {
                        Storage::disk('public')->delete($foto);
                    }
                }
            }

            $pengembalian->forceDelete();

            DB::commit();

            return redirect()->route('transaksi.index')
                ->with('success', 'Pengembalian berhasil dihapus');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting pengembalian', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Gagal menghapus pengembalian: ' . $e->getMessage());
        }
    }

    public function getPengembalianHistory($transaksiId)
    {
        $pengembalian = PengembalianMaterial::with('user')
            ->where('transaksi_material_id', $transaksiId)
            ->orderBy('tanggal_pengembalian', 'desc')
            ->get();

        $totalPengembalian = $pengembalian->sum('qty_pengembalian');

        $tanggalPengembalianTerakhir = $pengembalian->first()?->tanggal_pengembalian;

        return response()->json([
            'pengembalian' => $pengembalian,
            'totalPengembalian' => $totalPengembalian,
            'tanggalPengembalianTerakhir' => $tanggalPengembalianTerakhir,
        ]);
    }
}
