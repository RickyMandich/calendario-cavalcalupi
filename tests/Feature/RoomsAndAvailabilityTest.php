<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesRooms;
use Tests\TestCase;

class RoomsAndAvailabilityTest extends TestCase
{
    use CreatesRooms;
    use RefreshDatabase;

    public function test_member_cannot_manage_rooms_but_staff_can(): void
    {
        $this->actingAs($this->makeUser())->get(route('admin.rooms.index'))->assertForbidden();
        $this->actingAs($this->makeUser())->post(route('admin.rooms.store'), ['name' => 'X', 'capacity' => 1])->assertForbidden();

        foreach (['CD', 'ADMIN'] as $role) {
            $staff = $this->makeUser($role);
            $this->actingAs($staff)->get(route('admin.rooms.index'))->assertOk();
        }
    }

    public function test_staff_creates_updates_and_deletes_rooms(): void
    {
        $admin = $this->makeUser('ADMIN');

        $this->actingAs($admin)->post(route('admin.rooms.store'), [
            'name' => 'Sala Grande', 'capacity' => 3, 'is_active' => 1, 'weekdays' => [4, 5],
        ])->assertRedirect(route('admin.rooms.index'));

        $room = Room::where('name', 'Sala Grande')->firstOrFail();
        $this->assertEqualsCanonicalizing([4, 5], $room->weekdays->pluck('weekday')->all());

        // syncWeekdays sostituisce i giorni
        $this->actingAs($admin)->put(route('admin.rooms.update', $room), [
            'name' => 'Sala Grande', 'capacity' => 2, 'is_active' => 1, 'weekdays' => [2],
        ])->assertRedirect();
        $this->assertEquals([2], $room->fresh()->weekdays->pluck('weekday')->all());
        $this->assertSame(2, $room->fresh()->capacity);

        $this->actingAs($admin)->delete(route('admin.rooms.destroy', $room))->assertRedirect();
        $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
    }

    public function test_room_validation(): void
    {
        $admin = $this->makeUser('ADMIN');

        $this->actingAs($admin)->post(route('admin.rooms.store'), ['name' => 'X', 'capacity' => 0])
            ->assertSessionHasErrors('capacity');
        $this->actingAs($admin)->post(route('admin.rooms.store'), ['name' => 'X', 'capacity' => 1, 'weekdays' => [9]])
            ->assertSessionHasErrors('weekdays.0');
    }

    public function test_availability_endpoint_is_public_and_correct(): void
    {
        $this->createRoom(2, [4]);
        $thursday = $this->nextWeekday(4);
        $group = Group::create(['name' => 'G', 'is_active' => true]);
        $this->makeSession($group, $thursday);
        $this->makeEvent('assembly', $thursday, '10:00', '12:00');

        $single = $this->getJson(route('calendar.availability', ['date' => $thursday->toDateString()]))
            ->assertOk()->json();
        $this->assertSame(2, $single[0]['capacity']);
        $this->assertSame(1, $single[0]['occupied']);
        $this->assertSame(1, $single[0]['free']);
        $this->assertTrue($single[0]['has_assembly']);

        $range = $this->getJson(route('calendar.availability', [
            'start' => $thursday->copy()->subDay()->toDateString(),
            'end' => $thursday->copy()->addDay()->toDateString(),
        ]))->assertOk()->json();
        $this->assertCount(3, $range);
        $this->assertSame(0, $range[0]['capacity']);
    }

    public function test_availability_range_too_large_is_rejected(): void
    {
        $this->getJson(route('calendar.availability', ['start' => '2026-01-01', 'end' => '2026-06-01']))
            ->assertStatus(422);
    }

    public function test_event_creation_blocked_on_full_day_and_allowed_on_free_day(): void
    {
        $admin = $this->makeUser('ADMIN');
        $group = Group::create(['name' => 'G', 'is_active' => true]);
        $this->createRoom(1, [4]);
        $day = $this->nextWeekday(4);
        $this->makeSession($group, $day, '18:00', '20:00', 'Esistente');

        $payload = fn ($d) => [
            'title' => 'Nuova', 'type' => 'session', 'group_id' => $group->id,
            'start_time' => $d->copy()->setTime(21, 0)->format('Y-m-d H:i:s'),
            'end_time' => $d->copy()->setTime(23, 0)->format('Y-m-d H:i:s'),
        ];

        $this->actingAs($admin)->post(route('events.store'), $payload($day))->assertSessionHasErrors('start_time');
        $this->assertDatabaseMissing('events', ['title' => 'Nuova']);

        $this->actingAs($admin)->post(route('events.store'), $payload($day->copy()->addWeek()))->assertRedirect();
        $this->assertDatabaseHas('events', ['title' => 'Nuova']);
    }
}
