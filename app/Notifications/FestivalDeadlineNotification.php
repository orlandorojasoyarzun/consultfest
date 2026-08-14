<?php

namespace App\Notifications;

use App\Models\Festival;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FestivalDeadlineNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
