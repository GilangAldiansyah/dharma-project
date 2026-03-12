<?php

namespace App\Http\Controllers;

use App\Models\Sparepart;
use App\Models\SparepartHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SparepartController extends Controller
{
    public function index(Request $request)
    {
        $query = Sparepart::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('sap_id', 'like', '%' . $request->search . '%');
        }

        if ($request->filter === 'low') {
            $query->whereRaw('stok <= stok_minimum');
        }

        $spareparts   = $query->orderBy('name')->get();
        $lowStokCount = Sparepart::whereRaw('stok <= stok_minimum')->count();

        return Inertia::render('Jig/Sparepart', [
            'spareparts'   => $spareparts,
            'lowStokCount' => $lowStokCount,
            'filters'      => $request->only('search', 'filter'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'sap_id'       => 'nullable|string|max:50|unique:spareparts,sap_id',
            'name'         => 'required|string|max:255',
            'satuan'       => 'required|string|max:50',
            'stok'         => 'required|numeric|min:0',
            'stok_minimum' => 'required|numeric|min:0',
        ]);

        $sparepart = Sparepart::create($request->only('sap_id', 'name', 'satuan', 'stok', 'stok_minimum'));

        if ($request->stok > 0) {
            SparepartHistory::create([
                'sparepart_id' => $sparepart->id,
                'user_id'      => Auth::id(),
                'tipe'         => 'masuk',
                'report_type'  => 'manual',
                'qty'          => $request->stok,
                'stok_before'  => 0,
                'stok_after'   => $request->stok,
                'notes'        => 'Stok awal',
            ]);
        }

        return back()->with('success', 'Sparepart berhasil ditambahkan.');
    }

    public function update(Request $request, Sparepart $sparepart)
    {
        $request->validate([
            'sap_id'       => 'nullable|string|max:50|unique:spareparts,sap_id,' . $sparepart->id,
            'name'         => 'required|string|max:255',
            'satuan'       => 'required|string|max:50',
            'stok'         => 'required|numeric|min:0',
            'stok_minimum' => 'required|numeric|min:0',
        ]);

        $sparepart->update($request->only('sap_id', 'name', 'satuan', 'stok', 'stok_minimum'));

        return back()->with('success', 'Sparepart berhasil diupdate.');
    }

    public function tambahStok(Request $request, Sparepart $sparepart)
    {
        $request->validate(['qty' => 'required|numeric|min:0.01']);

        $sparepart->tambahStok($request->qty, $request->notes ?? null);

        return back()->with('success', "Stok berhasil ditambah {$request->qty} {$sparepart->satuan}.");
    }

    public function kurangiStok(Request $request, Sparepart $sparepart)
    {
        $request->validate([
            'qty'   => 'required|numeric|min:0.01|max:' . $sparepart->stok,
            'notes' => 'nullable|string|max:255',
        ]);

        $sparepart->kurangiStok($request->qty, 'manual', null, $request->notes);

        return back()->with('success', "Stok berhasil dikurangi {$request->qty} {$sparepart->satuan}.");
    }

    public function destroy(Sparepart $sparepart)
    {
        $sparepart->delete();
        return back()->with('success', 'Sparepart berhasil dihapus.');
    }

    public function history(Request $request)
    {
        $year = $request->year ?? now()->year;

        $query = SparepartHistory::with('sparepart', 'user')
            ->orderByDesc('created_at');

        if ($request->filled('sparepart_id')) {
            $query->where('sparepart_id', $request->sparepart_id);
        }

        if ($request->filled('tipe')) {
            $query->where('tipe', $request->tipe);
        }

        if ($request->filled('month')) {
            $query->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$year . '-' . $request->month]);
        } else {
            $query->whereYear('created_at', $year);
        }

        $histories  = $query->paginate(20)->withQueryString();
        $spareparts = Sparepart::orderBy('name')->get(['id', 'name', 'satuan', 'stok']);

        return Inertia::render('Jig/SparepartHistory', [
            'histories'  => $histories,
            'spareparts' => $spareparts,
            'filters'    => [
                'sparepart_id' => $request->sparepart_id ?? '',
                'tipe'         => $request->tipe         ?? '',
                'month'        => $request->month        ?? '',
                'year'         => (string) $year,
            ],
        ]);
    }
}
