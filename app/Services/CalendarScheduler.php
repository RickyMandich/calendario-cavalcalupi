<?php

namespace App\Services;

use App\Exceptions\SchedulingException;
use App\Models\Event;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Applica le regole del calendario (stanze, capienza, assemblee) a ogni scrittura.
 * Tutti i percorsi applicativi (form, scambio, import) passano da qui; il modello Event
 * NON applica queste regole, così seeder, test e tinker non vengono bloccati.
 */
class CalendarScheduler
{
    protected function tz(): string
    {
        return config('app.timezone');
    }

    /** Converte stringhe/Carbon in Carbon nel fuso dell'app. */
    protected function toCarbon(mixed $value): Carbon
    {
        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value)->setTimezone($this->tz());
        }

        return Carbon::parse($value, $this->tz());
    }

    public function capacityOn(CarbonInterface $date): int
    {
        return (int) Room::active()->usableOn($date->dayOfWeekIso)->sum('capacity');
    }

    /**
     * Sessioni che occupano uno slot nel giorno (conta il giorno di inizio).
     *
     * @param  iterable<array{type:string,start:CarbonInterface,end:CarbonInterface}>  $extra  pseudo-eventi (import)
     */
    public function occupiedSlotsOn(CarbonInterface $date, ?Event $ignore = null, iterable $extra = []): int
    {
        $dayStart = $this->toCarbon($date)->startOfDay();
        $dayEnd = $dayStart->copy()->endOfDay();

        $query = Event::where('type', 'session')->whereBetween('start_time', [$dayStart, $dayEnd]);
        if ($ignore) {
            $query->whereKeyNot($ignore->getKey());
        }
        $count = $query->count();

        foreach ($extra as $e) {
            if (($e['type'] ?? null) === 'session' && $this->toCarbon($e['start'])->between($dayStart, $dayEnd)) {
                $count++;
            }
        }

        return $count;
    }

    public function freeSlotsOn(CarbonInterface $date, ?Event $ignore = null, iterable $extra = []): int
    {
        return max(0, $this->capacityOn($date) - $this->occupiedSlotsOn($date, $ignore, $extra));
    }

    /**
     * Esistono eventi (dei tipi indicati) sovrapposti all'intervallo? Estremi adiacenti NON si sovrappongono.
     */
    public function overlapping(CarbonInterface $start, CarbonInterface $end, ?array $types = null, ?Event $ignore = null, iterable $extra = []): bool
    {
        $query = Event::where('start_time', '<', $end)->where('end_time', '>', $start);
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
            $eStart = $this->toCarbon($e['start']);
            $eEnd = $this->toCarbon($e['end']);
            if ($eStart < $end && $eEnd > $start && (! $types || in_array($e['type'] ?? null, $types, true))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array  $attrs  almeno type, start_time, end_time (stringhe o Carbon)
     *
     * @throws SchedulingException
     */
    public function validate(array $attrs, ?Event $ignore = null, iterable $extra = []): void
    {
        $type = $attrs['type'] ?? null;
        $start = $this->toCarbon($attrs['start_time']);
        $end = $this->toCarbon($attrs['end_time']);

        if ($end <= $start) {
            throw new SchedulingException("L'orario di fine deve essere successivo a quello di inizio.", 'end_time');
        }

        $day = $start->copy()->startOfDay();

        if ($type === 'session') {
            $capacity = $this->capacityOn($day);
            if ($capacity === 0) {
                throw new SchedulingException('Nessuna stanza disponibile in questo giorno.', 'start_time', 'no_rooms');
            }
            $occupied = $this->occupiedSlotsOn($day, $ignore, $extra);
            if ($occupied >= $capacity) {
                throw new SchedulingException("Giorno pieno: {$occupied}/{$capacity} posti occupati.", 'start_time', 'full');
            }
            if ($this->overlapping($start, $end, ['assembly'], $ignore, $extra)) {
                throw new SchedulingException("Nel giorno e orario scelto c'è un'assemblea.", 'start_time', 'assembly');
            }
        } elseif ($type === 'assembly') {
            if ($this->capacityOn($day) === 0) {
                throw new SchedulingException('Nessuna stanza disponibile in questo giorno.', 'start_time', 'no_rooms');
            }
            if ($this->overlapping($start, $end, null, $ignore, $extra)) {
                throw new SchedulingException("Esistono altri eventi nello stesso orario: l'assemblea li esclude.", 'start_time', 'overlap');
            }
        } elseif ($type === 'association_event') {
            if ($this->overlapping($start, $end, ['assembly'], $ignore, $extra)) {
                throw new SchedulingException("Un evento associativo non può sovrapporsi a un'assemblea.", 'start_time', 'assembly');
            }
        }
    }

    /**
     * Transazione il cui PRIMO statement è il lock sulle stanze (mutex contro richieste concorrenti).
     */
    public function withLock(callable $callback): mixed
    {
        return DB::transaction(function () use ($callback) {
            Room::query()->lockForUpdate()->get(['id']);

            return $callback();
        });
    }

    public function createEvent(array $attrs, User $by): Event
    {
        return $this->withLock(function () use ($attrs, $by) {
            $this->validate($attrs);

            return Event::create(array_merge($attrs, ['created_by' => $by->id]));
        });
    }

    /**
     * Aggiorna un evento. Capienza/assemblee si rivalidano solo se cambiano le date.
     * Se $actor non è staff non si possono spostare sessioni già iniziate o verso il passato.
     */
    public function updateEvent(Event $event, array $attrs, ?User $actor = null): Event
    {
        return $this->withLock(function () use ($event, $attrs, $actor) {
            $originalStart = $event->start_time ? $this->toCarbon($event->start_time) : null;
            $event->fill($attrs);

            if ($event->isDirty(['start_time', 'end_time'])) {
                if ($actor && ! $actor->isStaff()) {
                    $now = now($this->tz());
                    if (($originalStart && $originalStart < $now) || $this->toCarbon($event->start_time) < $now) {
                        throw new SchedulingException('Non si possono spostare sessioni già iniziate o verso il passato.', 'start_time', 'past');
                    }
                }

                $this->validate([
                    'type' => $event->type,
                    'start_time' => $event->start_time,
                    'end_time' => $event->end_time,
                ], $event);
            }

            $event->save();

            return $event;
        });
    }

    /**
     * Disponibilità giorno per giorno (usata da form, JSON e FullCalendar).
     *
     * @return array<int, array{date:string,capacity:int,occupied:int,free:int,over_capacity:bool,has_assembly:bool}>
     */
    public function dayAvailability(CarbonInterface $from, CarbonInterface $to): array
    {
        $start = Carbon::parse($from->toDateString(), $this->tz())->startOfDay();
        $end = Carbon::parse($to->toDateString(), $this->tz())->endOfDay();

        $capacityByWeekday = array_fill(1, 7, 0);
        Room::active()->with('weekdays')->get()->each(function (Room $room) use (&$capacityByWeekday) {
            foreach ($room->weekdays as $w) {
                $capacityByWeekday[$w->weekday] += $room->capacity;
            }
        });

        $sessions = Event::where('type', 'session')
            ->whereBetween('start_time', [$start, $end])
            ->get(['id', 'start_time'])
            ->groupBy(fn (Event $e) => $e->start_time->toDateString());

        $assemblies = Event::where('type', 'assembly')
            ->whereBetween('start_time', [$start, $end])
            ->get(['id', 'start_time'])
            ->groupBy(fn (Event $e) => $e->start_time->toDateString());

        $days = [];
        for ($day = $start->copy(); $day <= $end; $day->addDay()) {
            $key = $day->toDateString();
            $capacity = $capacityByWeekday[$day->dayOfWeekIso];
            $occupied = isset($sessions[$key]) ? $sessions[$key]->count() : 0;
            $days[] = [
                'date' => $key,
                'capacity' => $capacity,
                'occupied' => $occupied,
                'free' => max(0, $capacity - $occupied),
                'over_capacity' => $occupied > $capacity,
                'has_assembly' => isset($assemblies[$key]),
            ];
        }

        return $days;
    }
}
