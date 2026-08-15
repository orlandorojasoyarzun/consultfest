<?php

namespace App\Notifications;

use App\Models\Festival;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Cron-driven "deadline approaching" reminder. Dispatched by
 * NotificationService::checkAndNotifyDeadline() when a festival's
 * deadline is N days ahead and at least one subscriber is watching.
 *
 * Synchronous on purpose: this deploy runs no queue worker, so a queued
 * notification would land in the `jobs` table and never get sent. When
 * traffic warrants it, swap in a worker service and re-add
 * `implements ShouldQueue`.
 */
class FestivalDeadlineNotification extends Notification
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
        $deadline = $this->festival->deadline->format('M d, Y');

        return (new MailMessage)
            ->subject("Deadline del festival en {$this->daysAhead} días: {$this->festival->name}")
            ->greeting("Hola {$notifiable->name},")
            ->line("**{$this->festival->name}** tiene deadline en **{$this->daysAhead} días** ({$deadline}).")
            ->line("**Categoría:** {$this->festival->category}")
            ->line("**País:** {$this->festival->country}")
            ->line("**Tasa de admisión:** $" . number_format($this->festival->submission_fee, 2))
            ->action('Ver los detalles del festival', url('/festivals/' . $this->festival->id))
            ->line('Inicia sesión en Consultfest para manejar el estado de tus subcripciones.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'festival_id' => $this->festival->id,
            'festival_name' => $this->festival->name,
            'deadline' => $this->festival->deadline->toDateString(),
            'days_ahead' => $this->daysAhead,
            'type' => 'deadline',
        ];
    }
}
