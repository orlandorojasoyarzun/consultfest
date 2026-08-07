<?php

namespace App\Services;

use App\Models\Festival;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Notifications\FestivalDeadlineNotification;
use App\Notifications\FestivalOpeningNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function checkAndNotifyDeadline(int $daysAhead): Collection
    {
        $targetDate = now()->addDays($daysAhead)->toDateString();

        $festivals = Festival::whereDate('deadline', $targetDate)
            ->where('accepting_submissions', true)
            ->get();

        $notified = collect();

        foreach ($festivals as $festival) {
            $subscriptions = $this->getSubscriptionsToNotify($festival, 'deadline');

            foreach ($subscriptions as $subscription) {
                $subscriber = $subscription->subscriber;

                if (!$subscriber->notifications_enabled) {
                    continue;
                }

                if ($subscription->notified_deadline) {
                    continue;
                }

                try {
                    $subscriber->notify(new FestivalDeadlineNotification($festival, $daysAhead));
                    // Direct attribute write: notified_* is in $guarded on Subscription,
                    // so update([...]) would be silently rejected. The system is the
                    // only legitimate writer of these flags.
                    $subscription->notified_deadline = true;
                    $subscription->save();
                    $notified->push([
                        'subscriber_id' => $subscriber->id,
                        'festival_id' => $festival->id,
                        'type' => 'deadline',
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send deadline notification', [
                        'subscriber_id' => $subscriber->id,
                        'festival_id' => $festival->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $notified;
    }

    public function checkAndNotifyOpening(int $daysAhead): Collection
    {
        $targetDate = now()->addDays($daysAhead)->toDateString();

        $festivals = Festival::whereDate('opening_date', $targetDate)
            ->where('accepting_submissions', true)
            ->get();

        $notified = collect();

        foreach ($festivals as $festival) {
            $subscriptions = $this->getSubscriptionsToNotify($festival, 'opening');

            foreach ($subscriptions as $subscription) {
                $subscriber = $subscription->subscriber;

                if (!$subscriber->notifications_enabled) {
                    continue;
                }

                if ($subscription->notified_opening) {
                    continue;
                }

                try {
                    $subscriber->notify(new FestivalOpeningNotification($festival, $daysAhead));
                    // See note in checkAndNotifyDeadline: notified_* is $guarded.
                    $subscription->notified_opening = true;
                    $subscription->save();
                    $notified->push([
                        'subscriber_id' => $subscriber->id,
                        'festival_id' => $festival->id,
                        'type' => 'opening',
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send opening notification', [
                        'subscriber_id' => $subscriber->id,
                        'festival_id' => $festival->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $notified;
    }

    private function getSubscriptionsToNotify(Festival $festival, string $type): Collection
    {
        return Subscription::where('festival_id', $festival->id)
            ->where(function ($query) use ($type) {
                if ($type === 'deadline') {
                    $query->where('notification_type', 'deadline')
                          ->orWhere('notification_type', 'both');
                } else {
                    $query->where('notification_type', 'opening')
                          ->orWhere('notification_type', 'both');
                }
            })
            ->with('subscriber')
            ->get();
    }
}
