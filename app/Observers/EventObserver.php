<?php

namespace App\Observers;

use App\Models\Event;
use App\Models\EventLog;
use Illuminate\Support\Facades\Auth;

class EventObserver
{
    /**
     * Handle the Event "created" event.
     */
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

    /**
     * Handle the Event "updating" event.
     */
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
            $oldData[$key] = $oldValue instanceof \DateTimeInterface ? $oldValue->toIso8601String() : $oldValue;
            $newData[$key] = $newValue instanceof \DateTimeInterface ? $newValue->toIso8601String() : $newValue;
        }

        EventLog::create([
            'event_id' => $event->id,
            'user_id' => Auth::id() ?? $event->created_by,
            'action' => $action,
            'old_data' => $oldData,
            'new_data' => $newData,
        ]);
    }
}
