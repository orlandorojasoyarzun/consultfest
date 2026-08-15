<?php

namespace App\Notifications;

use App\Models\Festival;
use App\Models\Subscriber;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a Subscriber unsubscribes from a festival.
 *
 * Goal: confirm the desubscription landed so the user isn't left wondering
 * whether they'll still get emails. Also nudges them back to the catalogue
 * in case they want to subscribe to other festivals.
 *
 * Synchronous on purpose: this deploy runs no queue worker, so a queued
 * notification would land in the `jobs` table and never get sent. When
 * traffic warrants it, swap in a worker service and re-add
 * `implements ShouldQueue`.
 */
class FestivalUnsubscribedNotification extends Notification
{

    public function __construct(
        public Festival $festival,
    ) {
    }

    /**
     * @param  Subscriber  $notifiable
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * @param  Subscriber  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Desuscripción confirmada: {$this->festival->name}")
            ->greeting("Hola, {$notifiable->name},")
            ->line("Tu desuscripción al festival \"{$this->festival->name}\" se procesó correctamente.")
            ->line('Ya no vas a recibir recordatorios automaticos de este festival.')
            ->line('Puedes volver a suscribirte en cualquier momento desde tu panel.')
            ->action('Explorar festivales', url('/festivals'))
            ->line('---')
            ->line('Consultfest.');
    }

    /**
     * @param  Subscriber  $notifiable
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'festival_id' => $this->festival->id,
            'festival_name' => $this->festival->name,
            'subscriber_id' => $notifiable->id,
            'type' => 'festival_unsubscribed',
        ];
    }
}
