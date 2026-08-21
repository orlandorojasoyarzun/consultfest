<?php

namespace App\Services;

use App\Models\Festival;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Notifications\FestivalSubscribedNotification;
use App\Notifications\FestivalUnsubscribedNotification;

/**
 * Subscribe / unsubscribe a subscriber to a festival.
 *
 * Exists because both the HTTP controller (FestivalController::subscribe)
 * and the inline Livewire modal (FestivalResults::confirmSubscribe) need
 * to perform the exact same operation — and earlier the Livewire path
 * was calling the controller via Http::post() loopback, which deadlocks
 * the PHP session lock for up to max_execution_time (30s+). Calling the
 * logic directly through this service avoids the round-trip entirely.
 *
 * Same reason for unsubscribe(): the Livewire UnsubscribeFestivalModal
 * shares this path with FestivalController::unsubscribe so both
 * entrypoints delete the same row and fire the same notification.
 */
class FestivalSubscriptionService
{
    public function __construct(
        private FestivalApiService $api,
        private NotificationService $notifications,
    ) {}

    /**
     * Result envelope: success() for ok, error() for failure.
     * Callers render these into either a JSON response (controller) or
     * Livewire state (modal). Keeping it as a value object (not throwing)
     * means the Livewire branch doesn't need a try/catch for expected
     * outcomes like "festival not found in FestivalAPI".
     */
    public function subscribe(int $subscriberId, int $festivalApiId, string $notificationType): SubscribeResult
    {
        $festival = Festival::where('api_id', $festivalApiId)->first();

        // Sync on-demand: festival is in FestivalAPI but not in our local DB
        // (normal case after migrate:fresh). Costs 1 FestivalAPI credit.
        if (!$festival) {
            $synced = $this->api->syncFestivalDetails($festivalApiId);
            if (!$synced) {
                return SubscribeResult::error('Festival no encontrado en FestivalAPI.');
            }
            $festival = Festival::where('api_id', $festivalApiId)->first();
            if (!$festival) {
                return SubscribeResult::error('No pudimos guardar el festival después de sincronizarlo.');
            }
        }

        Subscription::updateOrCreate(
            [
                'subscriber_id' => $subscriberId,
                'festival_id' => $festival->id,
            ],
            [
                'notification_type' => $notificationType,
            ],
        );

        // Confirmation email. safeNotify() catches mail failures so a Resend
        // outage doesn't surface as a 500 — the subscription already landed.
        $subscriber = Subscriber::find($subscriberId);
        if ($subscriber) {
            $this->notifications->safeNotify(
                $subscriber,
                new FestivalSubscribedNotification($festival, $notificationType),
            );
        }

        return SubscribeResult::success();
    }

    /**
     * Idempotent unsubscribe. If the festival isn't in the local DB the
     * user can't possibly be subscribed — return success without
     * notifying. If a subscription row exists, delete it and send the
     * unsubscribe email only when there was something to remove (an
     * idempotent endpoint shouldn't spam a user who hit unsubscribe for
     * a festival they were never on).
     *
     * Returns true iff a row was actually deleted. The HTTP and Livewire
     * callers use this to decide whether to flash a "Te desuscribiste"
     * message vs. a quieter "No estabas suscrito".
     */
    public function unsubscribe(int $subscriberId, int $festivalApiId): UnsubscribeResult
    {
        $festival = Festival::where('api_id', $festivalApiId)->first();

        if (!$festival) {
            return UnsubscribeResult::noop('Ese festival no está en nuestro catálogo — no estabas suscrito.');
        }

        $deleted = Subscription::where('subscriber_id', $subscriberId)
            ->where('festival_id', $festival->id)
            ->delete();

        if ($deleted === 0) {
            return UnsubscribeResult::noop('No estabas suscrito a ' . $festival->name . '.');
        }

        $subscriber = Subscriber::find($subscriberId);
        if ($subscriber) {
            $this->notifications->safeNotify(
                $subscriber,
                new FestivalUnsubscribedNotification($festival),
            );
        }

        return UnsubscribeResult::deleted($festival->name);
    }
}
