<?php

namespace Tests\Concerns;

use App\Models\Event;
use App\Models\Group;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;

/**
 * Helper condivisi dai test che passano dalle regole di calendario (stanze/capienza).
 */
trait CreatesRooms
{
    protected function createRoom(int $capacity = 1, array $weekdays = [1, 2, 3, 4, 5, 6, 7], bool $active = true): Room
    {
        $room = Room::create(['name' => 'Stanza '.uniqid(), 'capacity' => $capacity, 'is_active' => $active]);
        $room->syncWeekdays($weekdays);

        return $room;
    }

    /** Prossimo giorno della settimana ISO (1=lun ... 7=dom) a mezzanotte, almeno $weeksAhead settimane nel futuro. */
    protected function nextWeekday(int $iso, int $weeksAhead = 1): Carbon
    {
        return now()->startOfDay()->next($iso % 7)->addWeeks($weeksAhead - 1)->addWeek();
    }

    protected function ensureRoles(): void
    {
        foreach (['ADMIN', 'CD', 'Membro'] as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }

    protected function makeUser(string $role = 'Membro', array $attrs = []): User
    {
        $this->ensureRoles();

        $user = User::factory()->create($attrs);
        $user->assignRole($role);

        return $user;
    }

    protected function makeSession(Group $group, Carbon $day, string $start = '21:00', string $end = '23:00', string $title = 'Sessione'): Event
    {
        return Event::create([
            'title' => $title,
            'type' => 'session',
            'group_id' => $group->id,
            'start_time' => $day->copy()->setTimeFromTimeString($start),
            'end_time' => $day->copy()->setTimeFromTimeString($end),
            'created_by' => User::factory()->create()->id,
        ]);
    }

    protected function makeEvent(string $type, Carbon $day, string $start, string $end, string $title = 'Evento'): Event
    {
        return Event::create([
            'title' => $title,
            'type' => $type,
            'start_time' => $day->copy()->setTimeFromTimeString($start),
            'end_time' => $day->copy()->setTimeFromTimeString($end),
            'created_by' => User::factory()->create()->id,
        ]);
    }
}
