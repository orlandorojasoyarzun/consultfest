<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a subscriber requests a password reset. We deliberately don't
 * extend the framework's `ResetPassword` notification because that one
 * assumes the notifiable is an Authenticatable tied to a Laravel user
 * provider — Subscriber isn't. Same UX, simpler contract.
 */
class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $resetUrl,
        public string $name,
    ) {
    }

    /**
     * @param  object  $notifiable
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * @param  object  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Restablece tu contraseña de Consultfest')
            ->greeting('Hola, '.$this->name)
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta.')
            ->action('Restablecer contraseña', $this->resetUrl)
            ->line('Este link expira en 60 minutos.')
            ->line('Si no solicitaste esto, puedes ignorar este mensaje.');
    }
}
