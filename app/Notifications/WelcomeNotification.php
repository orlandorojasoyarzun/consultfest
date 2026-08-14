<?php

namespace App\Notifications;

use App\Models\Subscriber;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent once when a Subscriber creates their account.
 *
 * Goal: confirm the registration landed, give the user a direct link
 * to their dashboard, and outline what they can do next. Kept short on
 * purpose — this is the first impression, not a feature tour.
 *
 * Synchronous on purpose: this deploy runs no queue worker, so a queued
 * notification would land in the `jobs` table and never get sent. For a
 * demo of 1–2 users the sync path is fine — it adds ~1–2s to the
 * registration request (SMTP round-trip to Gmail/Resend). When traffic
 * warrants it, swap in a worker service and re-add `implements ShouldQueue`.
 */
class WelcomeNotification extends Notification
{

    public function __construct(
        public Subscriber $subscriber,
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
            ->subject('¡Bienvenido a Consultfest!')
            ->greeting('¡Hola, '.$this->subscriber->name.'!')
            ->line('Gracias por crear tu cuenta en Consultfest. Ya puedes empezar a explorar festivales de cine en todo el mundo.')
            ->line('Con tu cuenta puedes:')
            ->line('• Buscar festivales por fecha, categoría, género o país.')
            ->line('• Crear tus producciones y recibir matches personalizados.')
            ->line('• Suscribirte a festivales para recibir recordatorios automáticos de deadlines.')
            ->action('Ir a mi panel', url('/dashboard'))
            ->line('Si no creaste esta cuenta, puedes ignorar este mensaje.');
    }

    /**
     * @param  Subscriber  $notifiable
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'subscriber_id' => $this->subscriber->id,
            'subscriber_email' => $this->subscriber->email,
            'type' => 'welcome',
        ];
    }
}
