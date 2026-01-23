<?php

namespace App\Http\Controllers;

use App\Models\Esp32Device;
use App\Models\Esp32Log;
use App\Models\Esp32ProductionHistory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ESP32Controller extends Controller
{
    public function monitor(Request $request)
    {
        $search = $request->input('search');

        $devices = Esp32Device::query()
            ->when($search, function ($query, $search) {
                $query->where('device_id', 'like', "%{$search}%");
            })
            ->orderBy('device_id', 'asc')
            ->paginate(12);

        return Inertia::render('ESP32/Monitor', [
            'devices' => $devices,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function detail($deviceId)
    {
        $device = Esp32Device::where('device_id', $deviceId)->firstOrFail();

    //      dd([
    //     'counter_a' => $device->counter_a,
    //     'production_started_at' => $device->production_started_at,
    //     'last_update' => $device->last_update,
    //     'cycle_time' => $device->cycle_time,
    //     'delay_seconds' => $device->delay_seconds,
    // ]);

        $logs = Esp32Log::where('device_id', $deviceId)
            ->orderBy('logged_at', 'desc')
            ->paginate(50);

        $productionHistories = Esp32ProductionHistory::where('device_id', $deviceId)
            ->orderBy('production_finished_at', 'desc')
            ->limit(10)
            ->get();

        return Inertia::render('ESP32/Detail', [
            'device' => $device,
            'logs' => $logs,
            'productionHistories' => $productionHistories,
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'device_id' => 'required|string|exists:esp32_devices,device_id',
            'max_count' => 'required|integer|min:0',
            'max_stroke' => 'nullable|integer|min:0',
            'reject' => 'required|integer|min:0',
            'cycle_time' => 'required|integer|min:0',
        ]);

        $loadingTime = $validated['max_count'] * $validated['cycle_time'];

        $device = Esp32Device::where('device_id', $validated['device_id'])->first();

        $productionStartedAt = $device->production_started_at;

        if ($device->cycle_time != $validated['cycle_time'] && $device->counter_a > 0) {
            $productionStartedAt = now()->subSeconds($device->counter_a * $validated['cycle_time']);
        }

        $updateData = [
            'max_count' => $validated['max_count'],
            'reject' => $validated['reject'],
            'cycle_time' => $validated['cycle_time'],
            'loading_time' => $loadingTime,
            'production_started_at' => $productionStartedAt,
        ];

        if (isset($validated['max_stroke'])) {
            $updateData['max_stroke'] = $validated['max_stroke'];
        }

        $device->update($updateData);

        return back()->with('success', 'Settings berhasil diupdate');
    }
}
