<?php

namespace App\Notifications;

use App\Models\Festival;
use App\Models\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a Subscriber successfully subscribes to a festival.
 *
 * Goal: confirm the subscription was recorded, remind the user which
 * notification type they chose, and tell them where to manage it.
 */
class FestivalSubscribedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Festival $festival,
        public string $notificationType,
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
        $typeLabel = match ($this->notificationType) {
            'opening' => 'cuando abra la convocatoria',
            'deadline' => 'antes del deadline',
            'both' => 'cuando abra la convocatoria y antes del deadline',
            default => 'segun corresponda',
        };

        $category = $this->festival->category
            ? ucfirst(str_replace('_', ' ', $this->festival->category))
            : null;
        $country = $this->festival->country;
        $deadline = $this->festival->deadline
            ? $this->festival->deadline->format('M d, Y')
            : null;

        $mail = (new MailMessage)
            ->subject("Suscripción confirmada: {$this->festival->name}")
            ->greeting("Hola, {$notifiable->name},")
            ->line("Tu suscripción al festival \"{$this->festival->name}\" se registró correctamente.")
            ->line("Recibiras notificaciones {$typeLabel}.");

        if ($category) {
            $mail->line("**Categoria:** {$category}");
        }
        if ($country) {
            $mail->line("**Pais:** {$country}");
        }
        if ($deadline) {
            $mail->line("**Deadline:** {$deadline}");
        }

        $mail
            ->action('Ver festival', url('/festivals/' . $this->festival->id))
            ->line('---')
            ->line('Podes cambiar o cancelar tu suscripcion desde tu panel.');

        return $mail;
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
            'notification_type' => $this->notificationType,
            'subscriber_id' => $notifiable->id,
            'type' => 'festival_subscribed',
        ];
    }
}
