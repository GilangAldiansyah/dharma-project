<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NgReport;
use App\Models\Part;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="Dharma Polimetal API Documentation",
 *      description="API documentation for NG Reporting & Stock Control System"
 * )
 *
 * @OA\Server(
 *      url="/api",
 *      description="API Server"
 * )
 */
class ApiController extends Controller
{
    /**
     * @OA\Get(
     *     path="/suppliers",
     *     summary="Get all suppliers",
     *     tags={"Suppliers"},
     *     @OA\Response(
     *          response=200,
     *          description="List of suppliers"
     *     )
     * )
     */
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
     * @OA\Get(
     *     path="/parts",
     *     summary="Get all parts with supplier relation",
     *     tags={"Parts"},
     *     @OA\Response(
     *          response=200,
     *          description="List of parts"
     *     )
     * )
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
     * @OA\Get(
     *     path="/ng-reports",
     *     summary="Get all NG reports with relations",
     *     tags={"NG Reports"},
     *     @OA\Response(
     *          response=200,
     *          description="List of NG Reports"
     *     )
     * )
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
     * @OA\Post(
     *     path="/ng-reports",
     *     summary="Create a new NG report (single or multiple images)",
     *     tags={"NG Reports"},
     *
     *     @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="multipart/form-data",
     *              @OA\Schema(
     *                  required={"part_id", "ng_image", "reported_by"},
     *                  @OA\Property(property="part_id", type="integer", example=1),
     *                  @OA\Property(property="ng_image", type="string", format="binary"),
     *                  @OA\Property(
     *                       property="ng_images",
     *                       type="array",
     *                       @OA\Items(type="string", format="binary")
     *                  ),
     *                  @OA\Property(property="notes", type="string", example="Crack on surface"),
     *                  @OA\Property(property="reported_by", type="string", example="John Doe")
     *              )
     *          )
     *     ),
     *
     *     @OA\Response(
     *          response=201,
     *          description="NG report created successfully"
     *     )
     * )
     */
    public function createNgReport(Request $request)
    {
        try {
            $validated = $request->validate([
                'part_id' => 'required|exists:parts,id',
                'ng_types' => 'required|array|min:1',
            'ng_types.*' => 'required|in:fungsi,dimensi,tampilan',
                'ng_image' => 'required|image|max:5120',
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
                'ng_types' => $validated['ng_types'],
                'ng_images' => $allImages,
                'notes' => $validated['notes'] ?? null,
                'reported_by' => $validated['reported_by'],
                'reported_at' => now(),
                'status' => 'open',
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
     * @OA\Delete(
     *     path="/ng-reports/{id}",
     *     summary="Delete an NG report along with its images",
     *     tags={"NG Reports"},
     *     @OA\Parameter(
     *          name="id",
     *          in="path",
     *          required=true,
     *          description="ID of NG report",
     *          @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="NG report deleted successfully"
     *     ),
     *     @OA\Response(response=404, description="NG report not found")
     * )
     */
    public function deleteNgReport($id)
    {
        try {
            $report = NgReport::findOrFail($id);

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
