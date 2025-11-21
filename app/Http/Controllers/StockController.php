<?php

namespace App\Http\Controllers;

use App\Models\DailyStock;
use App\Models\OutputProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Carbon\Carbon;

class StockController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));

        $stockDataBL1 = $this->getStockData($date, 'BL1');
        $stockDataBL2 = $this->getStockData($date, 'BL2');
        $outputData = $this->getOutputData($date);

        return Inertia::render('Stock/Index', [
            'stockData' => $stockDataBL1,
            'stockDataBL2' => $stockDataBL2,
            'outputData' => $outputData,
            'selectedDate' => $date,
        ]);
    }

    private function getStockData($date, $blType)
    {
        $stockData = DailyStock::where('stock_date', $date)
            ->where('bl_type', $blType)
            ->orderBy('id')
            ->get();

        if ($stockData->isEmpty()) {
            $yesterday = Carbon::parse($date)->subDay()->format('Y-m-d');
            $yesterdayData = DailyStock::where('stock_date', $yesterday)
                ->where('bl_type', $blType)
                ->orderBy('id')
                ->get();

            if ($yesterdayData->isNotEmpty()) {
                $newRecords = [];
                foreach ($yesterdayData as $item) {
                    $newRecord = DailyStock::create([
                        'bl_type' => $blType,
                        'sap_finish' => $item->sap_finish,
                        'id_sap' => $item->id_sap,
                        'material_name' => $item->material_name,
                        'part_no' => $item->part_no,
                        'part_name' => $item->part_name,
                        'type' => $item->type,
                        'qty_unit' => $item->qty_unit,
                        'qty_day' => $item->qty_day,
                        'stock_date' => $date,
                        'stock_awal' => $item->soh,
                        'produksi_shift1' => 0,
                        'produksi_shift2' => 0,
                        'produksi_shift3' => 0,
                        'out_shift1' => 0,
                        'out_shift2' => 0,
                        'out_shift3' => 0,
                        'ng_shift1' => 0,
                        'ng_shift2' => 0,
                        'total_produksi' => 0,
                        'total_out' => 0,
                        'soh' => $item->soh,
                    ]);
                    $newRecords[] = $this->formatStockData($newRecord);
                }
                return collect($newRecords);
            } else {
                return collect([]);
            }
        } else {
            $yesterday = Carbon::parse($date)->subDay()->format('Y-m-d');
            $yesterdayData = DailyStock::where('stock_date', $yesterday)
                ->where('bl_type', $blType)
                ->get()
                ->keyBy(function($item) {
                    return $item->id_sap ?: $item->sap_finish;
                });

            $stockData = $stockData->map(function ($item) use ($yesterdayData) {
                $yesterdayItem = $yesterdayData->get($item->id_sap ?: $item->sap_finish);
                if ($yesterdayItem) {
                    $needUpdate = false;
                    $updateData = [];

                    $fieldsToSync = ['sap_finish', 'id_sap', 'material_name', 'part_no', 'part_name', 'type', 'qty_unit'];
                    foreach ($fieldsToSync as $field) {
                        if ($item->$field !== $yesterdayItem->$field) {
                            $updateData[$field] = $yesterdayItem->$field;
                            $needUpdate = true;
                        }
                    }

                    if ($item->stock_awal != $yesterdayItem->soh) {
                        $updateData['stock_awal'] = $yesterdayItem->soh;
                        $needUpdate = true;
                    }

                    if ($needUpdate) {
                        $item->update($updateData);
                        if (isset($updateData['stock_awal'])) {
                            $totalProduksi = ($item->produksi_shift1 ?? 0) + ($item->produksi_shift2 ?? 0) + ($item->produksi_shift3 ?? 0);
                            $totalOut = ($item->out_shift1 ?? 0) + ($item->out_shift2 ?? 0) + ($item->out_shift3 ?? 0);
                            $newSoh = $updateData['stock_awal'] + $totalProduksi - $totalOut;
                            $item->update(['soh' => $newSoh]);
                            $item->soh = $newSoh;
                        }
                        $item->refresh();
                    }
                }
                return $this->formatStockData($item);
            });
        }

        return $stockData;
    }

    private function formatStockData($item)
    {
        return [
            'id' => $item->id,
            'bl_type' => $item->bl_type,
            'sap_finish' => $item->sap_finish,
            'id_sap' => $item->id_sap,
            'material_name' => $item->material_name,
            'part_no' => $item->part_no,
            'part_name' => $item->part_name,
            'type' => $item->type,
            'qty_unit' => $item->qty_unit,
            'qty_day' => $item->qty_day,
            'stock_date' => $item->stock_date,
            'stock_awal' => $item->stock_awal,
            'produksi_shift1' => $item->produksi_shift1,
            'produksi_shift2' => $item->produksi_shift2,
            'produksi_shift3' => $item->produksi_shift3,
            'out_shift1' => $item->out_shift1,
            'out_shift2' => $item->out_shift2,
            'out_shift3' => $item->out_shift3,
            'ng_shift1' => $item->ng_shift1,
            'ng_shift2' => $item->ng_shift2,
            'total_produksi' => $item->total_produksi,
            'total_out' => $item->total_out,
            'soh' => $item->soh,
        ];
    }

    private function getOutputData($date)
{
    $outputData = OutputProduct::where('stock_date', $date)
        ->orderBy('id')
        ->get();

    if ($outputData->isEmpty()) {
        $yesterday = Carbon::parse($date)->subDay()->format('Y-m-d');
        $yesterdayOutput = OutputProduct::where('stock_date', $yesterday)
            ->orderBy('id')
            ->get();

        if ($yesterdayOutput->isNotEmpty()) {
            $newRecords = [];
            foreach ($yesterdayOutput as $item) {
                // Create new record with yesterday's master data, reset all shifts
                $newRecord = OutputProduct::create([
                    'type' => $item->type,
                    'penanggung_jawab' => $item->penanggung_jawab,
                    'sap_no' => $item->sap_no,
                    'product_unit' => $item->product_unit,
                    'qty_day' => $item->qty_day,
                    'stock_date' => $date,
                    'out_shift1' => 0,
                    'out_shift2' => 0,
                    'out_shift3' => 0,
                    'ng_shift1' => 0,
                    'ng_shift2' => 0,
                    'ng_shift3' => 0,
                ]);

                // ✅ COPY BOM MATERIALS dari kemarin
                $yesterdayMaterials = $item->materials()->get();

                if ($yesterdayMaterials->isNotEmpty()) {
                    foreach ($yesterdayMaterials as $material) {
                        $newRecord->materials()->create([
                            'sap_no' => $material->sap_no,
                            'qty_per_unit' => $material->qty_per_unit,
                        ]);
                    }

                    Log::info('📦 BOM copied to new day', [
                        'from_output_id' => $item->id,
                        'to_output_id' => $newRecord->id,
                        'product_unit' => $newRecord->product_unit,
                        'materials_count' => $yesterdayMaterials->count(),
                        'from_date' => $yesterday,
                        'to_date' => $date,
                    ]);
                }

                $newRecords[] = $this->formatOutputData($newRecord);
            }
            return collect($newRecords);
        } else {
            return collect([]);
        }
    } else {
        return $outputData->map(function ($item) {
            return $this->formatOutputData($item);
        });
    }
}

    private function formatOutputData($item)
    {
        return [
            'id' => $item->id,
            'type' => $item->type,
            'penanggung_jawab' => $item->penanggung_jawab,
            'sap_no' => $item->sap_no,
            'product_unit' => $item->product_unit,
            'qty_day' => $item->qty_day,
            'stock_date' => $item->stock_date,
            'out_shift1' => $item->out_shift1,
            'out_shift2' => $item->out_shift2,
            'out_shift3' => $item->out_shift3 ?? 0,
            'ng_shift1' => $item->ng_shift1,
            'ng_shift2' => $item->ng_shift2,
            'ng_shift3' => $item->ng_shift3 ?? 0,
            'total' => ($item->out_shift1 ?? 0) + ($item->out_shift2 ?? 0) + ($item->out_shift3 ?? 0),
        ];
    }

    public function deleteMultiple(Request $request)
    {
        try {
            $validated = $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'required|integer|exists:daily_stocks,id',
                'bl_type' => 'required|in:BL1,BL2',
            ]);

            $deleted = DailyStock::whereIn('id', $validated['ids'])
                ->where('bl_type', $validated['bl_type'])
                ->delete();

            return response()->json([
                'success' => true,
                'deleted' => $deleted,
                'message' => "Berhasil menghapus {$deleted} data"
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteAllByDate(Request $request)
    {
        try {
            $validated = $request->validate([
                'date' => 'required|date',
                'bl_type' => 'required|in:BL1,BL2',
                'confirmation' => 'required|string',
            ]);

            if ($validated['confirmation'] !== $validated['bl_type']) {
                return response()->json(['success' => false, 'message' => 'Konfirmasi tidak sesuai'], 422);
            }

            $deleted = DailyStock::where('stock_date', $validated['date'])
                ->where('bl_type', $validated['bl_type'])
                ->delete();

            return response()->json([
                'success' => true,
                'deleted' => $deleted,
                'message' => "Berhasil menghapus {$deleted} data {$validated['bl_type']}"
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteMultipleOutput(Request $request)
    {
        try {
            $validated = $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'required|integer|exists:output_products,id',
            ]);

            $deleted = OutputProduct::whereIn('id', $validated['ids'])->delete();

            return response()->json([
                'success' => true,
                'deleted' => $deleted,
                'message' => "Berhasil menghapus {$deleted} output products"
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function output(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));
        $outputData = $this->getOutputData($date);

        return Inertia::render('Output/Index', [
            'outputData' => $outputData,
            'selectedDate' => $date,
        ]);
    }

    public function update(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'nullable',
                'bl_type' => 'required|in:BL1,BL2',
                'sap_finish' => 'nullable|string',
                'id_sap' => 'nullable|string',
                'material_name' => 'nullable|string',
                'part_no' => 'nullable|string',
                'part_name' => 'nullable|string',
                'type' => 'nullable|string',
                'qty_unit' => 'nullable|integer|min:0',
                'qty_day' => 'nullable|integer|min:0',
                'stock_date' => 'required|date',
                'stock_awal' => 'nullable|integer',
                'produksi_shift1' => 'nullable|integer|min:0',
                'produksi_shift2' => 'nullable|integer|min:0',
                'produksi_shift3' => 'nullable|integer|min:0',
                'out_shift1' => 'nullable|integer|min:0',
                'out_shift2' => 'nullable|integer|min:0',
                'out_shift3' => 'nullable|integer|min:0',
                'ng_shift1' => 'nullable|integer|min:0',
                'ng_shift2' => 'nullable|integer|min:0',
                'ng_shift3' => 'nullable|integer|min:0',
            ]);

            $data = [
                'sap_finish' => $validated['sap_finish'] ?? '',
                'id_sap' => $validated['id_sap'] ?? '',
                'material_name' => $validated['material_name'] ?? '',
                'part_no' => $validated['part_no'] ?? '',
                'part_name' => $validated['part_name'] ?? '',
                'type' => $validated['type'] ?? '',
                'qty_unit' => $validated['qty_unit'] ?? 0,
                'qty_day' => $validated['qty_day'] ?? 0,
                'stock_date' => $validated['stock_date'],
                'stock_awal' => $validated['stock_awal'] ?? 0,
                'produksi_shift1' => $validated['produksi_shift1'] ?? 0,
                'produksi_shift2' => $validated['produksi_shift2'] ?? 0,
                'produksi_shift3' => $validated['produksi_shift3'] ?? 0,
                'out_shift1' => $validated['out_shift1'] ?? 0,
                'out_shift2' => $validated['out_shift2'] ?? 0,
                'out_shift3' => $validated['out_shift3'] ?? 0,
                'ng_shift1' => $validated['ng_shift1'] ?? 0,
                'ng_shift2' => $validated['ng_shift2'] ?? 0,
                'ng_shift3' => $validated['ng_shift3'] ?? 0
            ];

            $data['total_produksi'] = $data['produksi_shift1'] + $data['produksi_shift2'] + $data['produksi_shift3'];
            $data['total_out'] = $data['out_shift1'] + $data['out_shift2'] + $data['out_shift3'];
            $data['soh'] = $data['stock_awal'] + $data['total_produksi'] - $data['total_out'];

            if (!empty($validated['id']) && is_numeric($validated['id'])) {
                $stock = DailyStock::find($validated['id']);
                if ($stock) {
                    if ($stock->bl_type !== $validated['bl_type']) {
                        return response()->json(['success' => false, 'message' => 'Cannot change bl_type'], 422);
                    }
                    $stock->update($data);
                } else {
                    $data['bl_type'] = $validated['bl_type'];
                    $stock = DailyStock::create($data);
                }
            } else {
                $data['bl_type'] = $validated['bl_type'];
                $stock = DailyStock::create($data);
            }

            return response()->json(['success' => true, 'data' => $stock->fresh()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteAllOutputByDate(Request $request)
    {
        try {
            $validated = $request->validate([
                'date' => 'required|date',
                'confirmation' => 'required|string',
            ]);

            if ($validated['confirmation'] !== 'OUTPUT') {
                return response()->json(['success' => false, 'message' => 'Konfirmasi tidak sesuai'], 422);
            }

            $deleted = OutputProduct::where('stock_date', $validated['date'])->delete();

            return response()->json([
                'success' => true,
                'deleted' => $deleted,
                'message' => "Berhasil menghapus {$deleted} output products"
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteRow($id)
    {
        try {
            DailyStock::destroy($id);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Output Product with real-time sync to Control Stock
     */
    public function updateOutput(Request $request)
{
    try {
        $validated = $request->validate([
            'id' => 'nullable',
            'type' => 'nullable|string',
            'penanggung_jawab' => 'nullable|string',
            'sap_no' => 'nullable|string',
            'product_unit' => 'nullable|string',
            'qty_day' => 'nullable|integer',
            'stock_date' => 'required|date',
            'out_shift1' => 'nullable|integer|min:0',
            'out_shift2' => 'nullable|integer|min:0',
            'out_shift3' => 'nullable|integer|min:0',
            'ng_shift1' => 'nullable|integer|min:0',
            'ng_shift2' => 'nullable|integer|min:0',
            'ng_shift3' => 'nullable|integer|min:0',
        ]);

        $data = [
            'type' => $validated['type'] ?? '',
            'penanggung_jawab' => $validated['penanggung_jawab'] ?? '',
            'sap_no' => $validated['sap_no'] ?? '',
            'product_unit' => $validated['product_unit'] ?? '',
            'qty_day' => $validated['qty_day'] ?? 0,
            'stock_date' => $validated['stock_date'],
            'out_shift1' => $validated['out_shift1'] ?? 0,
            'out_shift2' => $validated['out_shift2'] ?? 0,
            'out_shift3' => $validated['out_shift3'] ?? 0,
            'ng_shift1' => $validated['ng_shift1'] ?? 0,
            'ng_shift2' => $validated['ng_shift2'] ?? 0,
            'ng_shift3' => $validated['ng_shift3'] ?? 0,
        ];

        $oldOutputData = null;
        if (!empty($validated['id']) && is_numeric($validated['id'])) {
            $output = OutputProduct::find($validated['id']);
            if ($output) {
                // ✅ CRITICAL FIX: Simpan OLD data SEBELUM di-update
                $oldOutputData = [
                    'out_shift1' => $output->out_shift1,
                    'out_shift2' => $output->out_shift2,
                    'out_shift3' => $output->out_shift3 ?? 0,
                ];

                // Update output product
                $output->update($data);

                // Reload to get fresh data
                $output->refresh();
            } else {
                $output = OutputProduct::create($data);
            }
        } else {
            $output = OutputProduct::create($data);
        }

        // SYNC TO CONTROL STOCK (Real-time)
        $this->syncOutputToControlStock($output, $oldOutputData);

        return response()->json(['success' => true, 'data' => $output]);
    } catch (\Exception $e) {
        Log::error('Update output error', ['message' => $e->getMessage()]);
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

private function syncOutputToControlStock(OutputProduct $output, $oldOutputData = null)
{
    Log::info('🔵 START syncOutputToControlStock', [
        'output_id' => $output->id,
        'product_unit' => $output->product_unit,
        'stock_date' => $output->stock_date,
        'new_shifts' => [
            'shift1' => $output->out_shift1,
            'shift2' => $output->out_shift2,
            'shift3' => $output->out_shift3,
        ],
        'old_shifts' => $oldOutputData
    ]);

    $output->load('materials');

    Log::info('📦 Materials loaded', [
        'materials_count' => $output->materials->count(),
        'materials' => $output->materials->map(function($m) {
            return [
                'sap_no' => $m->sap_no,
                'qty_per_unit' => $m->qty_per_unit
            ];
        })->toArray()
    ]);

    if ($output->materials->isEmpty()) {
        Log::warning('⚠️ No materials defined for output product', [
            'output_id' => $output->id,
            'product_unit' => $output->product_unit
        ]);
        return;
    }

    // Calculate delta
    $deltaShift1 = ($output->out_shift1 ?? 0) - ($oldOutputData['out_shift1'] ?? 0);
    $deltaShift2 = ($output->out_shift2 ?? 0) - ($oldOutputData['out_shift2'] ?? 0);
    $deltaShift3 = ($output->out_shift3 ?? 0) - ($oldOutputData['out_shift3'] ?? 0);

    Log::info('📊 Delta calculated', [
        'current_values' => [
            'shift1' => $output->out_shift1,
            'shift2' => $output->out_shift2,
            'shift3' => $output->out_shift3,
        ],
        'old_values' => $oldOutputData,
        'deltas' => [
            'deltaShift1' => $deltaShift1,
            'deltaShift2' => $deltaShift2,
            'deltaShift3' => $deltaShift3,
        ],
    ]);

    // Loop setiap material yang dibutuhkan
    foreach ($output->materials as $index => $material) {
        Log::info("🔍 Processing material #{$index}", [
            'material_id' => $material->id,
            'sap_no' => $material->sap_no,
            'qty_per_unit_from_bom' => $material->qty_per_unit,
        ]);

        // Find matching stock in BL1 or BL2
        $matchingStocks = DailyStock::where('stock_date', $output->stock_date)
            ->where(function($query) use ($material) {
                $query->where('id_sap', $material->sap_no)
                      ->orWhere('sap_finish', $material->sap_no);
            })
            ->get();

        Log::info("📋 Matching stocks found", [
            'sap_no' => $material->sap_no,
            'count' => $matchingStocks->count(),
            'stock_ids' => $matchingStocks->pluck('id')->toArray(),
            'bl_types' => $matchingStocks->pluck('bl_type')->toArray(),
        ]);

        if ($matchingStocks->isEmpty()) {
            Log::warning('❌ No matching stock found', [
                'sap_no' => $material->sap_no,
                'output_id' => $output->id,
                'product' => $output->product_unit,
                'stock_date' => $output->stock_date,
            ]);
            continue;
        }

        foreach ($matchingStocks as $stockIndex => $stock) {
            // ✅ Gunakan qty_per_unit dari BOM, bukan dari Control Stock
            $qtyPerUnit = $material->qty_per_unit;

            Log::info("📐 Before update - Stock state", [
                'stock_id' => $stock->id,
                'bl_type' => $stock->bl_type,
                'current_out_shifts' => [
                    'out_shift1' => $stock->out_shift1,
                    'out_shift2' => $stock->out_shift2,
                    'out_shift3' => $stock->out_shift3,
                ],
                'qty_per_unit_from_bom' => $qtyPerUnit,
                'delta_to_apply' => [
                    'shift1' => $deltaShift1 * $qtyPerUnit,
                    'shift2' => $deltaShift2 * $qtyPerUnit,
                    'shift3' => $deltaShift3 * $qtyPerUnit,
                ]
            ]);

            $newOutShift1 = $stock->out_shift1 + ($deltaShift1 * $qtyPerUnit);
            $newOutShift2 = $stock->out_shift2 + ($deltaShift2 * $qtyPerUnit);
            $newOutShift3 = $stock->out_shift3 + ($deltaShift3 * $qtyPerUnit);

            $totalOut = $newOutShift1 + $newOutShift2 + $newOutShift3;
            $totalProduksi = ($stock->produksi_shift1 ?? 0) + ($stock->produksi_shift2 ?? 0) + ($stock->produksi_shift3 ?? 0);
            $newSoh = ($stock->stock_awal ?? 0) + $totalProduksi - $totalOut;

            $updateResult = $stock->update([
                'out_shift1' => max(0, $newOutShift1),
                'out_shift2' => max(0, $newOutShift2),
                'out_shift3' => max(0, $newOutShift3),
                'total_out' => $totalOut,
                'total_produksi' => $totalProduksi,
                'soh' => $newSoh,
            ]);

            Log::info("✅ After update - Stock state", [
                'stock_id' => $stock->id,
                'bl_type' => $stock->bl_type,
                'new_out_shifts' => [
                    'out_shift1' => max(0, $newOutShift1),
                    'out_shift2' => max(0, $newOutShift2),
                    'out_shift3' => max(0, $newOutShift3),
                ],
                'total_out' => $totalOut,
                'soh' => $newSoh,
                'update_success' => $updateResult,
            ]);
        }
    }

    Log::info('🔵 END syncOutputToControlStock');
}

    public function deleteOutput($id)
    {
        try {
            $output = OutputProduct::find($id);
            if ($output) {
                // Before delete, reverse the OUT from control stock
                $this->syncOutputToControlStock($output, [
                    'out_shift1' => $output->out_shift1,
                    'out_shift2' => $output->out_shift2,
                    'out_shift3' => $output->out_shift3 ?? 0,
                    'qty_day' => $output->qty_day,
                    'sap_no' => $output->sap_no,
                ]);

                $output->delete();
            }
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
 * Get materials for an output product
 */
public function getOutputMaterials(OutputProduct $outputProduct)
{
    $materials = $outputProduct->materials()->get()->map(function($material) use ($outputProduct) {
        // Get qty_unit from Control Stock
        $stock = DailyStock::where('stock_date', $outputProduct->stock_date)
            ->where(function($query) use ($material) {
                $query->where('id_sap', $material->sap_no)
                      ->orWhere('sap_finish', $material->sap_no);
            })
            ->first();

        return [
            'id' => $material->id,
            'sap_no' => $material->sap_no,
            'qty_per_unit' => $material->qty_per_unit,
            'qty_unit_reference' => $stock ? $stock->qty_unit : 0,
            'material_name' => $stock ? $stock->material_name : '-',
            'bl_type' => $stock ? $stock->bl_type : '-',
        ];
    });

    return response()->json([
        'success' => true,
        'materials' => $materials,
    ]);
}

public function updateOutputMaterials(Request $request, OutputProduct $outputProduct)
{
    try {
        $validated = $request->validate([
            'materials' => 'required|array',
            'materials.*.sap_no' => 'required|string',
            'materials.*.qty_per_unit' => 'required|integer|min:1',
        ]);

        Log::info('🔧 START updateOutputMaterials', [
            'output_id' => $outputProduct->id,
            'product_unit' => $outputProduct->product_unit,
            'stock_date' => $outputProduct->stock_date,
            'current_output_shifts' => [
                'shift1' => $outputProduct->out_shift1,
                'shift2' => $outputProduct->out_shift2,
                'shift3' => $outputProduct->out_shift3,
            ],
            'new_materials_count' => count($validated['materials']),
        ]);

        // ✅ STEP 1: Get OLD materials BEFORE delete (untuk reverse calculation)
        $oldMaterials = $outputProduct->materials()->get()->map(function($m) {
            return [
                'sap_no' => $m->sap_no,
                'qty_per_unit' => $m->qty_per_unit,
            ];
        })->toArray();

        Log::info('📦 Old materials before delete', [
            'count' => count($oldMaterials),
            'materials' => $oldMaterials,
        ]);

        // ✅ STEP 2: REVERSE old materials effect from Control Stock
        // Treat as if we're deleting the output (set all OUT to 0)
        foreach ($oldMaterials as $oldMaterial) {
            $this->reverseMaterialFromControlStock(
                $outputProduct,
                $oldMaterial['sap_no'],
                $oldMaterial['qty_per_unit']
            );
        }

        // ✅ STEP 3: Delete existing materials
        $outputProduct->materials()->delete();

        // ✅ STEP 4: Insert new materials
        foreach ($validated['materials'] as $material) {
            $outputProduct->materials()->create([
                'sap_no' => $material['sap_no'],
                'qty_per_unit' => $material['qty_per_unit'],
            ]);
        }

        Log::info('📦 New materials inserted', [
            'count' => count($validated['materials']),
            'materials' => $validated['materials'],
        ]);

        // ✅ STEP 5: RE-APPLY new materials effect to Control Stock
        // Treat as if we're creating new output with current OUT values
        $this->applyMaterialsToControlStock($outputProduct);

        Log::info('✅ END updateOutputMaterials - Success');

        return response()->json([
            'success' => true,
            'message' => 'Materials updated and Control Stock synced successfully',
            'count' => count($validated['materials']),
        ]);
    } catch (\Exception $e) {
        Log::error('❌ Update materials error', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}

private function reverseMaterialFromControlStock(OutputProduct $output, string $sapNo, int $qtyPerUnit)
{
    Log::info('🔙 Reversing material from Control Stock', [
        'sap_no' => $sapNo,
        'qty_per_unit' => $qtyPerUnit,
        'output_shifts' => [
            'shift1' => $output->out_shift1,
            'shift2' => $output->out_shift2,
            'shift3' => $output->out_shift3,
        ],
    ]);

    $matchingStocks = DailyStock::where('stock_date', $output->stock_date)
        ->where(function($query) use ($sapNo) {
            $query->where('id_sap', $sapNo)
                  ->orWhere('sap_finish', $sapNo);
        })
        ->get();

    if ($matchingStocks->isEmpty()) {
        Log::warning('⚠️ No matching stock to reverse', ['sap_no' => $sapNo]);
        return;
    }

    foreach ($matchingStocks as $stock) {
        // Subtract the OLD qty_per_unit effect
        $reverseShift1 = $output->out_shift1 * $qtyPerUnit;
        $reverseShift2 = $output->out_shift2 * $qtyPerUnit;
        $reverseShift3 = ($output->out_shift3 ?? 0) * $qtyPerUnit;

        $newOutShift1 = max(0, $stock->out_shift1 - $reverseShift1);
        $newOutShift2 = max(0, $stock->out_shift2 - $reverseShift2);
        $newOutShift3 = max(0, $stock->out_shift3 - $reverseShift3);

        $totalOut = $newOutShift1 + $newOutShift2 + $newOutShift3;
        $totalProduksi = ($stock->produksi_shift1 ?? 0) + ($stock->produksi_shift2 ?? 0) + ($stock->produksi_shift3 ?? 0);
        $newSoh = ($stock->stock_awal ?? 0) + $totalProduksi - $totalOut;

        $stock->update([
            'out_shift1' => $newOutShift1,
            'out_shift2' => $newOutShift2,
            'out_shift3' => $newOutShift3,
            'total_out' => $totalOut,
            'soh' => $newSoh,
        ]);

        Log::info('✅ Reversed from Control Stock', [
            'stock_id' => $stock->id,
            'bl_type' => $stock->bl_type,
            'reversed_amounts' => [
                'shift1' => $reverseShift1,
                'shift2' => $reverseShift2,
                'shift3' => $reverseShift3,
            ],
            'new_out_values' => [
                'out_shift1' => $newOutShift1,
                'out_shift2' => $newOutShift2,
                'out_shift3' => $newOutShift3,
            ],
            'new_soh' => $newSoh,
        ]);
    }
}

private function applyMaterialsToControlStock(OutputProduct $output)
{
    Log::info('➕ Applying materials to Control Stock', [
        'output_id' => $output->id,
        'product_unit' => $output->product_unit,
    ]);

    $output->load('materials');

    if ($output->materials->isEmpty()) {
        Log::warning('⚠️ No materials to apply');
        return;
    }

    foreach ($output->materials as $material) {
        $matchingStocks = DailyStock::where('stock_date', $output->stock_date)
            ->where(function($query) use ($material) {
                $query->where('id_sap', $material->sap_no)
                      ->orWhere('sap_finish', $material->sap_no);
            })
            ->get();

        if ($matchingStocks->isEmpty()) {
            Log::warning('⚠️ No matching stock to apply', ['sap_no' => $material->sap_no]);
            continue;
        }

        foreach ($matchingStocks as $stock) {
            // Add the NEW qty_per_unit effect
            $applyShift1 = $output->out_shift1 * $material->qty_per_unit;
            $applyShift2 = $output->out_shift2 * $material->qty_per_unit;
            $applyShift3 = ($output->out_shift3 ?? 0) * $material->qty_per_unit;

            $newOutShift1 = $stock->out_shift1 + $applyShift1;
            $newOutShift2 = $stock->out_shift2 + $applyShift2;
            $newOutShift3 = $stock->out_shift3 + $applyShift3;

            $totalOut = $newOutShift1 + $newOutShift2 + $newOutShift3;
            $totalProduksi = ($stock->produksi_shift1 ?? 0) + ($stock->produksi_shift2 ?? 0) + ($stock->produksi_shift3 ?? 0);
            $newSoh = ($stock->stock_awal ?? 0) + $totalProduksi - $totalOut;

            $stock->update([
                'out_shift1' => $newOutShift1,
                'out_shift2' => $newOutShift2,
                'out_shift3' => $newOutShift3,
                'total_out' => $totalOut,
                'soh' => $newSoh,
            ]);

            Log::info('✅ Applied to Control Stock', [
                'stock_id' => $stock->id,
                'bl_type' => $stock->bl_type,
                'sap_no' => $material->sap_no,
                'qty_per_unit' => $material->qty_per_unit,
                'applied_amounts' => [
                    'shift1' => $applyShift1,
                    'shift2' => $applyShift2,
                    'shift3' => $applyShift3,
                ],
                'new_out_values' => [
                    'out_shift1' => $newOutShift1,
                    'out_shift2' => $newOutShift2,
                    'out_shift3' => $newOutShift3,
                ],
                'new_soh' => $newSoh,
            ]);
        }
    }
}

public function getAvailableMaterials(Request $request)
{
    $date = $request->input('date', now()->format('Y-m-d'));
    $search = $request->input('search', '');

    $materials = DailyStock::where('stock_date', $date)
        ->where(function($query) use ($search) {
            if ($search) {
                $query->where('id_sap', 'like', "%{$search}%")
                      ->orWhere('sap_finish', 'like', "%{$search}%")
                      ->orWhere('material_name', 'like', "%{$search}%");
            }
        })
        ->select('id_sap', 'sap_finish', 'material_name', 'qty_unit', 'bl_type')
        ->distinct()
        ->limit(50)
        ->get()
        ->map(function($item) {
            return [
                'sap_no' => $item->id_sap ?: $item->sap_finish,
                'material_name' => $item->material_name,
                'qty_unit' => $item->qty_unit,
                'bl_type' => $item->bl_type,
            ];
        });

    return response()->json([
        'success' => true,
        'materials' => $materials,
    ]);
}
}
