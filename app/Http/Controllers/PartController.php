<?php

namespace App\Http\Controllers;

use App\Models\Part;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class PartController extends Controller
{
    public function index(Request $request)
    {
        $query = Part::query();

        if ($request->filled('supplier')) {
            $query->where('supplier_id', $request->supplier);
        }

        if ($request->filled('type_line')) {
            $query->where('type_line', $request->type_line);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('part_code', 'like', "%{$search}%")
                  ->orWhere('id_sap', 'like', "%{$search}%")
                  ->orWhere('part_name', 'like', "%{$search}%")
                  ->orWhere('type_line', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function($q2) use ($search) {
                      $q2->where('supplier_name', 'like', "%{$search}%");
                  });
            });
        }

        $parts = $query
            ->with('supplier:id,supplier_name,supplier_code')
            ->select('parts.*')
            ->orderBy('parts.id', 'desc')
            ->paginate(10)
            ->withQueryString();

        $suppliers = Supplier::select('id', 'supplier_name', 'supplier_code')
            ->orderBy('supplier_name')
            ->get();

        $typeLines = Part::whereNotNull('type_line')
            ->where('type_line', '!=', '')
            ->distinct()
            ->orderBy('type_line')
            ->pluck('type_line')
            ->values()
            ->toArray();

        return Inertia::render('Parts/Index', [
            'parts' => $parts,
            'suppliers' => $suppliers,
            'typeLines' => $typeLines,
            'filters' => [
                'search' => $request->search,
                'supplier' => $request->supplier ? (int)$request->supplier : null,
                'type_line' => $request->type_line,
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'part_code' => 'required|unique:parts',
            'id_sap' => 'nullable|string|max:255',
            'type_line' => 'nullable|string|max:255',
            'part_name' => 'required',
            'product_images.*' => 'nullable|image|max:2048',
            'description' => 'nullable',
        ]);

        $validated['product_images'] = [];

        if ($request->hasFile('product_images')) {
            foreach ($request->file('product_images') as $image) {
                $validated['product_images'][] = $image->store('parts', 'public');
            }
        }

        Part::create($validated);

        return redirect()->back()->with('success', 'Part berhasil ditambahkan!');
    }

    public function update(Request $request, Part $part)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'part_code' => 'required|unique:parts,part_code,' . $part->id,
            'id_sap' => 'nullable|string|max:255',
            'type_line' => 'nullable|string|max:255',
            'part_name' => 'required',
            'product_images.*' => 'nullable|image|max:2048',
            'description' => 'nullable|string',
            'existing_images' => 'nullable|array',
            'existing_images.*' => 'nullable|string',
        ]);

        $keepImages = $request->input('existing_images', []);
        if (!empty($part->product_images) && is_array($part->product_images)) {
            foreach ($part->product_images as $oldImage) {
                if (!in_array($oldImage, $keepImages) && Storage::disk('public')->exists($oldImage)) {
                    Storage::disk('public')->delete($oldImage);
                }
            }
        }
        $allImages = $keepImages;
        if ($request->hasFile('product_images')) {
            foreach ($request->file('product_images') as $image) {
                $allImages[] = $image->store('parts', 'public');
            }
        }

        $validated['product_images'] = $allImages;
        unset($validated['existing_images']);

        $part->update($validated);

        return redirect()->back()->with('success', 'Part berhasil diupdate!');
    }

    public function import(Request $request)
    {
        set_time_limit(300);

        $request->validate([
            'parts' => 'required|array',
            'parts.*.part_code' => 'required|string|max:255',
            'parts.*.id_sap' => 'nullable|string|max:255',
            'parts.*.type_line' => 'nullable|string|max:255',
            'parts.*.part_name' => 'required|string|max:255',
            'parts.*.supplier_id' => 'required|integer',
        ]);

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $duplicateCodes = [];

        $existingPartCodes = Part::pluck('id', 'part_code')->toArray();
        $validSupplierIds = \App\Models\Supplier::pluck('id')->toArray();

        foreach ($request->parts as $index => $partData) {
            try {
                $rowNumber = $index + 1;

                if ($partData['supplier_id'] == 0) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: {$partData['part_name']} - Supplier tidak valid";
                    continue;
                }

                if (!in_array($partData['supplier_id'], $validSupplierIds)) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: {$partData['part_name']} - Supplier ID {$partData['supplier_id']} tidak ditemukan di database";
                    continue;
                }

                $partCode = trim($partData['part_code']);

                $originalPartCode = $partCode;
                $suffix = 1;

                while (isset($duplicateCodes[$partCode])) {
                    $partCode = substr($originalPartCode, 0, 17) . '_' . str_pad($suffix, 2, '0', STR_PAD_LEFT);
                    $suffix++;

                    if ($suffix > 99) {
                        $skipped++;
                        $errors[] = "Baris {$rowNumber}: {$partData['part_name']} - Terlalu banyak duplikat part code";
                        continue 2;
                    }
                }

                $duplicateCodes[$partCode] = true;

                if (isset($existingPartCodes[$partCode])) {
                    $part = Part::find($existingPartCodes[$partCode]);
                    $part->update([
                        'part_name' => trim($partData['part_name']),
                        'supplier_id' => $partData['supplier_id'],
                        'id_sap' => isset($partData['id_sap']) ? trim($partData['id_sap']) : $part->id_sap,
                        'type_line' => isset($partData['type_line']) ? trim($partData['type_line']) : $part->type_line,
                        'description' => isset($partData['description']) ? trim($partData['description']) : $part->description,
                    ]);
                    $updated++;
                } else {
                    $newPart = Part::create([
                        'part_code' => $partCode,
                        'id_sap' => isset($partData['id_sap']) ? trim($partData['id_sap']) : null,
                        'type_line' => isset($partData['type_line']) ? trim($partData['type_line']) : null,
                        'part_name' => trim($partData['part_name']),
                        'supplier_id' => $partData['supplier_id'],
                        'description' => isset($partData['description']) ? trim($partData['description']) : '',
                        'product_images' => [],
                    ]);
                    $existingPartCodes[$partCode] = $newPart->id;
                    $imported++;
                }

            } catch (\Illuminate\Database\QueryException $e) {
                $skipped++;
                $errorMsg = $e->getMessage();

                if (str_contains($errorMsg, 'Duplicate entry')) {
                    $errors[] = "Baris {$rowNumber}: {$partData['part_name']} - Part code sudah ada (duplikat)";
                } elseif (str_contains($errorMsg, 'foreign key constraint')) {
                    $errors[] = "Baris {$rowNumber}: {$partData['part_name']} - Supplier tidak valid";
                } else {
                    $errors[] = "Baris {$rowNumber}: {$partData['part_name']} - Database error";
                }
            } catch (\Exception $e) {
                $skipped++;
                $errors[] = "Baris {$rowNumber}: {$partData['part_name']} - " . $e->getMessage();
            }
        }

        $message = "Import selesai!\n";
        if ($imported > 0) $message .= "✓ {$imported} part baru ditambahkan\n";
        if ($updated > 0) $message .= "✓ {$updated} part diupdate\n";
        if ($skipped > 0) $message .= "⚠ {$skipped} part dilewati\n";

        if (!empty($errors)) {
            $supplierErrors = array_filter($errors, fn($e) => str_contains($e, 'Supplier'));
            $duplicateErrors = array_filter($errors, fn($e) => str_contains($e, 'duplikat'));
            $otherErrors = array_diff($errors, $supplierErrors, $duplicateErrors);

            $errorSummary = "\n\nDetail Error:\n";

            if (!empty($supplierErrors)) {
                $errorSummary .= "\n📦 Supplier Tidak Valid (" . count($supplierErrors) . "):\n";
                $errorSummary .= implode("\n", array_slice($supplierErrors, 0, 5));
                if (count($supplierErrors) > 5) {
                    $errorSummary .= "\n... dan " . (count($supplierErrors) - 5) . " lainnya";
                }
            }

            if (!empty($duplicateErrors)) {
                $errorSummary .= "\n\n🔄 Part Code Duplikat (" . count($duplicateErrors) . "):\n";
                $errorSummary .= implode("\n", array_slice($duplicateErrors, 0, 3));
                if (count($duplicateErrors) > 3) {
                    $errorSummary .= "\n... dan " . (count($duplicateErrors) - 3) . " lainnya";
                }
            }

            if (!empty($otherErrors)) {
                $errorSummary .= "\n\n❌ Error Lainnya (" . count($otherErrors) . "):\n";
                $errorSummary .= implode("\n", array_slice($otherErrors, 0, 3));
                if (count($otherErrors) > 3) {
                    $errorSummary .= "\n... dan " . (count($otherErrors) - 3) . " lainnya";
                }
            }

            return redirect()->back()->with('warning', $message . $errorSummary);
        }

        return redirect()->back()->with('success', $message);
    }

    public function destroy(Part $part)
    {
        if (!empty($part->product_images) && is_array($part->product_images)) {
            foreach ($part->product_images as $image) {
                if ($image && Storage::disk('public')->exists($image)) {
                    Storage::disk('public')->delete($image);
                }
            }
        }
        $part->delete();
        return redirect()->back()->with('success', 'Part berhasil dihapus!');
    }
}
