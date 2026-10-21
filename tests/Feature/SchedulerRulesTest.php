<?php

namespace Tests\Feature;

use App\Exceptions\SchedulingException;
use App\Models\Group;
use App\Models\Room;
use App\Services\CalendarScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesRooms;
use Tests\TestCase;

class SchedulerRulesTest extends TestCase
{
    use CreatesRooms;
    use RefreshDatabase;

    private CalendarScheduler $scheduler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scheduler = app(CalendarScheduler::class);
    }

    private function attrs(string $type, $day, string $start, string $end): array
    {
        return [
            'type' => $type,
            'start_time' => $day->copy()->setTimeFromTimeString($start),
            'end_time' => $day->copy()->setTimeFromTimeString($end),
        ];
    }

    public function test_capacity_is_sum_of_active_rooms_on_that_weekday(): void
    {
        $this->createRoom(1, [4, 5]);
        $this->createRoom(2, [4]);
        $this->createRoom(1, [5]);
        $this->createRoom(9, [4], false); // non attiva: non conta

        $thursday = $this->nextWeekday(4);
        $friday = $this->nextWeekday(5);
        $monday = $this->nextWeekday(1);

        $this->assertSame(3, $this->scheduler->capacityOn($thursday));
        $this->assertSame(2, $this->scheduler->capacityOn($friday));
        $this->assertSame(0, $this->scheduler->capacityOn($monday));
    }

    public function test_weekday_mapping_for_late_evening_time(): void
    {
        $this->createRoom(1, [4]);
        $thursdayLate = $this->nextWeekday(4)->setTime(23, 30);

        $this->assertSame(1, $this->scheduler->capacityOn($thursdayLate));
    }

    public function test_session_rejected_without_rooms_and_when_full_accepted_with_last_slot(): void
    {
        $group = Group::create(['name' => 'G', 'is_active' => true]);
        $user = $this->makeUser();
        $day = $this->nextWeekday(4);

        try {
            $this->scheduler->validate($this->attrs('session', $day, '21:00', '23:00'));
            $this->fail('Giorno senza stanze: doveva essere rifiutato');
        } catch (SchedulingException $e) {
            $this->assertSame('no_rooms', $e->reason);
        }

        $this->createRoom(2, [4]);
        $this->makeSession($group, $day, '18:00', '20:00', 'Prima');

        // ultimo slot libero: ok
        $this->scheduler->validate($this->attrs('session', $day, '21:00', '23:00'));

        $this->makeSession($group, $day, '21:00', '23:00', 'Seconda');

        $this->expectException(SchedulingException::class);
        $this->scheduler->validate($this->attrs('session', $day, '16:00', '17:00'));
    }

    public function test_moving_within_same_day_does_not_count_twice_and_title_edit_is_allowed_on_overfull_day(): void
    {
        $group = Group::create(['name' => 'G', 'is_active' => true]);
        $this->createRoom(1, [4]);
        $day = $this->nextWeekday(4);
        $event = $this->makeSession($group, $day, '21:00', '23:00');

        // cambio di orario nello stesso giorno con capienza 1: ignora se stesso
        $updated = $this->scheduler->updateEvent($event, [
            'start_time' => $day->copy()->setTime(20, 0),
            'end_time' => $day->copy()->setTime(22, 0),
        ]);
        $this->assertSame('20:00', $updated->fresh()->start_time->format('H:i'));

        // giorno ora sovraffollato (stanza rimossa): la modifica del solo titolo è comunque consentita
        Room::query()->delete();
        $updated = $this->scheduler->updateEvent($event->fresh(), ['title' => 'Nuovo titolo']);
        $this->assertSame('Nuovo titolo', $updated->fresh()->title);
    }

    public function test_association_event_needs_no_room_and_takes_no_slot(): void
    {
        $day = $this->nextWeekday(2);

        $this->scheduler->validate($this->attrs('association_event', $day, '10:00', '12:00'));
        $this->assertSame(0, $this->scheduler->occupiedSlotsOn($day));
        $this->addToAssertionCount(1);
    }

    public function test_assembly_needs_rooms_and_excludes_overlapping_events(): void
    {
        $group = Group::create(['name' => 'G', 'is_active' => true]);
        $day = $this->nextWeekday(4);

        try {
            $this->scheduler->validate($this->attrs('assembly', $day, '21:00', '23:00'));
            $this->fail('Assemblea in giorno senza stanze: doveva essere rifiutata');
        } catch (SchedulingException) {
            $this->addToAssertionCount(1);
        }

        $this->createRoom(3, [4]);
        $this->scheduler->validate($this->attrs('assembly', $day, '21:00', '23:00'));

        // un'assemblea sopra una sessione esistente è rifiutata
        $this->makeSession($group, $day, '20:00', '22:00');
        $this->expectException(SchedulingException::class);
        $this->scheduler->validate($this->attrs('assembly', $day, '21:00', '23:00'));
    }

    public function test_overlap_with_assembly_blocks_sessions_and_association_events_but_adjacent_ends_are_fine(): void
    {
        $this->createRoom(5, [4]);
        $day = $this->nextWeekday(4);
        $this->makeEvent('assembly', $day, '21:00', '23:00', 'Assemblea');

        foreach ([['20:00', '22:00'], ['21:30', '22:00'], ['20:00', '23:30']] as [$s, $e]) {
            foreach (['session', 'association_event'] as $type) {
                try {
                    $this->scheduler->validate($this->attrs($type, $day, $s, $e));
                    $this->fail("$type $s-$e doveva essere rifiutato");
                } catch (SchedulingException) {
                    $this->addToAssertionCount(1);
                }
            }
        }

        // estremi adiacenti: ammessi
        $this->scheduler->validate($this->attrs('session', $day, '19:00', '21:00'));
        $this->scheduler->validate($this->attrs('session', $day, '23:00', '23:59'));
        $this->addToAssertionCount(1);
    }

    public function test_extra_events_count_towards_capacity(): void
    {
        $this->createRoom(1, [4]);
        $day = $this->nextWeekday(4);

        $extra = [[
            'type' => 'session',
            'start' => $day->copy()->setTime(18, 0),
            'end' => $day->copy()->setTime(20, 0),
        ]];

        $this->expectException(SchedulingException::class);
        $this->scheduler->validate($this->attrs('session', $day, '21:00', '23:00'), null, $extra);
    }

    public function test_non_staff_cannot_move_session_to_the_past(): void
    {
        $group = Group::create(['name' => 'G', 'is_active' => true]);
        $this->createRoom(5);
        $member = $this->makeUser();

        $event = \App\Models\Event::create([
            'title' => 'Passata', 'type' => 'session', 'group_id' => $group->id,
            'start_time' => now()->subDays(3)->setTime(21, 0), 'end_time' => now()->subDays(3)->setTime(23, 0),
            'created_by' => $member->id,
        ]);

        try {
            $this->scheduler->updateEvent($event, [
                'start_time' => now()->addDays(3)->setTime(21, 0),
                'end_time' => now()->addDays(3)->setTime(23, 0),
            ], $member);
            $this->fail('Sessione passata: lo spostamento doveva essere rifiutato');
        } catch (SchedulingException $e) {
            $this->assertSame('past', $e->reason);
        }

        // lo staff può
        $admin = $this->makeUser('ADMIN');
        $this->scheduler->updateEvent($event->fresh(), [
            'start_time' => now()->addDays(3)->setTime(21, 0),
            'end_time' => now()->addDays(3)->setTime(23, 0),
        ], $admin);
        $this->assertTrue($event->fresh()->start_time->isFuture());
    }
}
