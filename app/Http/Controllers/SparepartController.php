<?php

namespace App\Http\Controllers;

use App\Models\Sparepart;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SparepartController extends Controller
{
    public function index(Request $request)
    {
        $query = Sparepart::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filter === 'low') {
            $query->whereRaw('stok <= stok_minimum');
        }

        $spareparts = $query->orderBy('name')->get();

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
            'name'          => 'required|string|max:255',
            'satuan'        => 'required|string|max:50',
            'stok'          => 'required|numeric|min:0',
            'stok_minimum'  => 'required|numeric|min:0',
        ]);

        Sparepart::create($request->only('name', 'satuan', 'stok', 'stok_minimum'));

        return back()->with('success', 'Sparepart berhasil ditambahkan.');
    }

    public function update(Request $request, Sparepart $sparepart)
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'satuan'        => 'required|string|max:50',
            'stok'          => 'required|numeric|min:0',
            'stok_minimum'  => 'required|numeric|min:0',
        ]);

        $sparepart->update($request->only('name', 'satuan', 'stok', 'stok_minimum'));

        return back()->with('success', 'Sparepart berhasil diupdate.');
    }

    public function tambahStok(Request $request, Sparepart $sparepart)
    {
        $request->validate(['qty' => 'required|numeric|min:0.01']);
        $sparepart->tambahStok($request->qty);
        return back()->with('success', "Stok berhasil ditambah {$request->qty} {$sparepart->satuan}.");
    }

    public function destroy(Sparepart $sparepart)
    {
        $sparepart->delete();
        return back()->with('success', 'Sparepart berhasil dihapus.');
    }
}
