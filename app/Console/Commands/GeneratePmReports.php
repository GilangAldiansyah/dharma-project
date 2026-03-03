<?php

namespace App\Console\Commands;

use App\Services\PmScheduleService;
use Illuminate\Console\Command;

class GeneratePmReports extends Command
{
    protected $signature   = 'pm:generate {--month= : Bulan (1-12)} {--year= : Tahun}';
    protected $description = 'Generate PM report plans untuk bulan tertentu';

    public function __construct(protected PmScheduleService $service)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $month = (int) ($this->option('month') ?? now()->month);
        $year  = (int) ($this->option('year')  ?? now()->year);

        $this->info("Generating PM reports untuk bulan {$month}/{$year}...");
        $this->service->generateForMonth($month, $year);
        $this->info('Selesai.');
    }
}
