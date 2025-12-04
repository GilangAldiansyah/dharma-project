<?php

namespace App\Http\Controllers;

use App\Models\DiePart;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DiePartController extends Controller
{
    public function index(Request $request)
    {
        $query = DiePart::query();

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('part_no', 'like', "%{$request->search}%")
                  ->orWhere('part_name', 'like', "%{$request->search}%");
            });
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $dieParts = $query->latest()->paginate(15);

        return Inertia::render('DieShop/MasterParts/Index', [
            'dieParts' => $dieParts,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create()
    {
        return Inertia::render('DieShop/MasterParts/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'part_no' => 'required|string|unique:die_parts,part_no',
            'part_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);

        DiePart::create($validated);

        return redirect()->route('die-parts.index')
            ->with('success', 'Die Part berhasil ditambahkan');
    }

    public function edit(DiePart $diePart)
    {
        return Inertia::render('DieShop/MasterParts/Edit', [
            'diePart' => $diePart,
        ]);
    }

    public function update(Request $request, DiePart $diePart)
    {
        $validated = $request->validate([
            'part_no' => 'required|string|unique:die_parts,part_no,' . $diePart->id,
            'part_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);

        $diePart->update($validated);

        return redirect()->route('die-parts.index')
            ->with('success', 'Die Part berhasil diupdate');
    }

    public function destroy(DiePart $diePart)
    {
        $diePart->delete();

        return redirect()->route('die-parts.index')
            ->with('success', 'Die Part berhasil dihapus');
    }
}
