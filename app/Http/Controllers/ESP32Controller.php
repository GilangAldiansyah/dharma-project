<?php

namespace App\Http\Controllers;

use App\Models\Esp32Device;
use App\Models\Esp32Log;
use Illuminate\Http\Request;
use Inertia\Inertia;


class ESP32Controller extends Controller
{
    /**
     * Halaman monitoring - List semua devices
     *
     * @route GET /esp32/monitor
     * @access Protected (auth middleware)
     */
    public function monitor(Request $request)
    {
        $search = $request->input('search');

        $devices = Esp32Device::query()
            ->when($search, function ($query, $search) {
                $query->where('device_id', 'like', "%{$search}%");
            })
            ->orderBy('last_update', 'desc')
            ->paginate(12);

        return Inertia::render('ESP32/Monitor', [
            'devices' => $devices,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Halaman detail device - History & stats
     *
     * @route GET /esp32/monitor/{device_id}
     * @access Protected (auth middleware)
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
