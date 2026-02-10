<?php

namespace App\Http\Controllers;

use App\Models\Esp32Device;
use App\Models\Esp32Log;
use App\Models\Esp32ProductionHistory;
use App\Models\Area;
use App\Models\Line;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ESP32Controller extends Controller
{
    public function monitor(Request $request)
    {
        $search = $request->input('search');
        $areaId = $request->input('area');

        $devices = Esp32Device::query()
            ->with(['area', 'line'])
            ->when($search, function ($query, $search) {
                $query->where('device_id', 'like', "%{$search}%");
            })
            ->when($areaId, function ($query, $areaId) {
                $query->where('area_id', $areaId);
            })
            ->orderBy('device_id', 'asc')
            ->get();

        $areas = Area::orderBy('name', 'asc')->get();
        $lines = Line::where('is_archived', false)
            ->orderBy('line_code', 'asc')
            ->get(['id', 'line_code', 'line_name']);

        return Inertia::render('ESP32/Monitor', [
            'devices' => $devices,
            'areas' => $areas,
            'lines' => $lines,
            'filters' => [
                'search' => $search,
                'area' => $areaId,
            ],
        ]);
    }

    public function detail($deviceId, Request $request)
    {
        $device = Esp32Device::where('device_id', $deviceId)
            ->with(['area', 'line'])
            ->firstOrFail();

        $logs = Esp32Log::where('device_id', $deviceId)
            ->orderBy('logged_at', 'desc')
            ->paginate(100);

        $historyQuery = Esp32ProductionHistory::where('device_id', $deviceId);

        if ($request->filled('history_date')) {
            $historyQuery->whereDate('production_started_at', $request->input('history_date'));
        }

        if ($request->filled('history_shift')) {
            $historyQuery->where('shift', $request->input('history_shift'));
        }

        $productionHistories = $historyQuery
            ->orderBy('production_finished_at', 'desc')
            ->paginate(10, ['*'], 'history_page');

        return Inertia::render('ESP32/Detail', [
            'device' => $device,
            'logs' => $logs,
            'productionHistories' => $productionHistories,
            'shifts' => \App\Helpers\DateHelper::getAllShifts(),
            'filters' => [
                'history_date' => $request->input('history_date'),
                'history_shift' => $request->input('history_shift'),
            ],
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
            'area_id' => 'nullable|integer|exists:areas,id',
            'line_id' => 'nullable|integer|exists:lines,id',
            'new_area_name' => 'nullable|string|max:255',
            'reset_counter' => 'nullable|boolean',
        ]);

        $device = Esp32Device::where('device_id', $validated['device_id'])->first();

        $areaId = null;
        if (isset($validated['new_area_name']) && $validated['new_area_name']) {
            $area = Area::firstOrCreate(['name' => $validated['new_area_name']]);
            $areaId = $area->id;
        } elseif (isset($validated['area_id'])) {
            $areaId = $validated['area_id'];
        }

        $lineId = $validated['line_id'] ?? null;

        if ($validated['reset_counter'] ?? false) {
            if ($device->counter_a > 0) {
                $this->saveProductionHistory($device);
            }

            if ($device->line_id) {
                $device->autoStopLineOperation();
            }

            $device->update([
                'counter_a' => 0,
                'counter_b' => 0,
                'reject' => 0,
                'area_id' => $areaId,
                'line_id' => $lineId,
                'production_started_at' => null,
                'is_paused' => false,
                'paused_at' => null,
                'total_pause_seconds' => 0,
            ]);

            return back()->with('success', 'Counter berhasil direset ke 0');
        }

        $loadingTime = $validated['max_count'] * $validated['cycle_time'];
        $productionStartedAt = $device->production_started_at;

        if ($device->counter_a > 0) {
            $netElapsedSeconds = $device->counter_a * $validated['cycle_time'];
            $productionStartedAt = now()->subSeconds($netElapsedSeconds + $device->total_pause_seconds);
        } elseif (!$productionStartedAt) {
            $productionStartedAt = now();
        }

        $updateData = [
            'max_count' => $validated['max_count'],
            'reject' => $validated['reject'],
            'cycle_time' => $validated['cycle_time'],
            'loading_time' => $loadingTime,
            'area_id' => $areaId,
            'line_id' => $lineId,
            'production_started_at' => $productionStartedAt,
        ];

        if (isset($validated['max_stroke'])) {
            $updateData['max_stroke'] = $validated['max_stroke'];
        }

        $device->update($updateData);

        return back()->with('success', 'Settings berhasil diupdate');
    }

    private function saveProductionHistory($device)
    {
        if (!$device->production_started_at || $device->counter_a == 0) {
            return;
        }

        $productionStarted = \Carbon\Carbon::parse($device->production_started_at);
        $productionFinished = now();

        $actualTimeSeconds = $productionFinished->timestamp - $productionStarted->timestamp;
        $netTimeSeconds = $actualTimeSeconds - $device->total_pause_seconds;
        $expectedTimeSeconds = $device->counter_a * $device->cycle_time;
        $delaySeconds = $netTimeSeconds - $expectedTimeSeconds;

        if (abs($delaySeconds) <= $device->cycle_time) {
            $completionStatus = 'on_time';
        } elseif ($delaySeconds > 0) {
            $completionStatus = 'delayed';
        } else {
            $completionStatus = 'ahead';
        }

        Esp32ProductionHistory::create([
            'device_id' => $device->device_id,
            'total_counter_a' => $device->counter_a,
            'total_counter_b' => $device->counter_b,
            'total_reject' => $device->reject,
            'cycle_time' => $device->cycle_time,
            'max_count' => $device->max_count,
            'max_stroke' => $device->max_stroke,
            'expected_time_seconds' => $expectedTimeSeconds,
            'actual_time_seconds' => $netTimeSeconds,
            'delay_seconds' => $delaySeconds,
            'production_started_at' => $productionStarted,
            'production_finished_at' => $productionFinished,
            'completion_status' => $completionStatus,
        ]);
    }
}
