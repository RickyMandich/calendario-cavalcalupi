<?php

namespace App\Notifications;

use App\Models\Event;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SessionSwapped extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Event $event,
        public CarbonInterface $oldStart,
        public CarbonInterface $newStart,
        public ?string $otherTitle,
        public ?string $otherGroup,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $groupName = $this->event->group?->name ?? 'il tuo gruppo';

        return (new MailMessage)
            ->subject('Scambio sessione confermato')
            ->greeting('Ciao '.$notifiable->name.',')
            ->line("La sessione «{$this->event->title}» del gruppo {$groupName} passa da {$this->oldStart->format('d/m/Y H:i')} a {$this->newStart->format('d/m/Y H:i')}.")
            ->line('Scambio concordato con la sessione «'.($this->otherTitle ?? '-').'» del gruppo '.($this->otherGroup ?? '-').'.')
            ->action('Apri la sessione', route('events.show', $this->event));
    }
}
