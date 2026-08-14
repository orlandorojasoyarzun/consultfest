<?php

namespace App\Notifications;

use App\Models\Festival;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FestivalOpeningNotification extends Notification implements ShouldQueue
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
