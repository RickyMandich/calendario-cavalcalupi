<?php

namespace App\Notifications;

use App\Models\Event;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Inviata quando una richiesta di cambio giorno rimasta in attesa viene eseguita
 * perché si è liberato un posto nel giorno richiesto.
 */
class SessionMoved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Event $event,
        public CarbonInterface $oldStart,
        public CarbonInterface $newStart,
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
            ->subject('Cambio giorno eseguito')
            ->greeting('Ciao '.$notifiable->name.',')
            ->line("Si è liberato un posto: la sessione «{$this->event->title}» del gruppo {$groupName} passa da {$this->oldStart->format('d/m/Y H:i')} a {$this->newStart->format('d/m/Y H:i')}, come richiesto.")
            ->action('Apri la sessione', route('events.show', $this->event));
    }
}
