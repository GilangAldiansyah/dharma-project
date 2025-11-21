<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NgReport;
use App\Models\Part;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ApiController extends Controller
{
    // Suppliers
    public function getSuppliers()
    {
        $suppliers = Supplier::latest()->get();
        return response()->json(['data' => $suppliers]);
    }

    // Parts
    public function getParts()
    {
        $parts = Part::with('supplier')->latest()->get();
        return response()->json(['data' => $parts]);
    }

    // NG Reports
    public function getNgReports()
    {
        $reports = NgReport::with(['part.supplier'])->latest()->get();
        return response()->json(['data' => $reports]);
    }

    public function createNgReport(Request $request)
    {
        $validated = $request->validate([
            'part_id' => 'required|exists:parts,id',
            'ng_image' => 'required|image|max:5120',
            'ng_images' => 'nullable|array',
            'ng_images.*' => 'image|max:5120',
            'notes' => 'nullable|string',
            'reported_by' => 'required|string',
        ]);

        // 1. Simpan gambar utama
        $ngImagePath = $request->file('ng_image')->store('ng-reports', 'public');

        // 2. Simpan multiple images
        $additionalImages = [];
        if ($request->hasFile('ng_images')) {
            foreach ($request->file('ng_images') as $img) {
                $additionalImages[] = $img->store('ng-reports', 'public');
            }
        }

        // 3. Create NG Report
        $report = NgReport::create([
            'part_id' => $validated['part_id'],
            'ng_image' => $ngImagePath,
            'ng_images' => $additionalImages, // akan otomatis ke JSON kalau cast array di model
            'notes' => $validated['notes'] ?? null,
            'reported_by' => $validated['reported_by'],
            'reported_at' => now(),
        ]);

        // Load relasi untuk response
        $report->load('part.supplier');

        return response()->json([
            "message" => "Laporan NG berhasil dibuat",
            "data" => $report
        ], 201);
    }

    public function deleteNgReport($id)
    {
        $report = NgReport::findOrFail($id);

        // Hapus gambar utama
        if ($report->ng_image) {
            Storage::disk('public')->delete($report->ng_image);
        }

        // Hapus gambar tambahan
        if ($report->ng_images && is_array($report->ng_images)) {
            foreach ($report->ng_images as $image) {
                Storage::disk('public')->delete($image);
            }
        }

        $report->delete();

        return response()->json(['message' => 'Laporan berhasil dihapus']);
    }
}
