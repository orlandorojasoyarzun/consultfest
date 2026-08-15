<?php

namespace App\Services;

use App\Models\Festival;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Notifications\FestivalDeadlineNotification;
use App\Notifications\FestivalOpeningNotification;
use Illuminate\Notifications\Notification;
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

                if ($this->safeNotify($subscriber, new FestivalDeadlineNotification($festival, $daysAhead))) {
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

                if ($this->safeNotify($subscriber, new FestivalOpeningNotification($festival, $daysAhead))) {
                    // See note in checkAndNotifyDeadline: notified_* is $guarded.
                    $subscription->notified_opening = true;
                    $subscription->save();
                    $notified->push([
                        'subscriber_id' => $subscriber->id,
                        'festival_id' => $festival->id,
                        'type' => 'opening',
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

    /**
     * Send a notification resilient to mail-driver failures.
     *
     * Returns true on success, false on failure. Failure is logged at
     * `error` level (visible in `LOG_CHANNEL=stderr` on Railway) so a
     * transient Resend outage doesn't take down the user action.
     *
     * Also logs a successful send at `info` level — the only paper trail
     * for "what did we actually send and to whom". Production staff can
     * `railway logs | grep "Notification sent"` to audit.
     */
    public function safeNotify(Subscriber $subscriber, Notification $notification): bool
    {
        $class = $notification::class;

        try {
            $subscriber->notify($notification);
            Log::info('Notification sent', [
                'notification' => $class,
                'subscriber_id' => $subscriber->id,
                'subscriber_email' => $subscriber->email,
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to send notification', [
                'notification' => $class,
                'subscriber_id' => $subscriber->id,
                'subscriber_email' => $subscriber->email,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
