<?php

namespace App\Http\Controllers;

use App\Models\Line;
use App\Models\LineOperation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LineOperationController extends Controller
{
   public function startOperation(Request $request)
{
    try {
        $validated = $request->validate([
            'line_id' => 'required|exists:lines,id',
            'started_by' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();

        $line = Line::findOrFail($validated['line_id']);

        $runningOperation = $line->currentOperation;

        if ($runningOperation) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Line sudah dalam status operasi.',
            ], 400);
        }

        $operation = LineOperation::create([
            'line_id' => $validated['line_id'],
            'operation_number' => LineOperation::generateOperationNumber(),
            'started_at' => now(),
            'started_by' => $validated['started_by'] ?: 'System',
            'status' => 'running',
            'notes' => $validated['notes'],
        ]);

        $line->update([
            'status' => 'operating',
            'last_operation_start' => now(),
        ]);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Operasi berhasil dimulai',
            'data' => $operation,
        ]);

    } catch (\Illuminate\Validation\ValidationException $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Validasi gagal',
            'errors' => $e->errors(),
        ], 422);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
        ], 500);
    }
}
    public function stopOperation(Request $request, int $operationId)
    {
        try {
            $validated = $request->validate([
                'stopped_by' => 'nullable|string|max:100',
                'notes' => 'nullable|string',
            ]);

            DB::beginTransaction();

            $operation = LineOperation::findOrFail($operationId);

            if ($operation->status === 'stopped') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Operasi sudah dihentikan sebelumnya.',
                ], 400);
            }

            $operation->update([
                'stopped_at' => now(),
                'stopped_by' => $validated['stopped_by'] ?? 'System',
                'status' => 'stopped',
                'notes' => $validated['notes'] ?? $operation->notes,
            ]);

            $operation->calculateMetrics();
            $operation->line->update([
                'status' => 'stopped',
                'last_line_stop' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Operasi line dihentikan!',
                'data' => [
                    'operation' => $operation->fresh(),
                    'line' => $operation->line->fresh(),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Stop operation error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghentikan operasi: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getCurrentOperation(int $lineId)
    {
        try {
            $line = Line::with('currentOperation')->findOrFail($lineId);

            if (!$line->currentOperation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada operasi yang sedang berjalan',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $line->currentOperation,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data operasi: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getOperationHistory(Request $request, int $lineId)
    {
        try {
            $operations = LineOperation::where('line_id', $lineId)
                ->with('maintenanceReports')
                ->orderBy('started_at', 'desc')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $operations,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil riwayat operasi: ' . $e->getMessage(),
            ], 500);
        }
    }
}
