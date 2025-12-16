<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\PartMaterial;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PartMaterialController extends Controller
{
    public function index(Request $request)
    {
        $parts = PartMaterial::with('material')
            ->when($request->search, function ($query, $search) {
                $query->where('part_id', 'like', "%{$search}%")
                    ->orWhere('nama_part', 'like', "%{$search}%")
                    ->orWhereHas('material', function ($q) use ($search) {
                        $q->where('nama_material', 'like', "%{$search}%");
                    });
            })
            ->orderBy('part_id')
            ->paginate(15)
            ->withQueryString();

        $materials = Material::orderBy('material_id')->get();

        return Inertia::render('PartMaterials/Index', [
            'parts' => $parts,
            'materials' => $materials,
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    public function create()
    {
        $materials = Material::orderBy('material_id')->get();

        return Inertia::render('PartMaterials/Index', [
            'materials' => $materials,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'part_id' => 'required|string|unique:part_materials,part_id',
            'material_id' => 'required|exists:materials,id',
            'nama_part' => 'required|string|max:255',
        ]);

        PartMaterial::create($validated);

        return redirect()->route('part-materials.index')
            ->with('success', 'Part Material berhasil ditambahkan');
    }

    public function edit(PartMaterial $partMaterial)
    {
        $materials = Material::orderBy('material_id')->get();

        return Inertia::render('PartMaterials/Edit', [
            'part' => $partMaterial->load('material'),
            'materials' => $materials,
        ]);
    }

    public function update(Request $request, PartMaterial $partMaterial)
    {
        $validated = $request->validate([
            'part_id' => 'required|string|unique:part_materials,part_id,' . $partMaterial->id,
            'material_id' => 'required|exists:materials,id',
            'nama_part' => 'required|string|max:255',
        ]);

        $partMaterial->update($validated);

        return redirect()->route('part-materials.index')
            ->with('success', 'Part Material berhasil diupdate');
    }

    public function destroy(PartMaterial $partMaterial)
    {
        try {
            DB::beginTransaction();
            $isUsed = DB::table('transaksi_materials')
                ->where('part_material_id', $partMaterial->id)
                ->exists();

            if ($isUsed) {
                $transaksiCount = DB::table('transaksi_materials')
                    ->where('part_material_id', $partMaterial->id)
                    ->count();

                return redirect()->route('part-materials.index')
                    ->with('error', "Part Material tidak dapat dihapus karena sedang digunakan oleh {$transaksiCount} transaksi");
            }
            $deleted = $partMaterial->delete();

            if (!$deleted) {
                throw new \Exception('Gagal menghapus part material dari database');
            }

            DB::commit();

            return redirect()->route('part-materials.index')
                ->with('success', 'Part Material berhasil dihapus');

        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->route('part-materials.index')
                ->with('error', 'Gagal menghapus part material: ' . $e->getMessage());
        }
    }

    public function deleteMultiple(Request $request)
    {
        try {
            DB::beginTransaction();

            $validated = $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:part_materials,id'
            ]);
            $partMaterials = PartMaterial::whereIn('id', $validated['ids'])->get();

            if ($partMaterials->isEmpty()) {
                throw new \Exception('Tidak ada part material yang ditemukan');
            }

            $inUse = [];
            foreach ($partMaterials as $partMaterial) {
                $transaksiCount = DB::table('transaksi_materials')
                    ->where('part_material_id', $partMaterial->id)
                    ->count();

                if ($transaksiCount > 0) {
                    $inUse[] = "{$partMaterial->part_id} ({$transaksiCount} transaksi)";
                }
            }

            if (!empty($inUse)) {
                $message = "Beberapa part material tidak dapat dihapus karena sedang digunakan:\n" . implode("\n", $inUse);

                DB::rollBack();

                return redirect()->route('part-materials.index')
                    ->with('error', $message);
            }

            $deletedCount = 0;
            $failedDeletes = [];

            foreach ($partMaterials as $partMaterial) {
                try {
                    if ($partMaterial->delete()) {
                        $deletedCount++;
                        Log::info('Deleted part material', ['id' => $partMaterial->id, 'part_id' => $partMaterial->part_id]);
                    } else {
                        $failedDeletes[] = $partMaterial->part_id;
                    }
                } catch (\Exception $e) {
                    $failedDeletes[] = $partMaterial->part_id;
                }
            }

            if (!empty($failedDeletes)) {
                throw new \Exception('Gagal menghapus part material: ' . implode(', ', $failedDeletes));
            }

            $remaining = PartMaterial::whereIn('id', $validated['ids'])->count();
            if ($remaining > 0) {
                throw new \Exception('Masih ada ' . $remaining . ' part material yang belum terhapus');
            }

            DB::commit();

            return redirect()->route('part-materials.index')
                ->with('success', $deletedCount . ' part material berhasil dihapus');

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return redirect()->route('part-materials.index')
                ->with('error', 'Validasi gagal: ' . json_encode($e->errors()));

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('part-materials.index')
                ->with('error', 'Gagal menghapus part material: ' . $e->getMessage());
        }
    }

    public function import(Request $request)
    {
        set_time_limit(300);

        try {
            $request->validate([
                'parts' => 'required|array',
                'parts.*.part_id' => 'required|string|max:255',
                'parts.*.nama_part' => 'required|string|max:255',
                'parts.*.material_id' => 'required|string|max:255',
            ]);

            $imported = 0;
            $updated = 0;
            $skipped = 0;
            $errors = [];
            $duplicateCodes = [];
            $materialsByCode = Material::pluck('id', 'material_id')->toArray();
            $existingPartIds = PartMaterial::pluck('id', 'part_id')->toArray();

            DB::beginTransaction();

            foreach ($request->parts as $index => $partData) {
                try {
                    $rowNumber = $index + 1;

                    $partId = trim($partData['part_id']);
                    $materialCode = trim($partData['material_id']);
                    $originalPartId = $partId;
                    $suffix = 1;

                    while (isset($duplicateCodes[$partId])) {
                        $partId = substr($originalPartId, 0, 17) . '_' . str_pad($suffix, 2, '0', STR_PAD_LEFT);
                        $suffix++;

                        if ($suffix > 99) {
                            $skipped++;
                            $errors[] = "Baris {$rowNumber}: {$partData['nama_part']} - Terlalu banyak duplikat part ID";
                            continue 2;
                        }
                    }

                    $duplicateCodes[$partId] = true;

                    if (!isset($materialsByCode[$materialCode])) {
                        $skipped++;
                        $errors[] = "Baris {$rowNumber}: {$partData['nama_part']} - Material ID '{$materialCode}' tidak ditemukan";
                        continue;
                    }

                    $materialId = $materialsByCode[$materialCode];

                    if (isset($existingPartIds[$partId])) {
                        $part = PartMaterial::find($existingPartIds[$partId]);
                        $part->update([
                            'nama_part' => trim($partData['nama_part']),
                            'material_id' => $materialId,
                        ]);
                        $updated++;
                    } else {
                        $newPart = PartMaterial::create([
                            'part_id' => $partId,
                            'nama_part' => trim($partData['nama_part']),
                            'material_id' => $materialId,
                        ]);
                        $existingPartIds[$partId] = $newPart->id;
                        $imported++;
                    }

                } catch (\Illuminate\Database\QueryException $e) {
                    $skipped++;
                    $errorMsg = $e->getMessage();

                    if (str_contains($errorMsg, 'Duplicate entry')) {
                        $errors[] = "Baris {$rowNumber}: {$partData['nama_part']} - Part ID sudah ada (duplikat)";
                    } else {
                        $errors[] = "Baris {$rowNumber}: {$partData['nama_part']} - Database error: " . $e->getMessage();
                    }
                } catch (\Exception $e) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: {$partData['nama_part']} - " . $e->getMessage();
                }
            }

            DB::commit();

            $message = "Import selesai!\n";
            if ($imported > 0) $message .= "✓ {$imported} part baru ditambahkan\n";
            if ($updated > 0) $message .= "✓ {$updated} part diupdate\n";
            if ($skipped > 0) $message .= "⚠ {$skipped} part dilewati\n";

            if (!empty($errors)) {
                $materialNotFoundErrors = array_filter($errors, fn($e) => str_contains($e, 'tidak ditemukan'));
                $duplicateErrors = array_filter($errors, fn($e) => str_contains($e, 'duplikat'));
                $otherErrors = array_diff($errors, array_merge($materialNotFoundErrors, $duplicateErrors));

                $errorSummary = "\n\nDetail Error:\n";

                if (!empty($materialNotFoundErrors)) {
                    $errorSummary .= "\n🔍 Material Tidak Ditemukan (" . count($materialNotFoundErrors) . "):\n";
                    $errorSummary .= implode("\n", array_slice($materialNotFoundErrors, 0, 5));
                    if (count($materialNotFoundErrors) > 5) {
                        $errorSummary .= "\n... dan " . (count($materialNotFoundErrors) - 5) . " lainnya";
                    }
                }

                if (!empty($duplicateErrors)) {
                    $errorSummary .= "\n\n🔄 Part ID Duplikat (" . count($duplicateErrors) . "):\n";
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

                return redirect()->route('part-materials.index')->with('warning', $message . $errorSummary);
            }

            return redirect()->route('part-materials.index')->with('success', $message);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Validasi gagal: ' . json_encode($e->errors()));
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Import gagal: ' . $e->getMessage());
        }
    }
}
