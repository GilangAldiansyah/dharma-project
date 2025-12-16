<?php
namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\PartMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class MaterialController extends Controller
{
    public function index(Request $request)
    {
        $materials = Material::withCount('partMaterials')
            ->when($request->search, function ($query, $search) {
                $query->where('material_id', 'like', "%{$search}%")
                    ->orWhere('nama_material', 'like', "%{$search}%")
                    ->orWhere('material_type', 'like', "%{$search}%");
            })
            ->orderBy('material_id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Materials/Index', [
            'materials' => $materials,
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Materials/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'material_id' => 'required|string|unique:materials,material_id',
            'nama_material' => 'required|string|max:255',
            'material_type' => 'required|string|max:255',
            'satuan' => 'required|string|max:50',
        ]);

        Material::create($validated);

        return redirect()->route('materials.index')
            ->with('success', 'Material berhasil ditambahkan');
    }

    public function edit(Material $material)
    {
        return Inertia::render('Materials/Edit', [
            'material' => $material,
        ]);
    }

    public function update(Request $request, Material $material)
    {
        $validated = $request->validate([
            'material_id' => 'required|string|unique:materials,material_id,' . $material->id,
            'nama_material' => 'required|string|max:255',
            'material_type' => 'required|string|max:255',
            'satuan' => 'required|string|max:50',
        ]);

        $material->update($validated);

        return redirect()->route('materials.index')
            ->with('success', 'Material berhasil diupdate');
    }

    public function destroy(Material $material)
    {
        try {
            DB::beginTransaction();

            Log::info('Deleting material', [
                'id' => $material->id,
                'material_id' => $material->material_id,
                'nama_material' => $material->nama_material
            ]);

            // Cek apakah material sedang digunakan di part_materials
            $partMaterialsCount = $material->partMaterials()->count();

            if ($partMaterialsCount > 0) {
                Log::warning('Material in use, cannot delete', [
                    'material_id' => $material->material_id,
                    'used_in' => $partMaterialsCount
                ]);

                return redirect()->route('materials.index')
                    ->with('error', "Material tidak dapat dihapus karena sedang digunakan oleh {$partMaterialsCount} part material");
            }

            // Force delete (permanent delete)
            $deleted = $material->forceDelete();

            if (!$deleted) {
                throw new \Exception('Gagal menghapus material dari database');
            }

            Log::info('Material deleted successfully', ['id' => $material->id]);

            DB::commit();

            return redirect()->route('materials.index')
                ->with('success', 'Material berhasil dihapus secara permanen');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting material', [
                'id' => $material->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('materials.index')
                ->with('error', 'Gagal menghapus material: ' . $e->getMessage());
        }
    }

    /**
     * API Search untuk autocomplete - optimized untuk 3000+ records
     * Endpoint: GET /materials/search/api
     */
    public function searchApi(Request $request)
    {
        $query = $request->input('query', '');

        // Validasi minimal 2 karakter untuk menghindari query terlalu luas
        if (strlen($query) < 2) {
            return response()->json([]);
        }

        try {
            // Query dengan optimasi untuk data besar
            $materials = Material::query()
                ->with(['partMaterials' => function ($q) {
                    // Hanya ambil field yang diperlukan untuk mengurangi memory
                    $q->select('id', 'part_id', 'nama_part', 'material_id');
                }])
                ->where(function ($q) use ($query) {
                    // Prefix search lebih cepat daripada wildcard di awal
                    $q->where('material_id', 'like', $query . '%')
                      ->orWhere('nama_material', 'like', '%' . $query . '%');
                })
                // Hanya ambil field yang diperlukan
                ->select('id', 'material_id', 'nama_material', 'material_type', 'satuan')
                // Sort: prioritaskan exact match di material_id
                ->orderByRaw("CASE
                    WHEN material_id LIKE ? THEN 1
                    WHEN material_id LIKE ? THEN 2
                    WHEN nama_material LIKE ? THEN 3
                    ELSE 4
                END", [$query, $query . '%', $query . '%'])
                ->limit(20) // Batasi hasil untuk performa
                ->get();

            return response()->json($materials);

        } catch (\Exception $e) {
            Log::error('Material search API error', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Terjadi kesalahan saat mencari material',
                'message' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Legacy search method - keep for backward compatibility
     */
    public function search(Request $request)
    {
        return $this->searchApi($request);
    }

    public function import(Request $request)
    {
        set_time_limit(300);

        $request->validate([
            'materials' => 'required|array',
            'materials.*.material_id' => 'required|string|max:255',
            'materials.*.nama_material' => 'required|string|max:255',
            'materials.*.material_type' => 'required|string|max:255',
            'materials.*.satuan' => 'required|string|max:50',
        ]);

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $duplicateCodes = [];

        DB::beginTransaction();

        try {
            $existingMaterialIds = Material::pluck('id', 'material_id')->toArray();

            foreach ($request->materials as $index => $materialData) {
                try {
                    $rowNumber = $index + 1;

                    $materialId = trim($materialData['material_id']);

                    // Handle duplicate material_id in the same import batch
                    $originalMaterialId = $materialId;
                    $suffix = 1;

                    while (isset($duplicateCodes[$materialId])) {
                        $materialId = substr($originalMaterialId, 0, 17) . '_' . str_pad($suffix, 2, '0', STR_PAD_LEFT);
                        $suffix++;

                        if ($suffix > 99) {
                            $skipped++;
                            $errors[] = "Baris {$rowNumber}: {$materialData['nama_material']} - Terlalu banyak duplikat material ID";
                            continue 2;
                        }
                    }

                    $duplicateCodes[$materialId] = true;

                    // Check if material exists and update or create
                    if (isset($existingMaterialIds[$materialId])) {
                        $material = Material::find($existingMaterialIds[$materialId]);
                        $material->update([
                            'nama_material' => trim($materialData['nama_material']),
                            'material_type' => trim($materialData['material_type']),
                            'satuan' => trim($materialData['satuan']),
                        ]);
                        $updated++;
                    } else {
                        $newMaterial = Material::create([
                            'material_id' => $materialId,
                            'nama_material' => trim($materialData['nama_material']),
                            'material_type' => trim($materialData['material_type']),
                            'satuan' => trim($materialData['satuan']),
                        ]);
                        $existingMaterialIds[$materialId] = $newMaterial->id;
                        $imported++;
                    }

                } catch (\Illuminate\Database\QueryException $e) {
                    $skipped++;
                    $errorMsg = $e->getMessage();

                    if (str_contains($errorMsg, 'Duplicate entry')) {
                        $errors[] = "Baris {$rowNumber}: {$materialData['nama_material']} - Material ID sudah ada (duplikat)";
                    } else {
                        $errors[] = "Baris {$rowNumber}: {$materialData['nama_material']} - Database error";
                    }
                } catch (\Exception $e) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: {$materialData['nama_material']} - " . $e->getMessage();
                }
            }

            DB::commit();

            $message = "Import selesai!\n";
            if ($imported > 0) $message .= "✓ {$imported} material baru ditambahkan\n";
            if ($updated > 0) $message .= "✓ {$updated} material diupdate\n";
            if ($skipped > 0) $message .= "⚠ {$skipped} material dilewati\n";

            if (!empty($errors)) {
                $duplicateErrors = array_filter($errors, fn($e) => str_contains($e, 'duplikat'));
                $otherErrors = array_diff($errors, $duplicateErrors);

                $errorSummary = "\n\nDetail Error:\n";

                if (!empty($duplicateErrors)) {
                    $errorSummary .= "\n🔄 Material ID Duplikat (" . count($duplicateErrors) . "):\n";
                    $errorSummary .= implode("\n", array_slice($duplicateErrors, 0, 5));
                    if (count($duplicateErrors) > 5) {
                        $errorSummary .= "\n... dan " . (count($duplicateErrors) - 5) . " lainnya";
                    }
                }

                if (!empty($otherErrors)) {
                    $errorSummary .= "\n\n❌ Error Lainnya (" . count($otherErrors) . "):\n";
                    $errorSummary .= implode("\n", array_slice($otherErrors, 0, 5));
                    if (count($otherErrors) > 5) {
                        $errorSummary .= "\n... dan " . (count($otherErrors) - 5) . " lainnya";
                    }
                }

                return redirect()->back()->with('warning', $message . $errorSummary);
            }

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Material import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', 'Import gagal: ' . $e->getMessage());
        }
    }
}
