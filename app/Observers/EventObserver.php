<?php

namespace App\Observers;

use App\Models\Event;
use App\Models\EventLog;
use App\Models\SessionSwapRequest;
use Illuminate\Support\Facades\Auth;

class EventObserver
{
    public function created(Event $event): void
    {
        EventLog::create([
            'event_id' => $event->id,
            'user_id' => Auth::id() ?? $event->created_by,
            'action' => 'created',
            'old_data' => null,
            'new_data' => [
                'title' => $event->title,
                'description' => $event->description,
                'start_time' => $event->start_time?->toIso8601String(),
                'end_time' => $event->end_time?->toIso8601String(),
                'type' => $event->type,
                'group_id' => $event->group_id,
            ],
        ]);
    }

    public function updating(Event $event): void
    {
        $dirty = $event->getDirty();
        if (empty($dirty)) {
            return;
        }

        $isRescheduled = $event->isDirty('start_time') || $event->isDirty('end_time');
        $action = $isRescheduled ? 'rescheduled' : 'updated';

        $oldData = [];
        $newData = [];

        foreach ($dirty as $key => $newValue) {
            $oldValue = $event->getOriginal($key);
            $oldData[$key] = $oldValue instanceof \DateTimeInterface ? $oldValue->format(\DateTimeInterface::ATOM) : $oldValue;
            $newData[$key] = $newValue instanceof \DateTimeInterface ? $newValue->format(\DateTimeInterface::ATOM) : $newValue;
        }

        if ($event->logContext !== null) {
            $newData['_context'] = $event->logContext;
        }

        EventLog::create([
            'event_id' => $event->id,
            'user_id' => Auth::id() ?? $event->created_by,
            'action' => $action,
            'old_data' => $oldData,
            'new_data' => $newData,
        ]);
    }

    /**
     * Se il GIORNO della sessione cambia, le richieste di scambio pendenti non hanno più senso
     * (un cambio di solo orario nello stesso giorno le lascia valide).
     * Lo scambio marca le richieste "completed" PRIMA di aggiornare gli eventi: qui non le trova.
     */
    public function updated(Event $event): void
    {
        if (! $event->wasChanged('start_time')) {
            return;
        }

        $old = $event->getOriginal('start_time');
        $dayChanged = ! $old || \Carbon\Carbon::parse($old)->toDateString() !== $event->start_time->toDateString();

        if ($dayChanged) {
            SessionSwapRequest::pending()
                ->where('event_id', $event->id)
                ->get()
                ->each(fn (SessionSwapRequest $r) => $r->markCancelled(null, 'session_changed'));
        }
    }
}
