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

                DiesHistorySparepart::create([
                    'tipe'           => 'masuk',
                    'maintenance_id' => null,
                    'sparepart_id'   => $diesSparepart->id,
                    'dies_id'        => null,
                    'quantity'       => $request->qty,
                    'notes'          => $request->notes,
                    'created_by'     => Auth::id(),
                ]);
            } else {
                $diesSparepart->decrement('stok', $request->qty);
            }
        });

        return back()->with('success', 'Stok berhasil disesuaikan.');
    }

    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'ids'          => 'required|array|min:1',
            'ids.*'        => 'required|exists:dies_spareparts,id',
            'stok_tambah'  => 'nullable|integer|min:1',
            'stok_minimum' => 'nullable|integer|min:0',
            'notes'        => 'nullable|string',
        ]);

        if (!$request->filled('stok_tambah') && !$request->filled('stok_minimum') && $request->stok_minimum !== 0) {
            return back()->with('error', 'Isi minimal salah satu nilai yang ingin diubah.');
        }

        DB::transaction(function () use ($request) {
            $spareparts = DiesSparepart::whereIn('id', $request->ids)->get();

            foreach ($spareparts as $sp) {
                if ($request->filled('stok_tambah') && $request->stok_tambah > 0) {
                    $sp->increment('stok', $request->stok_tambah);

                    DiesHistorySparepart::create([
                        'tipe'           => 'masuk',
                        'maintenance_id' => null,
                        'sparepart_id'   => $sp->id,
                        'dies_id'        => null,
                        'quantity'       => $request->stok_tambah,
                        'notes'          => $request->notes,
                        'created_by'     => Auth::id(),
                    ]);
                }

                if ($request->has('stok_minimum') && $request->stok_minimum !== null) {
                    $sp->update(['stok_minimum' => $request->stok_minimum]);
                }
            }
        });

        return back()->with('success', 'Bulk update berhasil untuk ' . count($request->ids) . ' sparepart.');
    }

    public function historyIndex(Request $request)
    {
        $query = DiesHistorySparepart::with(['sparepart', 'dies', 'createdBy'])->latest();

        if ($request->filled('tipe')) {
            $query->where('tipe', $request->tipe);
        }

        if ($request->filled('flow')) {
            if ($request->flow === 'masuk') {
                $query->where('tipe', 'masuk');
            } elseif ($request->flow === 'keluar') {
                $query->whereIn('tipe', ['preventive', 'corrective', 'reguler']);
            }
        }

        if ($request->filled('sparepart_id')) {
            $query->where('sparepart_id', $request->sparepart_id);
        }

        if ($request->filled('dies_id')) {
            $query->where('dies_id', $request->dies_id);
        }

        $histories  = $query->paginate(20)->withQueryString();
        $spareparts = DiesSparepart::orderBy('sparepart_name')->get(['id', 'sparepart_name', 'sparepart_code', 'stok', 'unit']);
        $dies       = Dies::orderBy('no_part')->get(['id_sap', 'no_part', 'nama_dies', 'line']);

        return Inertia::render('Dies/Sparepart/History', [
            'histories'  => $histories,
            'spareparts' => $spareparts,
            'dies'       => $dies,
            'filters'    => $request->only('tipe', 'sparepart_id', 'dies_id', 'flow'),
        ]);
    }

    public function historyStore(Request $request)
    {
        $request->validate([
            'tipe'           => 'required|in:preventive,corrective,reguler',
            'maintenance_id' => 'nullable|required_unless:tipe,reguler|integer',
            'sparepart_id'   => 'required|exists:dies_spareparts,id',
            'dies_id'        => 'nullable|exists:dies,id_sap',
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
                'dies_id'        => $request->dies_id ?? null,
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
            if ($history->tipe === 'masuk') {
                $history->sparepart->decrement('stok', $history->quantity);
            } else {
                $history->sparepart->increment('stok', $history->quantity);
            }
            $history->delete();
        });

        return back()->with('success', 'Riwayat berhasil dihapus dan stok disesuaikan.');
    }
}
