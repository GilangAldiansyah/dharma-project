<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Esp32Device;
use App\Models\Esp32Log;
use App\Models\Esp32ProductionHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ESP32ApiController extends Controller
{
    public function postData(Request $request)
    {
        $this->autoStopIdleDevices();

        try {
            $validated = $request->validate([
                'device_id'           => 'required|string|max:100',
                'counter_a'           => 'required|integer',
                'counter_b'           => 'nullable|integer',
                'relay_status'        => 'nullable|boolean',
                'error_B'             => 'nullable|boolean',
                'stop_operation'      => 'nullable|boolean',
                'line_stop_requested' => 'nullable|boolean',
                'pause_requested'     => 'nullable|boolean',
                'resume_requested'    => 'nullable|boolean',
            ]);

            $oldDevice = Esp32Device::where('device_id', $validated['device_id'])->first();

            if (!$oldDevice) {
                $device = Esp32Device::create([
                    'device_id'             => $validated['device_id'],
                    'counter_a'             => $validated['counter_a'],
                    'counter_b'             => $validated['counter_b'] ?? 0,
                    'reject'                => 0,
                    'cycle_time'            => 3,
                    'max_count'             => 100,
                    'max_stroke'            => 0,
                    'loading_time'          => 300,
                    'production_started_at' => now(),
                    'relay_status'          => $validated['relay_status'] ?? false,
                    'error_b'               => $validated['error_B'] ?? false,
                    'reset_requested'       => false,
                    'stop_operation'        => false,
                    'line_stop_requested'   => false,
                    'pause_requested'       => false,
                    'resume_requested'      => false,
                    'is_paused'             => false,
                    'paused_at'             => null,
                    'total_pause_seconds'   => 0,
                    'last_update'           => now(),
                ]);

                Esp32Log::create([
                    'device_id'             => $validated['device_id'],
                    'counter_a'             => $validated['counter_a'],
                    'counter_b'             => $validated['counter_b'] ?? 0,
                    'reject'                => 0,
                    'cycle_time'            => 3,
                    'max_count'             => 100,
                    'max_stroke'            => 0,
                    'loading_time'          => 300,
                    'production_started_at' => $device->production_started_at,
                    'relay_status'          => $validated['relay_status'] ?? false,
                    'error_b'               => $validated['error_B'] ?? false,
                    'is_paused'             => false,
                    'paused_at'             => null,
                    'total_pause_seconds'   => 0,
                    'logged_at'             => now(),
                ]);

                return response()->json([
                    'success'        => true,
                    'message'        => "Device {$validated['device_id']} registered successfully",
                    'new_max_count'  => $device->max_count,
                    'new_cycle_time' => $device->cycle_time,
                    'reset_counter'  => false,
                ], 200);
            }

            if (!empty($validated['stop_operation'])) {
                $oldDevice->update([
                    'counter_a'             => 0,
                    'counter_b'             => 0,
                    'relay_status'          => $validated['relay_status'] ?? $oldDevice->relay_status,
                    'error_b'               => $validated['error_B'] ?? $oldDevice->error_b,
                    'stop_operation'        => false,
                    'is_paused'             => false,
                    'paused_at'             => null,
                    'total_pause_seconds'   => 0,
                    'production_started_at' => null,
                    'last_update'           => now(),
                    'reset_requested'       => false,
                ]);

                $oldDevice->refresh();
                $oldDevice->autoStopLineOperation();

                Esp32Log::create([
                    'device_id'             => $validated['device_id'],
                    'counter_a'             => 0,
                    'counter_b'             => 0,
                    'reject'                => $oldDevice->reject,
                    'cycle_time'            => $oldDevice->cycle_time,
                    'max_count'             => $oldDevice->max_count,
                    'max_stroke'            => $oldDevice->max_stroke,
                    'loading_time'          => $oldDevice->loading_time,
                    'production_started_at' => null,
                    'relay_status'          => $validated['relay_status'] ?? $oldDevice->relay_status,
                    'error_b'               => $validated['error_B'] ?? $oldDevice->error_b,
                    'is_paused'             => false,
                    'paused_at'             => null,
                    'total_pause_seconds'   => 0,
                    'logged_at'             => now(),
                ]);

                return response()->json([
                    'success'        => true,
                    'message'        => "Operation stopped via physical button for {$validated['device_id']}",
                    'new_max_count'  => $oldDevice->max_count,
                    'new_cycle_time' => $oldDevice->cycle_time,
                    'reset_counter'  => true,
                ], 200);
            }

            if (!empty($validated['line_stop_requested'])) {
                $line = $oldDevice->line;

                if ($line) {
                    $line->update([
                        'status'              => 'maintenance',
                        'last_line_stop'      => now(),
                        'pending_line_stop'   => true,
                    ]);

                    try {
                        \App\Models\AppNotification::create([
                            'type'    => 'line_stop_pending',
                            'title'   => 'Line Stop - Konfirmasi Diperlukan',
                            'message' => "Line {$line->line_name} dihentikan via tombol fisik ESP32. Harap konfirmasi dan isi detail.",
                            'data'    => json_encode(['line_id' => $line->id, 'line_name' => $line->line_name]),
                        ]);
                    } catch (\Exception $e) {
                        Log::warning('[Notif] ' . $e->getMessage());
                    }
                }

                $oldDevice->update([
                    'line_stop_requested' => false,
                    'relay_status'        => $validated['relay_status'] ?? $oldDevice->relay_status,
                    'error_b'             => $validated['error_B'] ?? $oldDevice->error_b,
                    'last_update'         => now(),
                ]);

                return response()->json([
                    'success'        => true,
                    'message'        => "Line stop triggered for {$validated['device_id']}, awaiting confirmation from web",
                    'new_max_count'  => $oldDevice->max_count,
                    'new_cycle_time' => $oldDevice->cycle_time,
                    'reset_counter'  => false,
                ], 200);
            }

            if (!empty($validated['pause_requested'])) {
                $line = $oldDevice->line;

                if ($line) {
                    $currentOperation = $line->currentOperation;

                    if ($currentOperation && $currentOperation->status === 'running') {
                        $currentOperation->pause('ESP32: ' . $oldDevice->device_id);
                        $line->update(['status' => 'paused']);

                        if (!$oldDevice->is_paused) {
                            $oldDevice->update([
                                'is_paused' => true,
                                'paused_at' => now(),
                            ]);
                        }
                    }
                }

                $oldDevice->update([
                    'pause_requested' => false,
                    'relay_status'    => $validated['relay_status'] ?? $oldDevice->relay_status,
                    'error_b'         => $validated['error_B'] ?? $oldDevice->error_b,
                    'last_update'     => now(),
                ]);

                return response()->json([
                    'success'        => true,
                    'message'        => "Pause processed for {$validated['device_id']}",
                    'new_max_count'  => $oldDevice->max_count,
                    'new_cycle_time' => $oldDevice->cycle_time,
                    'reset_counter'  => false,
                ], 200);
            }

            if (!empty($validated['resume_requested'])) {
                $line = $oldDevice->line;

                if ($line) {
                    $currentOperation = $line->currentOperation;

                    if ($currentOperation && $currentOperation->status === 'paused') {
                        $currentOperation->resume('ESP32: ' . $oldDevice->device_id);
                        $line->update(['status' => 'operating']);

                        if ($oldDevice->is_paused && $oldDevice->paused_at) {
                            $pauseDuration = now()->diffInSeconds($oldDevice->paused_at);
                            $oldDevice->update([
                                'is_paused'           => false,
                                'total_pause_seconds' => $oldDevice->total_pause_seconds + $pauseDuration,
                                'paused_at'           => null,
                            ]);
                        }
                    }
                }

                $oldDevice->update([
                    'resume_requested' => false,
                    'relay_status'     => $validated['relay_status'] ?? $oldDevice->relay_status,
                    'error_b'          => $validated['error_B'] ?? $oldDevice->error_b,
                    'last_update'      => now(),
                ]);

                return response()->json([
                    'success'        => true,
                    'message'        => "Resume processed for {$validated['device_id']}",
                    'new_max_count'  => $oldDevice->max_count,
                    'new_cycle_time' => $oldDevice->cycle_time,
                    'reset_counter'  => false,
                ], 200);
            }

            $resetRequested = $oldDevice->reset_requested ?? false;

            $counterChanged = $oldDevice->counter_a != $validated['counter_a'] ||
            $oldDevice->counter_b != ($validated['counter_b'] ?? 0);

            $errorChanged = $oldDevice->error_b != ($validated['error_B'] ?? false);

            $secondsSinceLastUpdate = now()->diffInSeconds(Carbon::parse($oldDevice->last_update));
            $isDuplicate = !$counterChanged && !$errorChanged && $secondsSinceLastUpdate < 3;

            $shouldLog = ($counterChanged || $errorChanged) && !$isDuplicate;

            $productionStartedAt = $oldDevice->production_started_at;
            $totalPauseSeconds   = $oldDevice->total_pause_seconds;
            $isPaused            = $oldDevice->is_paused;
            $pausedAt            = $oldDevice->paused_at;
            $lastUpdate          = $oldDevice->last_update;

            if ($counterChanged) {
                $lastUpdate = now();

                if ($isPaused) {
                    $pauseDuration      = now()->timestamp - Carbon::parse($pausedAt)->timestamp;
                    $totalPauseSeconds += $pauseDuration;
                    $isPaused           = false;
                    $pausedAt           = null;
                }
            }

            if ($oldDevice->counter_a > 0 && $validated['counter_a'] == 0) {
                $this->saveProductionHistory($oldDevice);

                $oldDevice->refresh();
                $oldDevice->autoStopLineOperation();

                $productionStartedAt = now();
                $totalPauseSeconds   = 0;
                $isPaused            = false;
                $pausedAt            = null;
                $lastUpdate          = now();
            } elseif ($oldDevice->counter_a == 0 && $validated['counter_a'] > 0) {
                $oldDevice->autoStartLineOperation();

                if ($oldDevice->cycle_time > 0) {
                    $productionStartedAt = now()->subSeconds($validated['counter_a'] * $oldDevice->cycle_time + $totalPauseSeconds);
                } else {
                    $productionStartedAt = now();
                }
                $lastUpdate = now();
            } elseif (!$productionStartedAt && $validated['counter_a'] > 0) {
                $oldDevice->autoStartLineOperation();

                if ($oldDevice->cycle_time > 0) {
                    $productionStartedAt = now()->subSeconds($validated['counter_a'] * $oldDevice->cycle_time + $totalPauseSeconds);
                } else {
                    $productionStartedAt = now();
                }
                $lastUpdate = now();
            } elseif (!$productionStartedAt) {
                $productionStartedAt = now();
            }

            $oldDevice->update([
                'counter_a'             => $validated['counter_a'],
                'counter_b'             => $validated['counter_b'] ?? $oldDevice->counter_b,
                'relay_status'          => $validated['relay_status'] ?? $oldDevice->relay_status,
                'error_b'               => $validated['error_B'] ?? $oldDevice->error_b,
                'production_started_at' => $productionStartedAt,
                'is_paused'             => $isPaused,
                'paused_at'             => $pausedAt,
                'total_pause_seconds'   => $totalPauseSeconds,
                'last_update'           => $lastUpdate,
                'reset_requested'       => false,
            ]);

            if ($shouldLog) {
                Esp32Log::create([
                    'device_id'             => $validated['device_id'],
                    'counter_a'             => $validated['counter_a'],
                    'counter_b'             => $validated['counter_b'] ?? $oldDevice->counter_b,
                    'reject'                => $oldDevice->reject,
                    'cycle_time'            => $oldDevice->cycle_time,
                    'max_count'             => $oldDevice->max_count,
                    'max_stroke'            => $oldDevice->max_stroke,
                    'loading_time'          => $oldDevice->loading_time,
                    'production_started_at' => $productionStartedAt,
                    'relay_status'          => $validated['relay_status'] ?? $oldDevice->relay_status,
                    'error_b'               => $validated['error_B'] ?? $oldDevice->error_b,
                    'is_paused'             => $isPaused,
                    'paused_at'             => $pausedAt,
                    'total_pause_seconds'   => $totalPauseSeconds,
                    'logged_at'             => now(),
                ]);

                Esp32Log::where('device_id', $validated['device_id'])
                    ->where('logged_at', '<', now()->subHours(48))
                    ->delete();
            }

            return response()->json([
                'success'        => true,
                'message'        => "Status for {$validated['device_id']} updated successfully",
                'new_max_count'  => $oldDevice->max_count,
                'new_cycle_time' => $oldDevice->cycle_time,
                'reset_counter'  => $resetRequested,
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('ESP32 POST Error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error'
            ], 500);
        }
    }

    private function saveProductionHistory(Esp32Device $device)
    {
        if (!$device->production_started_at || $device->counter_a == 0) {
            return;
        }

        $productionStarted = Carbon::parse($device->production_started_at);
        $productionFinished = Carbon::parse($device->last_update);

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

    private function autoStopIdleDevices()
    {
        Esp32Device::where('counter_a', '>', 0)
            ->whereNotNull('production_started_at')
            ->where('last_update', '<=', now()->subHour())
            ->each(function ($device) {
                $this->saveProductionHistory($device);

                $device->update([
                    'counter_a'             => 0,
                    'counter_b'             => 0,
                    'reject'                => 0,
                    'production_started_at' => null,
                    'is_paused'             => false,
                    'paused_at'             => null,
                    'total_pause_seconds'   => 0,
                    'reset_requested'       => true,
                ]);
            });
    }
}
