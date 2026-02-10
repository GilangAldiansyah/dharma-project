<?php

namespace App\Http\Controllers;

use App\Models\Line;
use App\Models\LineOperation;
use App\Models\Esp32Device;
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

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Start operation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function pauseOperation(Request $request, int $operationId)
    {
        try {
            $validated = $request->validate([
                'paused_by' => 'nullable|string|max:100',
            ]);

            DB::beginTransaction();

            $operation = LineOperation::findOrFail($operationId);

            if ($operation->status !== 'running') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Operasi tidak sedang berjalan.',
                ], 400);
            }

            $operation->pause($validated['paused_by'] ?? 'System');
            $operation->line->update(['status' => 'paused']);

            $esp32Device = $operation->line->esp32Device;
            if ($esp32Device && !$esp32Device->is_paused) {
                $esp32Device->update([
                    'is_paused' => true,
                    'paused_at' => now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Operasi dan produksi di-pause',
                'data' => $operation->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Pause operation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal pause operasi: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function resumeOperation(Request $request, int $operationId)
    {
        try {
            $validated = $request->validate([
                'resumed_by' => 'nullable|string|max:100',
            ]);

            DB::beginTransaction();

            $operation = LineOperation::findOrFail($operationId);

            if ($operation->status !== 'paused') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Operasi tidak dalam status pause.',
                ], 400);
            }

            $operation->resume($validated['resumed_by'] ?? 'System');
            $operation->line->update(['status' => 'operating']);

            $esp32Device = $operation->line->esp32Device;
            if ($esp32Device && $esp32Device->is_paused) {
                $pauseDuration = now()->diffInSeconds($esp32Device->paused_at);

                $esp32Device->update([
                    'is_paused' => false,
                    'total_pause_seconds' => $esp32Device->total_pause_seconds + $pauseDuration,
                    'paused_at' => null,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Operasi dan produksi dilanjutkan',
                'data' => $operation->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Resume operation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal resume operasi: ' . $e->getMessage(),
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

            $line = $operation->line;

            $esp32Device = $line->esp32Device;
            if ($esp32Device) {
                if ($esp32Device->is_paused && $esp32Device->paused_at) {
                    $lastPauseDuration = now()->diffInSeconds($esp32Device->paused_at);
                    $esp32Device->total_pause_seconds += $lastPauseDuration;
                }

                $esp32Device->update([
                    'is_paused' => false,
                    'paused_at' => null,
                ]);
            }

            $line->autoArchiveAndReset('Stop operation by ' . ($validated['stopped_by'] ?? 'System'));

            $line->refresh();
            $line->load('currentOperation', 'machines');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Operasi dihentikan dan metrics telah direset. Data periode sebelumnya tersimpan dalam history.',
                'data' => [
                    'operation' => $operation->fresh(),
                    'line' => $line,
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
}
