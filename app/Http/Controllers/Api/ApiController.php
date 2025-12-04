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
    public function getSuppliers()
    {
        $suppliers = Supplier::latest()->get();
        return response()->json(['data' => $suppliers]);
    }
    public function getParts()
    {
        $parts = Part::with('supplier')->latest()->get();
        return response()->json(['data' => $parts]);
    }

    public function getNgReports()
    {
        $reports = NgReport::with(['part.supplier'])->latest()->get();
        return response()->json(['data' => $reports]);
    }

    public function createNgReport(Request $request)
    {
        $validated = $request->validate([
            'part_id' => 'required|exists:parts,id',
            'ng_image' => 'required|image|max:5120', // gambar pertama
            'ng_images' => 'nullable|array',
            'ng_images.*' => 'image|max:5120',
            'notes' => 'nullable|string',
            'reported_by' => 'required|string',
        ]);

        $allImages = [];

        if ($request->hasFile('ng_image')) {
            $allImages[] = $request->file('ng_image')->store('ng-reports', 'public');
        }

        if ($request->hasFile('ng_images')) {
            foreach ($request->file('ng_images') as $img) {
                $allImages[] = $img->store('ng-reports', 'public');
            }
        }

        $report = NgReport::create([
            'part_id' => $validated['part_id'],
            'ng_images' => $allImages,
            'notes' => $validated['notes'] ?? null,
            'reported_by' => $validated['reported_by'],
            'reported_at' => now(),
        ]);

        $report->load('part.supplier');

        return response()->json([
            "message" => "Laporan NG berhasil dibuat",
            "data" => $report
        ], 201);
    }

    public function deleteNgReport($id)
    {
        $report = NgReport::findOrFail($id);

        if ($report->ng_images && is_array($report->ng_images)) {
            foreach ($report->ng_images as $image) {
                Storage::disk('public')->delete($image);
            }
        }

        $report->delete();

        return response()->json(['message' => 'Laporan berhasil dihapus']);
    }
}
