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
            ->subject("🎬 Festival Opens in {$this->daysAhead} days: {$this->festival->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line("**{$this->festival->name}** opens for submissions in **{$this->daysAhead} days** ({$openingDate}).")
            ->line("**Category:** {$this->festival->category}")
            ->line("**Country:** {$this->festival->country}")
            ->line("**Submission Fee:** $" . number_format($this->festival->submission_fee, 2))
            ->action('View Festival Details', url('/festivals/' . $this->festival->id))
            ->line('Start preparing your submission materials!');
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
