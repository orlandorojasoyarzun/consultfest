<?php

namespace App\Notifications;

use App\Models\Production;
use App\Models\Subscriber;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent right after a Subscriber creates a Production (short film, feature, etc.).
 *
 * Goal: confirm the record landed, surface a deep link to the production,
 * and nudge the user toward subscribing to matching festivals. Kept short
 * so it doesn't compete with the dashboard UX.
 *
 * Synchronous on purpose: this deploy runs no queue worker, so a queued
 * notification would land in the `jobs` table and never get sent. For a
 * demo of 1–2 users the sync path is fine — it adds ~1–2s to the
 * production creation request (SMTP round-trip to Resend). When traffic
 * warrants it, swap in a worker service and re-add `implements ShouldQueue`.
 */
class ProductionCreatedNotification extends Notification
{

    public function __construct(
        public Production $production,
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
        $category = $this->production->category
            ? ucfirst(str_replace('_', ' ', $this->production->category))
            : null;
        $country = $this->production->country;

        $mail = (new MailMessage)
            ->subject("Tu producción '{$this->production->title}' fue creada")
            ->greeting("Hola, {$notifiable->name},")
            ->line("Tu producción \"{$this->production->title}\" se guardo correctamente en Consultfest.");

        if ($category) {
            $mail->line("**Categoría:** {$category}");
        }
        if ($country) {
            $mail->line("**País:** {$country}");
        }

        $mail
            ->action('Ver mi producción', url('/productions/' . $this->production->id))
            ->line('Tip: Subscribete a festivales que coincidan con esta produccion para recibir recordatorios automaticos de deadlines.')
            ->line('---')
            ->line('Puedes desactivar las notificaciones desde tu panel.');

        return $mail;
    }

    /**
     * @param  Subscriber  $notifiable
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'production_id' => $this->production->id,
            'production_title' => $this->production->title,
            'subscriber_id' => $notifiable->id,
            'type' => 'production_created',
        ];
    }
}
