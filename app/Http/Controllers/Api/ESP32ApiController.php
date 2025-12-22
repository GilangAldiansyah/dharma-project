<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Esp32Device;
use App\Models\Esp32Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
class ESP32ApiController extends Controller
{
    public function postData(Request $request)
    {
        try {
            $validated = $request->validate([
                'device_id' => 'required|string|max:100',
                'counter_a' => 'required|integer',
                'counter_b' => 'required|integer',
                'max_count' => 'required|integer',
                'relay_status' => 'required|boolean',
                'error_B' => 'required|boolean',
            ]);

            $device = Esp32Device::updateOrCreate(
                ['device_id' => $validated['device_id']],
                [
                    'counter_a' => $validated['counter_a'],
                    'counter_b' => $validated['counter_b'],
                    'max_count' => $validated['max_count'],
                    'relay_status' => $validated['relay_status'],
                    'error_b' => $validated['error_B'],
                    'last_update' => now(),
                ]
            );

            // Log data setiap 1 menit
            $lastLog = Esp32Log::where('device_id', $validated['device_id'])
                ->orderBy('logged_at', 'desc')
                ->first();

            $shouldLog = !$lastLog || $lastLog->logged_at->diffInSeconds(now()) >= 60;

            if ($shouldLog) {
                Esp32Log::create([
                    'device_id' => $validated['device_id'],
                    'counter_a' => $validated['counter_a'],
                    'counter_b' => $validated['counter_b'],
                    'max_count' => $validated['max_count'],
                    'relay_status' => $validated['relay_status'],
                    'error_b' => $validated['error_B'],
                    'logged_at' => now(),
                ]);

                $totalLogs = Esp32Log::where('device_id', $validated['device_id'])->count();
                if ($totalLogs > 100) {
                    Esp32Log::where('device_id', $validated['device_id'])
                        ->orderBy('logged_at', 'asc')
                        ->limit($totalLogs - 100)
                        ->delete();
                }
            }

            Log::info("ESP32 data received", [
                'device_id' => $validated['device_id'],
                'counter_a' => $validated['counter_a'],
                'counter_b' => $validated['counter_b'],
            ]);

            return response()->json([
                'success' => true,
                'message' => "Status for {$validated['device_id']} updated successfully"
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('ESP32 POST Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Get status semua devices
     *
     * @route GET /api/esp32/status
     * @access Protected (auth:sanctum)
     */
    public function getStatus()
    {
        try {
            $devices = Esp32Device::orderBy('last_update', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $devices
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch devices'
            ], 500);
        }
    }

    /**
     * Get history log dari device tertentu
     *
     * @route GET /api/esp32/history/{deviceId}
     * @access Protected (auth:sanctum)
     */
    public function getHistory($deviceId)
    {
        try {
            $logs = Esp32Log::where('device_id', $deviceId)
                ->orderBy('logged_at', 'desc')
                ->limit(100)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $logs
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch history'
            ], 500);
        }
    }

    /**
     * Get daftar semua device IDs
     *
     * @route GET /api/esp32/devices
     * @access Protected (auth:sanctum)
     */
    public function getDevices()
    {
        try {
            $devices = Esp32Device::select('device_id', 'last_update')
                ->orderBy('last_update', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $devices
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch device list'
            ], 500);
        }
    }
}
