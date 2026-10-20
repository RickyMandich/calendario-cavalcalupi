<?php

namespace App\Services;

use App\Exceptions\SchedulingException;
use App\Models\Event;
use App\Models\Room;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class CalendarScheduler
{
    /**
     * Get total capacity for a given date.
     */
    public function capacityOn(CarbonInterface $date): int
    {
        // Sum capacities of active rooms usable on the weekday of the date
        return (int) Room::active()
            ->usableOn($date->dayOfWeekIso)
            ->sum('capacity');
    }

    /**
     * Count occupied slots (sessions) on a given date.
     *
     * @param Event|null $ignore Event to ignore (e.g., when updating the same event)
     * @param iterable $extra Additional pseudo‑events supplied as ['type'=>..., 'start'=>Carbon, 'end'=>Carbon]
     */
    public function occupiedSlotsOn(CarbonInterface $date, ?Event $ignore = null, iterable $extra = []): int
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd   = $date->copy()->endOfDay();

        $query = Event::where('type', 'session')
            ->whereBetween('start_time', [$dayStart, $dayEnd]);

        if ($ignore) {
            $query->whereKeyNot($ignore->getKey());
        }

        $count = $query->count();

        foreach ($extra as $e) {
            if (($e['type'] ?? null) === 'session') {
                $start = $e['start'];
                $end   = $e['end'];
                if ($start->between($dayStart, $dayEnd) || $end->between($dayStart, $dayEnd) || ($start->lt($dayStart) && $end->gt($dayEnd))) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Free slots on a date.
     */
    public function freeSlotsOn(CarbonInterface $date, ?Event $ignore = null, iterable $extra = []): int
    {
        $free = $this->capacityOn($date) - $this->occupiedSlotsOn($date, $ignore, $extra);
        return $free > 0 ? $free : 0;
    }

    /**
     * Determine if a time interval overlaps existing events.
     */
    public function overlapping(CarbonInterface $start, CarbonInterface $end, ?array $types = null, ?Event $ignore = null, iterable $extra = []): bool
    {
        $query = Event::where(function (Builder $q) use ($start, $end) {
            $q->where('start_time', '<', $end)
                ->where('end_time', '>', $start);
        });

        if ($types) {
            $query->whereIn('type', $types);
        }

        if ($ignore) {
            $query->whereKeyNot($ignore->getKey());
        }

        if ($query->exists()) {
            return true;
        }

        foreach ($extra as $e) {
            $eStart = $e['start'];
            $eEnd   = $e['end'];
            if ($eStart < $end && $eEnd > $start) {
                if (!$types || in_array($e['type'] ?? null, $types, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Validate attributes according to calendar rules.
     *
     * @throws SchedulingException
     */
    public function validate(array $attrs, ?Event $ignore = null, iterable $extra = []): void
    {
        $type = $attrs['type'] ?? null;
        $start = $attrs['start_time'];
        $end   = $attrs['end_time'];

        $date = $start->copy()->startOfDay();
        $capacity = $this->capacityOn($date);
        $occupied = $this->occupiedSlotsOn($date, $ignore, $extra);

        if ($type === 'session') {
            if ($capacity === 0) {
                throw new SchedulingException('Nessuna stanza disponibile in questo giorno.', 'start_time');
            }
            if ($occupied >= $capacity) {
                throw new SchedulingException("Giorno pieno: {$occupied}/{$capacity} posti occupati.", 'start_time');
            }
            if ($this->overlapping($start, $end, ['assembly'], $ignore, $extra)) {
                throw new SchedulingException('Nel giorno e orario scelto c\'è un\'assemblea.', 'start_time');
            }
        } elseif ($type === 'assembly') {
            if ($capacity === 0) {
                throw new SchedulingException('Nessuna stanza disponibile in questo giorno.', 'start_time');
            }
            if ($this->overlapping($start, $end, null, $ignore, $extra)) {
                throw new SchedulingException('Esistono altri eventi nello stesso orario: l\'assemblea li esclude.', 'start_time');
            }
        } elseif ($type === 'association_event') {
            if ($this->overlapping($start, $end, ['assembly'], $ignore, $extra)) {
                throw new SchedulingException('Un evento associativo non può sovrapporsi a un\'assemblea.', 'start_time');
            }
        }
    }

    /**
     * Execute a closure inside a transaction with the first query locking rooms.
     */
    public function withLock(callable $cb)
    {
        return DB::transaction(function () use ($cb) {
            // First query to acquire row locks on rooms
            Room::query()->lockForUpdate()->get(['id']);
            return $cb();
        });
    }

    /**
     * Create an event using the scheduler logic.
     */
    public function createEvent(array $attrs, $by)
    {
        return $this->withLock(function () use ($attrs, $by) {
            $this->validate($attrs);
            $event = Event::create(array_merge($attrs, ['created_by' => $by->id]));
            return $event;
        });
    }

    /**
     * Update an existing event.
     */
    public function updateEvent(Event $event, array $attrs)
    {
        return $this->withLock(function () use ($event, $attrs) {
            $event->fill($attrs);
            if ($event->isDirty(['start_time', 'end_time'])) {
                $this->validate($event->getAttributes(), $event);
            }
            $event->save();
            return $event;
        });
    }

    /**
     * Return day‑by‑day availability between two dates.
     *
     * @return array Each element: ['date'=>Carbon,'capacity'=>int,'occupied'=>int,'free'=>int,'over_capacity'=>bool,'has_assembly'=>bool]
     */
    public function dayAvailability(CarbonInterface $from, CarbonInterface $to): array
    {
        $days = [];
        $period = \Carbon\CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay());
        foreach ($period as $day) {
            $capacity = $this->capacityOn($day);
            $occupied = $this->occupiedSlotsOn($day);
            $free = max(0, $capacity - $occupied);
            $hasAssembly = Event::where('type', 'assembly')
                ->whereBetween('start_time', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
                ->exists();
            $days[] = [
                'date' => $day->toDateString(),
                'capacity' => $capacity,
                'occupied' => $occupied,
                'free' => $free,
                'over_capacity' => $occupied > $capacity,
                'has_assembly' => $hasAssembly,
            ];
        }
        return $days;
    }
}
?>
