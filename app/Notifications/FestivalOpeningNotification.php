<?php

namespace App\Notifications;

use App\Models\Festival;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Cron-driven "submissions open" reminder. Dispatched by
 * NotificationService::checkAndNotifyOpening() when a festival's
 * opening_date is N days ahead and at least one subscriber is watching.
 *
 * Synchronous on purpose: this deploy runs no queue worker, so a queued
 * notification would land in the `jobs` table and never get sent. When
 * traffic warrants it, swap in a worker service and re-add
 * `implements ShouldQueue`.
 */
class FestivalOpeningNotification extends Notification
{

    public function __construct(
        public Festival $festival,
        public int $daysAhead
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $openingDate = $this->festival->opening_date->format('M d, Y');

        return (new MailMessage)
            ->subject("Festival abre en {$this->daysAhead} días: {$this->festival->name}")
            ->greeting("Hola {$notifiable->name},")
            ->line("**{$this->festival->name}** abre para admisiones en **{$this->daysAhead} días** ({$openingDate}).")
            ->line("**Categoría:** {$this->festival->category}")
            ->line("**País:** {$this->festival->country}")
            ->line("**Tasa de admisión:** $" . number_format($this->festival->submission_fee, 2))
            ->action('Ver los detalles del festival', url('/festivals/' . $this->festival->id))
            ->line('Empieza a preparar tu postulación!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'festival_id' => $this->festival->id,
            'festival_name' => $this->festival->name,
            'opening_date' => $this->festival->opening_date->toDateString(),
            'days_ahead' => $this->daysAhead,
            'type' => 'opening',
        ];
    }
}
