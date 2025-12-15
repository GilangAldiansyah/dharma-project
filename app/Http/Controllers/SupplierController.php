<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = Supplier::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('supplier_code', 'like', "%{$search}%")
                  ->orWhere('supplier_name', 'like', "%{$search}%");
            });
        }

        $suppliers = $query->orderBy('id', 'desc')->paginate(10)->withQueryString();

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => [
                'search' => $request->search,
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_code' => 'required|unique:suppliers',
            'supplier_name' => 'required',
            'contact_person' => 'nullable|string',
            'phone' => 'nullable|numeric|digits_between:8,15',
            'address' => 'nullable|string',
        ], [
            'phone.numeric' => 'Nomor telepon hanya boleh berisi angka',
            'phone.digits_between' => 'Nomor telepon harus antara 8-15 digit',
        ]);

        Supplier::create($validated);

        return redirect()->back()->with('success', 'Supplier berhasil ditambahkan!');
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'supplier_code' => 'required|unique:suppliers,supplier_code,' . $supplier->id,
            'supplier_name' => 'required',
            'contact_person' => 'nullable|string',
            'phone' => 'nullable|numeric|digits_between:8,15',
            'address' => 'nullable|string',
        ], [
            'phone.numeric' => 'Nomor telepon hanya boleh berisi angka',
            'phone.digits_between' => 'Nomor telepon harus antara 8-15 digit',
        ]);

        $supplier->update($validated);

        return redirect()->back()->with('success', 'Supplier berhasil diupdate!');
    }

    public function import(Request $request)
    {
        $request->validate([
            'suppliers' => 'required|array',
            'suppliers.*.supplier_code' => 'required|string',
            'suppliers.*.supplier_name' => 'required|string',
            'suppliers.*.phone' => 'nullable|numeric',
        ]);

        $imported = 0;
        $updated = 0;
        $errors = [];

        foreach ($request->suppliers as $index => $supplierData) {
            try {
                $existing = Supplier::where('supplier_code', $supplierData['supplier_code'])->first();

                if ($existing) {
                    $existing->update($supplierData);
                    $updated++;
                } else {
                    Supplier::create($supplierData);
                    $imported++;
                }
            } catch (\Exception $e) {
                $errors[] = "Baris " . ($index + 1) . ": {$supplierData['supplier_name']} - " . $e->getMessage();
            }
        }

        $message = "Import selesai!\n";
        if ($imported > 0) $message .= "✓ {$imported} supplier baru ditambahkan\n";
        if ($updated > 0) $message .= "✓ {$updated} supplier diupdate\n";

        if (!empty($errors)) {
            $message .= "\n\nError:\n" . implode("\n", array_slice($errors, 0, 5));
            if (count($errors) > 5) {
                $message .= "\n... dan " . (count($errors) - 5) . " lainnya";
            }
            return redirect()->back()->with('warning', $message);
        }

        return redirect()->back()->with('success', $message);
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();
        return redirect()->back()->with('success', 'Supplier berhasil dihapus!');
    }
}
