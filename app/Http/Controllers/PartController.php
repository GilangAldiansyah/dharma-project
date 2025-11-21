<?php

namespace App\Http\Controllers;

use App\Models\Part;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;
Use illuminate\Support\Facades\Storage;

class PartController extends Controller
{
    public function index(Request $request)
    {
        $query = Part::with('supplier');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('part_code', 'like', "%{$search}%")
                  ->orWhere('part_name', 'like', "%{$search}%");
        }

        $parts = $query->latest()->paginate(10);
        $suppliers = Supplier::select('id', 'supplier_name')->get();

        return Inertia::render('Parts/Index', [
            'parts' => $parts,
            'suppliers' => $suppliers,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'part_code' => 'required|unique:parts',
            'part_name' => 'required',
            'product_image' => 'nullable|image|max:2048',
            'product_images.*' => 'nullable|image|max:2048',
            'description' => 'nullable',
        ]);

        if ($request->hasFile('product_image')) {
            $validated['product_image'] = $request->file('product_image')->store('parts', 'public');
        }

         if ($request->hasFile('product_images')) {
        $images = [];
        foreach ($request->file('product_images') as $image) {
            $images[] = $image->store('parts', 'public');
        }
        $validated['product_images'] = $images;
    }

        Part::create($validated);

        return redirect()->back();
    }

    public function update(Request $request, Part $part)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'part_code' => 'required|unique:parts,part_code,' . $part->id,
            'part_name' => 'required',
            'product_image' => 'nullable|image|max:2048',
            'product_images.*' => 'nullable|image|max:2048',
            'description' => 'nullable',
        ]);

        if ($request->hasFile('product_image')) {
            if ($part->product_image) {
                Storage::disk('public')->delete($part->product_image);
            }
            $validated['product_image'] = $request->file('product_image')->store('parts', 'public');
        }

        if ($request->hasFile('product_images')) {
        // Hapus gambar lama jika ada
        if ($part->product_images) {
            foreach ($part->product_images as $oldImage) {
                Storage::disk('public')->delete($oldImage);
            }
        }

        $images = [];
        foreach ($request->file('product_images') as $image) {
            $images[] = $image->store('parts', 'public');
        }
        $validated['product_images'] = $images;
    }

        $part->update($validated);

        return redirect()->back();
    }

    public function destroy(Part $part)
    {
        if ($part->product_image) {
            Storage::disk('public')->delete($part->product_image);
        }
        $part->delete();
        return redirect()->back();
    }
}
