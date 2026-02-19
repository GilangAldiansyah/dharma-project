<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LineScheduleService;

class CheckLineSchedules extends Command
{
    protected $signature = 'line:check-schedules';

    protected $description = 'Check and apply auto pause/resume for all running line operations based on schedule';

    public function handle(LineScheduleService $scheduleService)
    {
        $this->info('Checking line schedules...');

        try {
            $scheduleService->checkAllRunningOperations();
            $this->info('Line schedules checked successfully');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error checking line schedules: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
