<?php

namespace App\Http\Controllers;

use App\Models\Esp32Device;
use App\Models\Esp32Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ESP32Controller extends Controller
{
    /**
     * Endpoint untuk menerima data POST dari ESP32
     * Route: POST /api/esp32/post
     */
    public function postData(Request $request)
    {
        try {
            // Validasi data dari ESP32
            $validated = $request->validate([
                'device_id' => 'required|string|max:100',
                'counter_a' => 'required|integer',
                'counter_b' => 'required|integer',
                'max_count' => 'required|integer',
                'relay_status' => 'required|boolean',
                'error_B' => 'required|boolean', // Sesuai dengan ESP32
            ]);

            // Update atau buat device baru
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

            // Log data setiap 1 menit (cek log terakhir)
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

                // Batasi riwayat maksimal 100 per device
                $totalLogs = Esp32Log::where('device_id', $validated['device_id'])->count();
                if ($totalLogs > 100) {
                    Esp32Log::where('device_id', $validated['device_id'])
                        ->orderBy('logged_at', 'asc')
                        ->limit($totalLogs - 100)
                        ->delete();
                }
            }

            Log::info("Data received from {$validated['device_id']}", $validated);

            return response()->json([
                'message' => "Status for {$validated['device_id']} updated successfully"
            ], 200);

        } catch (\Exception $e) {
            Log::error('ESP32 POST Error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }

    /**
     * Endpoint untuk mengambil semua status terbaru
     * Route: GET /api/esp32/status
     */
    public function getStatus()
    {
        $devices = Esp32Device::all()->keyBy('device_id');
        return response()->json($devices);
    }

    /**
     * Endpoint untuk mengambil riwayat device tertentu
     * Route: GET /api/esp32/history/{device_id}
     */
    public function getHistory($deviceId)
    {
        $logs = Esp32Log::where('device_id', $deviceId)
            ->orderBy('logged_at', 'desc')
            ->limit(100)
            ->get();

        return response()->json($logs);
    }

    /**
     * Endpoint untuk mendapatkan daftar device ID
     * Route: GET /api/esp32/devices
     */
    public function getDevices()
    {
        $deviceIds = Esp32Device::pluck('device_id');
        return response()->json($deviceIds);
    }

    /**
     * Halaman dashboard monitoring (Inertia)
     * Route: GET /esp32/monitor
     */
    public function monitor(Request $request)
    {
        $search = $request->input('search');

        $devices = Esp32Device::query()
            ->when($search, function ($query, $search) {
                $query->where('device_id', 'like', "%{$search}%");
            })
            ->orderBy('last_update', 'desc')
            ->paginate(10);

        return Inertia::render('ESP32/Monitor', [
            'devices' => $devices,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Detail device dengan history logs
     * Route: GET /esp32/monitor/{device_id}
     */
    public function detail($deviceId)
    {
        $device = Esp32Device::where('device_id', $deviceId)->firstOrFail();

        $logs = Esp32Log::where('device_id', $deviceId)
            ->orderBy('logged_at', 'desc')
            ->paginate(50);

        return Inertia::render('ESP32/Detail', [
            'device' => $device,
            'logs' => $logs,
        ]);
    }
}
