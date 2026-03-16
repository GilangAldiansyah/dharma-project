<?php

namespace App\Http\Controllers;

use App\Models\Dies;
use App\Models\DiesSparepart;
use App\Models\DiesHistorySparepart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DiesSparepartController extends Controller
{
    public function index(Request $request)
    {
        $query = DiesSparepart::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('sparepart_name', 'like', '%' . $request->search . '%')
                    ->orWhere('sparepart_code', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filter === 'low') {
            $query->whereRaw('stok <= stok_minimum');
        }

        $spareparts   = $query->orderBy('sparepart_name')->paginate(20)->withQueryString();
        $lowStokCount = DiesSparepart::whereRaw('stok <= stok_minimum')->count();

        return Inertia::render('Dies/Sparepart/Index', [
            'spareparts'   => $spareparts,
            'lowStokCount' => $lowStokCount,
            'filters'      => $request->only('search', 'filter'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'sparepart_code' => 'required|string|unique:dies_spareparts,sparepart_code',
            'sparepart_name' => 'required|string',
            'unit'           => 'required|string',
            'stok'           => 'required|integer|min:0',
            'stok_minimum'   => 'required|integer|min:0',
        ]);

        DiesSparepart::create($request->only('sparepart_code', 'sparepart_name', 'unit', 'stok', 'stok_minimum'));

        return back()->with('success', 'Sparepart berhasil ditambahkan.');
    }

    public function update(Request $request, DiesSparepart $diesSparepart)
    {
        $request->validate([
            'sparepart_code' => [
                'required',
                'string',
                Rule::unique('dies_spareparts', 'sparepart_code')->ignore($diesSparepart->getKey()),
            ],
            'sparepart_name' => 'required|string',
            'unit'           => 'required|string',
            'stok_minimum'   => 'required|integer|min:0',
        ]);

        $diesSparepart->update($request->only('sparepart_code', 'sparepart_name', 'unit', 'stok_minimum'));

        return back()->with('success', 'Sparepart berhasil diperbarui.');
    }

    public function destroy(DiesSparepart $diesSparepart)
    {
        if ($diesSparepart->histories()->exists()) {
            return back()->with('error', 'Sparepart tidak dapat dihapus karena sudah memiliki riwayat pemakaian.');
        }

        $diesSparepart->delete();

        return back()->with('success', 'Sparepart berhasil dihapus.');
    }

    public function adjustStok(Request $request, DiesSparepart $diesSparepart)
    {
        $request->validate([
            'qty'   => 'required|integer|min:1',
            'type'  => 'required|in:tambah,kurangi',
            'notes' => 'nullable|string',
        ]);

        if ($request->type === 'kurangi' && $diesSparepart->stok < $request->qty) {
            return back()->with('error', 'Stok tidak mencukupi. Stok tersedia: ' . $diesSparepart->stok);
        }

        DB::transaction(function () use ($request, $diesSparepart) {
            if ($request->type === 'tambah') {
                $diesSparepart->increment('stok', $request->qty);
            } else {
                $diesSparepart->decrement('stok', $request->qty);
            }
        });

        return back()->with('success', 'Stok berhasil disesuaikan.');
    }

    public function historyIndex(Request $request)
    {
        $query = DiesHistorySparepart::with(['sparepart', 'dies', 'createdBy'])->latest();

        if ($request->filled('tipe')) {
            $query->where('tipe', $request->tipe);
        }

        if ($request->filled('sparepart_id')) {
            $query->where('sparepart_id', $request->sparepart_id);
        }

        if ($request->filled('dies_id')) {
            $query->where('dies_id', $request->dies_id);
        }

        $histories  = $query->paginate(20)->withQueryString();
        $spareparts = DiesSparepart::orderBy('sparepart_name')->get(['id', 'sparepart_name', 'sparepart_code']);
        $dies       = Dies::orderBy('no_part')->get(['id_sap', 'no_part', 'nama_dies', 'line']);

        return Inertia::render('Dies/Sparepart/History', [
            'histories'  => $histories,
            'spareparts' => $spareparts,
            'dies'       => $dies,
            'filters'    => $request->only('tipe', 'sparepart_id', 'dies_id'),
        ]);
    }

    public function historyStore(Request $request)
    {
        $request->validate([
            'tipe'           => 'required|in:preventive,corrective,reguler',
            'maintenance_id' => 'nullable|required_unless:tipe,reguler|integer',
            'sparepart_id'   => 'required|exists:dies_spareparts,id',
            'dies_id'        => 'nullable|required_if:tipe,reguler|exists:dies,id_sap',
            'quantity'       => 'required|integer|min:1',
            'notes'          => 'nullable|string',
        ]);

        $sparepart = DiesSparepart::findOrFail($request->sparepart_id);

        if ($sparepart->stok < $request->quantity) {
            return back()->withErrors(['quantity' => 'Stok tidak mencukupi. Stok tersedia: ' . $sparepart->stok]);
        }

        DB::transaction(function () use ($request, $sparepart) {
            DiesHistorySparepart::create([
                'tipe'           => $request->tipe,
                'maintenance_id' => $request->tipe !== 'reguler' ? $request->maintenance_id : null,
                'sparepart_id'   => $request->sparepart_id,
                'dies_id'        => $request->tipe === 'reguler' ? $request->dies_id : null,
                'quantity'       => $request->quantity,
                'notes'          => $request->notes,
                'created_by'     => Auth::id(),
            ]);

            $sparepart->decrement('stok', $request->quantity);
        });

        return back()->with('success', 'Pemakaian sparepart berhasil dicatat.');
    }

    public function historyDestroy(DiesHistorySparepart $history)
    {
        DB::transaction(function () use ($history) {
            $history->sparepart->increment('stok', $history->quantity);
            $history->delete();
        });

        return back()->with('success', 'Riwayat pemakaian berhasil dihapus dan stok dikembalikan.');
    }
}
