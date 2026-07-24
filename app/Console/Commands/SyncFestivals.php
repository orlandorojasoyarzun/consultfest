<?php

namespace App\Console\Commands;

use App\Services\FestivalApiService;
use Illuminate\Console\Command;

class SyncFestivals extends Command
{
    protected $signature = 'festivals:sync {--details : Also sync festival details}';
    protected $description = 'Sync festivals from FestivalAPI';

    public function handle(FestivalApiService $service): int
    {
        $this->info('Starting festival sync...');

        $result = $service->syncFestivals();

        $this->info("Synced {$result['synced']} festivals.");

        if ($this->option('details')) {
            $this->info('Syncing festival details is only supported for individual festivals via API.');
        }

        return Command::SUCCESS;
    }
}
