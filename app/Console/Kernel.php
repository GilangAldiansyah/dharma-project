<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Check alerts setiap 5 menit
        $schedule->command('production:check-alerts')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        // Optional: Backup database setiap hari jam 2 pagi
        // $schedule->command('backup:run')->dailyAt('02:00');

        // Optional: Reset counter robot yang sudah completed
        // $schedule->call(function () {
        //     \App\Models\RobotCounter::where('is_completed', true)
        //         ->update(['counter' => 0, 'status' => 'idle']);
        // })->dailyAt('00:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
