<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\AndroidConfig;

class AppNotification extends Model
{
    protected $table = 'app_notifications';

    protected $fillable = [
        'type',
        'title',
        'body',
        'data',
        'is_read',
    ];

    protected $casts = [
        'data'    => 'array',
        'is_read' => 'boolean',
    ];

    public static function createLineStop(MaintenanceReport $report): self
    {
        $title = 'LINE STOP - ' . $report->line->line_name;
        $body  = implode("\n", [
            'Line    : ' . $report->line->line_name . ' (' . $report->line->line_code . ')',
            'Plant   : ' . $report->line->plant,
            'Mesin   : ' . $report->machine->machine_name,
            'Masalah : ' . ($report->problem ?: '-'),
            'Oleh    : ' . ($report->reported_by ?: '-'),
            'Shift   : ' . $report->shift_label,
            'Waktu   : ' . now()->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
            'No      : ' . $report->report_number,
        ]);

        $notification = self::create([
            'type'  => 'line_stop',
            'title' => $title,
            'body'  => $body,
            'data'  => [
                'report_id' => $report->id,
                'line_id'   => $report->line_id,
            ],
        ]);

        self::sendFcm($title, $body, 'line_stop', [
            'report_id' => (string) $report->id,
            'line_id'   => (string) $report->line_id,
        ]);

        return $notification;
    }

    public static function createRepairComplete(MaintenanceReport $report): self
    {
        $title = 'Perbaikan Selesai - ' . $report->line->line_name;
        $body  = implode("\n", [
            'Line         : ' . $report->line->line_name . ' (' . $report->line->line_code . ')',
            'Mesin        : ' . $report->machine->machine_name,
            'Masalah      : ' . ($report->problem ?: '-'),
            'Waktu Repair : ' . ($report->repair_duration_formatted ?: '-'),
            'Selesai      : ' . now()->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
            'No           : ' . $report->report_number,
        ]);

        $notification = self::create([
            'type'  => 'repair_complete',
            'title' => $title,
            'body'  => $body,
            'data'  => [
                'report_id' => $report->id,
                'line_id'   => $report->line_id,
            ],
        ]);

        self::sendFcm($title, $body, 'repair_complete', [
            'report_id' => (string) $report->id,
            'line_id'   => (string) $report->line_id,
        ]);

        return $notification;
    }

    private static function sendFcm(string $title, string $body, string $type, array $data = []): void
    {
        try {
            $tokens = DeviceToken::getAllTokens();

            if (empty($tokens)) {
                Log::info('[FCM] No device tokens found, skipping.');
                return;
            }

            $messaging = app('firebase.messaging');

            $data['type'] = $type;

            $channelId = $type === 'line_stop' ? 'line_stop_channel' : 'repair_channel';

            $message = CloudMessage::new()
                ->withNotification(Notification::create($title, $body))
                ->withData($data)
                ->withAndroidConfig(
                    AndroidConfig::fromArray([
                        'priority' => 'high',
                        'notification' => [
                            'channel_id'   => $channelId,
                            'sound'        => 'default',
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        ],
                    ])
                );

            $report = $messaging->sendMulticast($message, $tokens);

            if ($report->hasFailures()) {
                foreach ($report->failures()->getItems() as $failure) {
                    $errorMsg = $failure->error()->getMessage();
                    $token    = $failure->target()->value();

                    Log::warning('[FCM] Failed: ' . $errorMsg . ' | token: ' . $token);

                    if (str_contains($errorMsg, 'not-registered') ||
                        str_contains($errorMsg, 'invalid-registration-token')) {
                        DeviceToken::removeToken($token);
                        Log::info('[FCM] Removed invalid token: ' . $token);
                    }
                }
            }

            Log::info('[FCM] Sent: ' . $report->successes()->count() . ' success, ' . $report->failures()->count() . ' failed.');

        } catch (\Exception $e) {
            Log::error('[FCM] Fatal error: ' . $e->getMessage());
        }
    }
}
