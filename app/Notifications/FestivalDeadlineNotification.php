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
            ->subject("⏰ Festival Deadline in {$this->daysAhead} days: {$this->festival->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line("**{$this->festival->name}** has a submission deadline in **{$this->daysAhead} days** ({$deadline}).")
            ->line("**Category:** {$this->festival->category}")
            ->line("**Country:** {$this->festival->country}")
            ->line("**Submission Fee:** $" . number_format($this->festival->submission_fee, 2))
            ->action('View Festival Details', url('/festivals/' . $this->festival->id))
            ->line('Log in to Consultfest to manage your submission status.');
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
