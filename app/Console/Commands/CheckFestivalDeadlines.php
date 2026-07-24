<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

class CheckFestivalDeadlines extends Command
{
    protected $signature = 'festivals:check-deadlines {--days= : Specific number of days ahead}';
    protected $description = 'Check festival deadlines and send notifications';

    public function handle(NotificationService $service): int
    {
        $days = $this->option('days') ? (int) $this->option('days') : null;

        if ($days) {
            $this->info("Checking deadlines for {$days} days ahead...");
            $deadlineNotified = $service->checkAndNotifyDeadline($days);
            $openingNotified = $service->checkAndNotifyOpening($days);

            $this->info("Sent {$deadlineNotified->count()} deadline notifications.");
            $this->info("Sent {$openingNotified->count()} opening notifications.");
        } else {
            $this->info('Checking all configured anticipation days...');

            foreach ([7, 14, 30] as $anticipationDay) {
                $this->line("Checking {$anticipationDay} days ahead...");
                $deadlineNotified = $service->checkAndNotifyDeadline($anticipationDay);
                $openingNotified = $service->checkAndNotifyOpening($anticipationDay);

                $this->line("  - Deadline notifications: {$deadlineNotified->count()}");
                $this->line("  - Opening notifications: {$openingNotified->count()}");
            }
        }

        return Command::SUCCESS;
    }
}
