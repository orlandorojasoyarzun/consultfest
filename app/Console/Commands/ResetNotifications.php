<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;

class ResetNotifications extends Command
{
    protected $signature = 'festivals:reset-notifications
                            {--subscriber= : Reset only this subscriber id}';

    protected $description = 'Reset notified_opening/notified_deadline flags so notifications can fire again.';

    public function handle(): int
    {
        // DB::table bypasses Eloquent and ignores $guarded by design —
        // this command is a system-level reset, not a user-driven write.
        $query = \DB::table('subscriptions');

        if ($id = $this->option('subscriber')) {
            $query->where('subscriber_id', $id);
        }

        $count = $query->update([
            'notified_opening' => false,
            'notified_deadline' => false,
        ]);

        $this->info("Reset {$count} subscription(s).");

        return self::SUCCESS;
    }
}