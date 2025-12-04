<?php

namespace App\Http\Controllers;

use App\Models\DailyStock;
use App\Models\OutputProduct;
use App\Models\MonthlyForecast;
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
                        'ng_unit' => 0,
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
                            $ngUnit = $item->ng_unit ?? 0;
                            $newSoh = $updateData['stock_awal'] + $totalProduksi - $totalOut + $ngUnit;
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
            'ng_unit' => $item->ng_unit ?? 0,
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

                $yesterdayMaterials = $item->materials()->get();

                if ($yesterdayMaterials->isNotEmpty()) {
                    foreach ($yesterdayMaterials as $material) {
                        $newRecord->materials()->create([
                            'sap_no' => $material->sap_no,
                            'qty_per_unit' => $material->qty_per_unit,
                        ]);
                    }
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
                'ng_unit' => 'nullable|integer|min:0',
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
                'ng_unit' => $validated['ng_unit'] ?? 0,
            ];

            $data['total_produksi'] = $data['produksi_shift1'] + $data['produksi_shift2'] + $data['produksi_shift3'];
            $data['total_out'] = $data['out_shift1'] + $data['out_shift2'] + $data['out_shift3'];
            $data['soh'] = $data['stock_awal'] + $data['total_produksi'] - $data['total_out'] + $data['ng_unit'];

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
                    $oldOutputData = [
                        'out_shift1' => $output->out_shift1,
                        'out_shift2' => $output->out_shift2,
                        'out_shift3' => $output->out_shift3 ?? 0,
                        'ng_shift1' => $output->ng_shift1 ?? 0,
                        'ng_shift2' => $output->ng_shift2 ?? 0,
                        'ng_shift3' => $output->ng_shift3 ?? 0,
                    ];

                    $output->update($data);
                    $output->refresh();
                } else {
                    $output = OutputProduct::create($data);
                }
            } else {
                $output = OutputProduct::create($data);
            }

            $this->syncOutputToControlStock($output, $oldOutputData);

            return response()->json(['success' => true, 'data' => $output]);
        } catch (\Exception $e) {
            Log::error('Update output error', ['message' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

private function syncOutputToControlStock(OutputProduct $output, $oldOutputData = null)
{
    $output->load('materials');

    if ($output->materials->isEmpty()) {
        Log::warning('⚠️ No materials defined for output product');
        return;
    }

    $deltaOutShift1 = ($output->out_shift1 ?? 0) - ($oldOutputData['out_shift1'] ?? 0);
    $deltaOutShift2 = ($output->out_shift2 ?? 0) - ($oldOutputData['out_shift2'] ?? 0);
    $deltaOutShift3 = ($output->out_shift3 ?? 0) - ($oldOutputData['out_shift3'] ?? 0);

    $oldTotalNg = ($oldOutputData['ng_shift1'] ?? 0) +
                  ($oldOutputData['ng_shift2'] ?? 0) +
                  ($oldOutputData['ng_shift3'] ?? 0);

    $newTotalNg = ($output->ng_shift1 ?? 0) +
                  ($output->ng_shift2 ?? 0) +
                  ($output->ng_shift3 ?? 0);

    $deltaNgUnit = $newTotalNg - $oldTotalNg;

    foreach ($output->materials as $material) {
        $matchingStocks = DailyStock::where('stock_date', $output->stock_date)
            ->where(function($query) use ($material) {
                $query->where('id_sap', $material->sap_no)
                      ->orWhere('sap_finish', $material->sap_no);
            })
            ->get();

        if ($matchingStocks->isEmpty()) {
            Log::warning('❌ No matching stock found', ['sap_no' => $material->sap_no]);
            continue;
        }

        foreach ($matchingStocks as $stock) {
            $qtyPerUnit = $material->qty_per_unit;

            $newOutShift1 = $stock->out_shift1 + ($deltaOutShift1 * $qtyPerUnit);
            $newOutShift2 = $stock->out_shift2 + ($deltaOutShift2 * $qtyPerUnit);
            $newOutShift3 = $stock->out_shift3 + ($deltaOutShift3 * $qtyPerUnit);

            $newNgUnit = $stock->ng_unit + ($deltaNgUnit * $qtyPerUnit);

            $totalOut = $newOutShift1 + $newOutShift2 + $newOutShift3;
            $totalProduksi = ($stock->produksi_shift1 ?? 0) +
                           ($stock->produksi_shift2 ?? 0) +
                           ($stock->produksi_shift3 ?? 0);

            $newSoh = ($stock->stock_awal ?? 0) + $totalProduksi - $totalOut + $newNgUnit;

            $stock->update([
                'out_shift1' => max(0, $newOutShift1),
                'out_shift2' => max(0, $newOutShift2),
                'out_shift3' => max(0, $newOutShift3),
                'ng_unit' => max(0, $newNgUnit),
                'total_out' => $totalOut,
                'soh' => $newSoh,
            ]);
        }
    }
}


    public function deleteOutput($id)
    {
        try {
            $output = OutputProduct::find($id);
            if ($output) {
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

public function getOutputMaterials(OutputProduct $outputProduct)
{
    $materials = $outputProduct->materials()->get()->map(function($material) use ($outputProduct) {
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

        $oldMaterials = $outputProduct->materials()->get()->map(function($m) {
            return [
                'sap_no' => $m->sap_no,
                'qty_per_unit' => $m->qty_per_unit,
            ];
        })->toArray();

        foreach ($oldMaterials as $oldMaterial) {
            $this->reverseMaterialFromControlStock(
                $outputProduct,
                $oldMaterial['sap_no'],
                $oldMaterial['qty_per_unit']
            );
        }

        $outputProduct->materials()->delete();

        foreach ($validated['materials'] as $material) {
            $outputProduct->materials()->create([
                'sap_no' => $material['sap_no'],
                'qty_per_unit' => $material['qty_per_unit'],
            ]);
        }

        $this->applyMaterialsToControlStock($outputProduct);

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

    $matchingStocks = DailyStock::where('stock_date', $output->stock_date)
        ->where(function($query) use ($sapNo) {
            $query->where('id_sap', $sapNo)
                  ->orWhere('sap_finish', $sapNo);
        })
        ->get();

    if ($matchingStocks->isEmpty()) {
        Log::warning('⚠️ No matching stock to reverse');
        return;
    }

    foreach ($matchingStocks as $stock) {
        $reverseOutShift1 = $output->out_shift1 * $qtyPerUnit;
        $reverseOutShift2 = $output->out_shift2 * $qtyPerUnit;
        $reverseOutShift3 = ($output->out_shift3 ?? 0) * $qtyPerUnit;

        $totalNg = ($output->ng_shift1 ?? 0) +
                   ($output->ng_shift2 ?? 0) +
                   ($output->ng_shift3 ?? 0);
        $reverseNgUnit = $totalNg * $qtyPerUnit;

        $newOutShift1 = max(0, $stock->out_shift1 - $reverseOutShift1);
        $newOutShift2 = max(0, $stock->out_shift2 - $reverseOutShift2);
        $newOutShift3 = max(0, $stock->out_shift3 - $reverseOutShift3);
        $newNgUnit = max(0, $stock->ng_unit - $reverseNgUnit);

        $totalOut = $newOutShift1 + $newOutShift2 + $newOutShift3;
        $totalProduksi = ($stock->produksi_shift1 ?? 0) +
                       ($stock->produksi_shift2 ?? 0) +
                       ($stock->produksi_shift3 ?? 0);
        $newSoh = ($stock->stock_awal ?? 0) + $totalProduksi - $totalOut + $newNgUnit;

        $stock->update([
            'out_shift1' => $newOutShift1,
            'out_shift2' => $newOutShift2,
            'out_shift3' => $newOutShift3,
            'ng_unit' => $newNgUnit,
            'total_out' => $totalOut,
            'soh' => $newSoh,
        ]);
    }
}

private function applyMaterialsToControlStock(OutputProduct $output)
{
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
            $applyOutShift1 = $output->out_shift1 * $material->qty_per_unit;
            $applyOutShift2 = $output->out_shift2 * $material->qty_per_unit;
            $applyOutShift3 = ($output->out_shift3 ?? 0) * $material->qty_per_unit;

            $totalNg = ($output->ng_shift1 ?? 0) +
                       ($output->ng_shift2 ?? 0) +
                       ($output->ng_shift3 ?? 0);
            $applyNgUnit = $totalNg * $material->qty_per_unit;

            $newOutShift1 = $stock->out_shift1 + $applyOutShift1;
            $newOutShift2 = $stock->out_shift2 + $applyOutShift2;
            $newOutShift3 = $stock->out_shift3 + $applyOutShift3;
            $newNgUnit = $stock->ng_unit + $applyNgUnit;

            $totalOut = $newOutShift1 + $newOutShift2 + $newOutShift3;
            $totalProduksi = ($stock->produksi_shift1 ?? 0) +
                           ($stock->produksi_shift2 ?? 0) +
                           ($stock->produksi_shift3 ?? 0);
            $newSoh = ($stock->stock_awal ?? 0) + $totalProduksi - $totalOut + $newNgUnit;

            $stock->update([
                'out_shift1' => $newOutShift1,
                'out_shift2' => $newOutShift2,
                'out_shift3' => $newOutShift3,
                'ng_unit' => $newNgUnit,
                'total_out' => $totalOut,
                'soh' => $newSoh,
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

    public function forecastIndex(Request $request)
{
    $year = $request->input('year', now()->year);
    $month = $request->input('month', now()->month);

    $forecasts = MonthlyForecast::forMonth($year, $month)
        ->orderBy('type')
        ->orderBy('sap_no')
        ->get();

    // Get unique SAP numbers from Output Products
    $availableSapNumbers = OutputProduct::select('sap_no', 'product_unit', 'type')
        ->distinct()
        ->whereNotNull('sap_no')
        ->where('sap_no', '!=', '')
        ->orderBy('type')
        ->orderBy('sap_no')
        ->get();

    return Inertia::render('Forecast/Index', [
        'forecasts' => $forecasts,
        'availableSapNumbers' => $availableSapNumbers,
        'selectedYear' => $year,
        'selectedMonth' => $month,
        'months' => [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ],
    ]);
}

/**
 * Update or create forecast
 * Alur: Forecast → Output Products → Control Stock (via BOM)
 */
public function forecastUpdate(Request $request)
{
    try {
        $validated = $request->validate([
            'id' => 'nullable|integer',
            'sap_no' => 'required|string',
            'product_unit' => 'nullable|string',
            'part_name' => 'nullable|string',
            'type' => 'nullable|string',
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'forecast_qty' => 'required|integer|min:0',
            'working_days' => 'required|integer|min:1|max:31',
        ]);

        // Create or update forecast
        $forecast = MonthlyForecast::updateOrCreate(
            [
                'sap_no' => $validated['sap_no'],
                'year' => $validated['year'],
                'month' => $validated['month'],
            ],
            [
                'product_unit' => $validated['product_unit'] ?? '',
                'part_name' => $validated['part_name'] ?? '',
                'type' => $validated['type'] ?? '',
                'forecast_qty' => $validated['forecast_qty'],
                'working_days' => $validated['working_days'],
            ]
        );

        Log::info('📊 Forecast created/updated', [
            'sap_no' => $forecast->sap_no,
            'forecast_qty' => $forecast->forecast_qty,
            'working_days' => $forecast->working_days,
            'qty_per_day' => $forecast->qty_per_day,
        ]);

        // STEP 1: Sync forecast ke Output Products (Product level)
        $outputSyncResult = $this->syncForecastToOutputProducts($forecast);

        // STEP 2: Sync dari Output Products ke Control Stock (Material level via BOM)
        $stockSyncResult = $this->syncOutputProductToControlStockViaBOM($forecast);

        return response()->json([
            'success' => true,
            'data' => $forecast->fresh(),
            'sync_results' => [
                'output_products' => $outputSyncResult,
                'control_stock' => $stockSyncResult,
            ],
            'message' => "Forecast updated successfully!\n" .
                        "Output Products synced: {$outputSyncResult['updated']} records\n" .
                        "Control Stock synced: {$stockSyncResult['updated']} materials across {$stockSyncResult['products']} products"
        ]);
    } catch (\Exception $e) {
        Log::error('❌ Forecast update error', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}

/**
 * STEP 1: Sync forecast to Output Products (Product level)
 * Formula: Output Product qty_day = forecast_qty / working_days
 */
private function syncForecastToOutputProducts(MonthlyForecast $forecast): array
{
    $currentMonth = Carbon::create($forecast->year, $forecast->month, 1);
    $startDate = $currentMonth->copy()->startOfMonth()->format('Y-m-d');
    $endDate = $currentMonth->copy()->endOfMonth()->format('Y-m-d');

    $qtyPerDay = $forecast->qty_per_day;

    // Update semua Output Products dengan SAP NO yang sama dalam bulan ini
    $updated = OutputProduct::where('sap_no', $forecast->sap_no)
        ->whereBetween('stock_date', [$startDate, $endDate])
        ->update([
            'qty_day' => $qtyPerDay,
            'forecast_qty' => $forecast->forecast_qty,
            'working_days' => $forecast->working_days,
            'sync_note' => "Synced from forecast: {$forecast->forecast_qty} / {$forecast->working_days} = {$qtyPerDay}",
        ]);

    Log::info('✅ STEP 1: Forecast → Output Products', [
        'sap_no' => $forecast->sap_no,
        'forecast_qty' => $forecast->forecast_qty,
        'working_days' => $forecast->working_days,
        'qty_per_day' => $qtyPerDay,
        'updated_output_products' => $updated,
        'date_range' => [$startDate, $endDate]
    ]);

    return [
        'updated' => $updated,
        'qty_per_day' => $qtyPerDay,
        'date_range' => [$startDate, $endDate]
    ];
}

private function syncOutputProductToControlStockViaBOM(MonthlyForecast $forecast): array
{
    $currentMonth = Carbon::create($forecast->year, $forecast->month, 1);
    $startDate = $currentMonth->copy()->startOfMonth()->format('Y-m-d');
    $endDate = $currentMonth->copy()->endOfMonth()->format('Y-m-d');

    $outputProducts = OutputProduct::where('sap_no', $forecast->sap_no)
        ->whereBetween('stock_date', [$startDate, $endDate])
        ->with('materials')
        ->get();

    $totalMaterialsUpdated = 0;
    $bomCalculations = [];

    foreach ($outputProducts as $outputProduct) {
        if ($outputProduct->materials->isEmpty()) {
            Log::warning('⚠️ No BOM defined for output product', [
                'output_id' => $outputProduct->id,
                'sap_no' => $outputProduct->sap_no,
            ]);
            continue;
        }

        $outputQtyPerDay = $outputProduct->qty_day;

        // Loop setiap material dalam BOM
        foreach ($outputProduct->materials as $bomMaterial) {
            // Formula: Material qty_day = Output qty_day × qty_per_unit
            $materialQtyPerDay = $outputQtyPerDay * $bomMaterial->qty_per_unit;

            // Update Control Stock untuk material ini
            $updated = DailyStock::where('stock_date', $outputProduct->stock_date)
                ->where(function($query) use ($bomMaterial) {
                    $query->where('id_sap', $bomMaterial->sap_no)
                          ->orWhere('sap_finish', $bomMaterial->sap_no);
                })
                ->update([
                    'qty_day' => $materialQtyPerDay,
                    'qty_day_from_forecast' => $materialQtyPerDay,
                    'bom_calculation' => "Output {$outputProduct->sap_no}: {$outputQtyPerDay} × {$bomMaterial->qty_per_unit} = {$materialQtyPerDay}",
                ]);

            if ($updated > 0) {
                $totalMaterialsUpdated += $updated;

                $bomCalculations[] = [
                    'material_sap' => $bomMaterial->sap_no,
                    'output_qty_day' => $outputQtyPerDay,
                    'qty_per_unit' => $bomMaterial->qty_per_unit,
                    'material_qty_day' => $materialQtyPerDay,
                    'formula' => "{$outputQtyPerDay} × {$bomMaterial->qty_per_unit} = {$materialQtyPerDay}",
                ];

                Log::info('✅ Material qty_day calculated', [
                    'material_sap' => $bomMaterial->sap_no,
                    'output_product' => $outputProduct->sap_no,
                    'output_qty_day' => $outputQtyPerDay,
                    'bom_qty_per_unit' => $bomMaterial->qty_per_unit,
                    'material_qty_day' => $materialQtyPerDay,
                    'updated_records' => $updated,
                ]);
            }
        }
    }

    Log::info('✅ STEP 2: Output Products → Control Stock (via BOM)', [
        'forecast_sap' => $forecast->sap_no,
        'output_products_processed' => $outputProducts->count(),
        'total_materials_updated' => $totalMaterialsUpdated,
        'bom_calculations' => $bomCalculations,
    ]);

    return [
        'products' => $outputProducts->count(),
        'updated' => $totalMaterialsUpdated,
        'calculations' => $bomCalculations,
    ];
}

/**
 * Bulk import forecasts from Excel/CSV
 */
public function forecastBulkImport(Request $request)
{
    try {
        $validated = $request->validate([
            'year' => 'required|integer',
            'month' => 'required|integer|min:1|max:12',
            'working_days' => 'required|integer|min:1|max:31',
            'forecasts' => 'required|array',
            'forecasts.*.sap_no' => 'required|string',
            'forecasts.*.forecast_qty' => 'required|integer|min:0',
            'forecasts.*.product_unit' => 'nullable|string',
            'forecasts.*.type' => 'nullable|string',
        ]);

        $imported = 0;
        $results = [];

        foreach ($validated['forecasts'] as $forecastData) {
            $forecast = MonthlyForecast::updateOrCreate(
                [
                    'sap_no' => $forecastData['sap_no'],
                    'year' => $validated['year'],
                    'month' => $validated['month'],
                ],
                [
                    'forecast_qty' => $forecastData['forecast_qty'],
                    'working_days' => $validated['working_days'],
                    'product_unit' => $forecastData['product_unit'] ?? '',
                    'type' => $forecastData['type'] ?? '',
                ]
            );

            // Sync to Output Products and Control Stock
            $outputSync = $this->syncForecastToOutputProducts($forecast);
            $stockSync = $this->syncOutputProductToControlStockViaBOM($forecast);

            $results[] = [
                'sap_no' => $forecast->sap_no,
                'qty_per_day' => $forecast->qty_per_day,
                'output_synced' => $outputSync['updated'],
                'materials_synced' => $stockSync['updated'],
            ];

            $imported++;
        }

        return response()->json([
            'success' => true,
            'imported' => $imported,
            'results' => $results,
            'message' => "Successfully imported {$imported} forecasts and synced to Output Products & Control Stock"
        ]);
    } catch (\Exception $e) {
        Log::error('❌ Bulk import error', [
            'message' => $e->getMessage(),
        ]);
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}

/**
 * Delete forecast
 */
public function forecastDelete($id)
{
    try {
        $forecast = MonthlyForecast::findOrFail($id);

        Log::info('🗑️ Deleting forecast', [
            'id' => $forecast->id,
            'sap_no' => $forecast->sap_no,
        ]);

        $forecast->delete();

        return response()->json([
            'success' => true,
            'message' => 'Forecast deleted successfully'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}

/**
 * Get forecast summary for a month
 */
public function forecastSummary(Request $request)
{
    $year = $request->input('year', now()->year);
    $month = $request->input('month', now()->month);

    $forecasts = MonthlyForecast::forMonth($year, $month)->get();

    $summary = [
        'total_forecasts' => $forecasts->count(),
        'total_forecast_qty' => $forecasts->sum('forecast_qty'),
        'avg_working_days' => $forecasts->avg('working_days'),
        'total_qty_per_day' => $forecasts->sum('qty_per_day'),
        'by_type' => $forecasts->groupBy('type')->map(function($group) {
            return [
                'count' => $group->count(),
                'total_qty' => $group->sum('forecast_qty'),
                'total_qty_per_day' => $group->sum('qty_per_day'),
            ];
        }),
    ];

    return response()->json([
        'success' => true,
        'summary' => $summary,
    ]);
}
}






