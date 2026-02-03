<?php
namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Line;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('line');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('product_name', 'like', "%{$search}%")
                  ->orWhere('product_code', 'like', "%{$search}%")
                  ->orWhere('customer', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('line_id')) {
            $query->where('line_id', $request->line_id);
        }

        $products = $query->orderBy('created_at', 'desc')->paginate(15);
        $lines = Line::where('is_archived', false)->get(['id', 'line_name', 'line_code']);

        return Inertia::render('Kanbans/ProductIndex', [
            'products' => $products,
            'lines' => $lines,
            'filters' => $request->only(['search', 'line_id']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|string|max:255|unique:products,id',
            'line_id' => 'required|exists:lines,id',
            'product_name' => 'required|string|max:255',
            'customer' => 'nullable|string|max:255',
            'product_code' => 'nullable|string|max:255|unique:products,product_code',
        ]);

        DB::transaction(function () use ($validated) {
            if (empty($validated['product_code'])) {
                $validated['product_code'] = Product::generateProductCode($validated['line_id']);
            }

            $validated['current_stock'] = 0;

            Product::create($validated);
        });

        return redirect()->route('products.index')->with('success', 'Product created successfully.');
    }

    public function show(Product $product)
    {
        $product->load(['line', 'kanbans' => function($query) {
            $query->orderBy('scanned_at', 'desc')->limit(50);
        }]);

        return Inertia::render('Kanbans/ProductShow', [
            'product' => $product,
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'line_id' => 'required|exists:lines,id',
            'product_name' => 'required|string|max:255',
            'customer' => 'nullable|string|max:255',
            'product_code' => 'required|string|max:255|unique:products,product_code,' . $product->id,
        ]);

        $product->update($validated);

        return redirect()->route('products.index')->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        if ($product->kanbans()->exists()) {
            return back()->with('error', 'Cannot delete product with existing kanban records.');
        }

        $product->delete();

        return redirect()->route('products.index')->with('success', 'Product deleted successfully.');
    }
}
