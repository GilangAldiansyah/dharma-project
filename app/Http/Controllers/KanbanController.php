<?php
namespace App\Http\Controllers;

use App\Models\Kanban;
use App\Models\RfidMaster;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class KanbanController extends Controller
{
    public function index(Request $request)
    {
        $query = Kanban::with('product.line');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('rfid_tag', 'like', "%{$search}%")
                  ->orWhere('kanban_no', 'like', "%{$search}%")
                  ->orWhere('operator_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('scan_type')) {
            $query->where('scan_type', $request->scan_type);
        }

        if ($request->filled('date')) {
            $query->whereDate('scanned_at', $request->date);
        }

        $kanbans = $query->orderBy('scanned_at', 'desc')->paginate(15);

        $mastersQuery = RfidMaster::with('product.line');

        if ($request->filled('master_search')) {
            $search = $request->master_search;
            $mastersQuery->where(function($q) use ($search) {
                $q->where('rfid_tag', 'like', "%{$search}%")
                  ->orWhereHas('product', function($query) use ($search) {
                      $query->where('product_name', 'like', "%{$search}%")
                            ->orWhere('product_code', 'like', "%{$search}%");
                  });
            });
        }

        $rfidMasters = $mastersQuery->orderBy('created_at', 'desc')->paginate(15, ['*'], 'master_page');

        $products = Product::with('line')->get(['id', 'product_name', 'product_code', 'line_id']);

        return Inertia::render('Kanbans/KanbanIndex', [
            'kanbans' => $kanbans,
            'rfidMasters' => $rfidMasters,
            'products' => $products,
            'filters' => $request->only(['search', 'product_id', 'scan_type', 'date', 'master_search']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'rfid_tag' => 'required|string|max:255',
            'product_id' => 'required|exists:products,id',
            'scan_type' => 'required|in:in,out',
            'route' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'packaging_type' => 'nullable|string|max:255',
            'quantity' => 'required|integer|min:1',
            'operator_name' => 'nullable|string|max:255',
            'shift' => 'nullable|integer|in:1,2,3',
            'notes' => 'nullable|string',
            'kanban_no' => 'nullable|string|max:255|unique:kanbans,kanban_no',
            'force_scan' => 'nullable|boolean',
        ]);

        if (!($validated['force_scan'] ?? false) && Kanban::isRfidUsedToday($validated['rfid_tag'])) {
            return back()->withErrors(['rfid_tag' => 'RFID tag has already been scanned today.'])->withInput();
        }

        DB::transaction(function () use ($validated) {
            if (empty($validated['kanban_no'])) {
                $validated['kanban_no'] = Kanban::generateKanbanNo($validated['product_id']);
            }

            $validated['scanned_at'] = now();
            unset($validated['force_scan']);

            Kanban::create($validated);
        });

        return redirect()->route('kanbans.index')->with('success', 'Kanban scan recorded successfully.');
    }

    public function storeMaster(Request $request)
    {
        $validated = $request->validate([
            'rfid_tag' => 'required|string|max:255|unique:rfid_masters,rfid_tag',
            'product_id' => 'required|exists:products,id',
            'scan_type' => 'required|in:in,out',
            'route' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'packaging_type' => 'nullable|string|max:255',
            'quantity' => 'required|integer|min:1',
            'operator_name' => 'nullable|string|max:255',
            'shift' => 'nullable|integer|in:1,2,3',
            'notes' => 'nullable|string',
        ]);

        RfidMaster::create($validated);

        return redirect()->route('kanbans.index')->with('success', 'RFID Master registered successfully.');
    }

    public function updateMaster(Request $request, RfidMaster $rfidMaster)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'scan_type' => 'required|in:in,out',
            'route' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'packaging_type' => 'nullable|string|max:255',
            'quantity' => 'required|integer|min:1',
            'operator_name' => 'nullable|string|max:255',
            'shift' => 'nullable|integer|in:1,2,3',
            'notes' => 'nullable|string',
        ]);

        $rfidMaster->update($validated);

        return redirect()->route('kanbans.index')->with('success', 'RFID Master updated successfully.');
    }

    public function destroyMaster(RfidMaster $rfidMaster)
    {
        $rfidMaster->delete();
        return redirect()->route('kanbans.index')->with('success', 'RFID Master deleted successfully.');
    }

    public function show(Kanban $kanban)
    {
        $kanban->load('product.line');

        return Inertia::render('Kanbans/KanbanShow', [
            'kanban' => $kanban,
        ]);
    }

    public function scan(Request $request)
    {
        $validated = $request->validate([
            'rfid_tag' => 'required|string|max:255',
            'product_id' => 'required|exists:products,id',
            'scan_type' => 'required|in:in,out',
            'route' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'packaging_type' => 'nullable|string|max:255',
            'quantity' => 'required|integer|min:1',
            'operator_name' => 'nullable|string|max:255',
            'shift' => 'nullable|integer|in:1,2,3',
            'notes' => 'nullable|string',
            'force_scan' => 'nullable|boolean',
        ]);

        if (!($validated['force_scan'] ?? false) && Kanban::isRfidUsedToday($validated['rfid_tag'])) {
            return response()->json([
                'success' => false,
                'message' => 'RFID tag has already been scanned today.'
            ], 422);
        }

        $kanban = DB::transaction(function () use ($validated) {
            $validated['kanban_no'] = Kanban::generateKanbanNo($validated['product_id']);
            $validated['scanned_at'] = now();
            unset($validated['force_scan']);

            return Kanban::create($validated);
        });

        $kanban->load('product.line');

        return response()->json([
            'success' => true,
            'message' => 'Scan recorded successfully.',
            'data' => $kanban
        ]);
    }

    public function history(Request $request, $rfidTag)
    {
        $kanbans = Kanban::where('rfid_tag', $rfidTag)
            ->with('product.line')
            ->orderBy('scanned_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $kanbans
        ]);
    }

    public function checkRfid(Request $request)
    {
        $validated = $request->validate([
            'rfid_tag' => 'required|string|max:255',
        ]);

        $isUsed = Kanban::isRfidUsedToday($validated['rfid_tag']);

        return response()->json([
            'success' => true,
            'is_used_today' => $isUsed,
            'message' => $isUsed ? 'RFID already scanned today.' : 'RFID available for scanning.'
        ]);
    }

    public function getRfidMasterData(Request $request)
    {
        $validated = $request->validate([
            'rfid_tag' => 'required|string|max:255',
        ]);

        $masterData = RfidMaster::where('rfid_tag', $validated['rfid_tag'])
            ->with('product.line')
            ->first();

        if (!$masterData) {
            return response()->json([
                'success' => false,
                'message' => 'RFID not registered. Please register first.',
                'is_new' => true,
                'data' => null
            ]);
        }

        $isUsedToday = Kanban::isRfidUsedToday($validated['rfid_tag']);

        return response()->json([
            'success' => true,
            'message' => 'RFID master data found.',
            'is_new' => false,
            'is_used_today' => $isUsedToday,
            'data' => [
                'product_id' => $masterData->product_id,
                'product' => $masterData->product,
                'route' => $masterData->route,
                'address' => $masterData->address,
                'packaging_type' => $masterData->packaging_type,
                'quantity' => $masterData->quantity,
                'operator_name' => $masterData->operator_name,
                'shift' => $masterData->shift,
                'last_scan_type' => $masterData->scan_type,
                'last_scanned_at' => now()->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    public function destroy(Kanban $kanban)
    {
        DB::transaction(function () use ($kanban) {
            $product = $kanban->product;

            if ($kanban->scan_type === 'in') {
                $product->decrementStock($kanban->quantity);
            } else {
                $product->incrementStock($kanban->quantity);
            }

            $kanban->delete();
        });

        return redirect()->route('kanbans.index')->with('success', 'Kanban record deleted successfully.');
    }
}
