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
        try {
            $suppliers = Supplier::latest()->get();

            return response()->json([
                'success' => true,
                'data' => $suppliers
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch suppliers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all parts with supplier relation
     */
    public function getParts()
    {
        try {
            $parts = Part::with('supplier')->latest()->get();

            return response()->json([
                'success' => true,
                'data' => $parts
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch parts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all NG reports with relations
     */
    public function getNgReports()
    {
        try {
            $reports = NgReport::with(['part.supplier'])->latest()->get();

            return response()->json([
                'success' => true,
                'data' => $reports
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch NG reports',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new NG report with multiple images
     */
    public function createNgReport(Request $request)
    {
        try {
            $validated = $request->validate([
                'part_id' => 'required|exists:parts,id',
                'ng_image' => 'required|image|max:5120',
                'ng_images' => 'nullable|array',
                'ng_images.*' => 'image|max:5120',
                'notes' => 'nullable|string',
                'reported_by' => 'required|string',
            ]);

            $allImages = [];

            // Store main image
            if ($request->hasFile('ng_image')) {
                $allImages[] = $request->file('ng_image')->store('ng-reports', 'public');
            }

            // Store additional images
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
                'status' => 'open', // Default status
            ]);

            $report->load('part.supplier');

            return response()->json([
                'success' => true,
                'message' => 'NG report created successfully',
                'data' => $report
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create NG report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete NG report and its images
     */
    public function deleteNgReport($id)
    {
        try {
            $report = NgReport::findOrFail($id);

            // Delete all images
            if ($report->ng_images && is_array($report->ng_images)) {
                foreach ($report->ng_images as $image) {
                    if (Storage::disk('public')->exists($image)) {
                        Storage::disk('public')->delete($image);
                    }
                }
            }

            $report->delete();

            return response()->json([
                'success' => true,
                'message' => 'NG report deleted successfully'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'NG report not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete NG report',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
