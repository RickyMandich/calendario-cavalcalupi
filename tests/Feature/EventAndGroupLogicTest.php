<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventLog;
use App\Models\Group;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class EventAndGroupLogicTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_creation_automatically_generates_ics_token(): void
    {
        $user = User::create([
            'name' => 'Mario Rossi',
            'email' => 'mario@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $this->assertNotEmpty($user->ics_token);
        $this->assertEquals(64, strlen($user->ics_token));
    }

    public function test_group_active_scope_and_user_assignment(): void
    {
        $activeGroup = Group::create(['name' => 'Gruppo Lupi', 'is_active' => true]);
        $inactiveGroup = Group::create(['name' => 'Gruppo Vecchio', 'is_active' => false]);

        $this->assertEquals(1, Group::active()->count());
        $this->assertEquals('Gruppo Lupi', Group::active()->first()->name);

        // Factory: email verificata, altrimenti non potrebbe essere aggiunto al gruppo
        $user = User::factory()->create([
            'name' => 'Luigi Verdi',
            'email' => 'luigi@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $activeGroup->addUser($user);
        $this->assertTrue($activeGroup->users->contains($user));

        $this->expectException(DomainException::class);
        $inactiveGroup->addUser($user);
    }

    public function test_session_requires_active_group(): void
    {
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $inactiveGroup = Group::create(['name' => 'Gruppo Inattivo', 'is_active' => false]);

        $this->expectException(InvalidArgumentException::class);
        Event::create([
            'title' => 'Sessione D&D',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(3),
            'type' => 'session',
            'group_id' => $inactiveGroup->id,
            'created_by' => $user->id,
        ]);
    }

    public function test_non_session_clears_group_id(): void
    {
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin2@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $group = Group::create(['name' => 'Gruppo Attivo', 'is_active' => true]);

        $event = Event::create([
            'title' => 'Assemblea Generale',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(2),
            'type' => 'assembly',
            'group_id' => $group->id,
            'created_by' => $user->id,
        ]);

        $this->assertNull($event->group_id);
    }

    public function test_event_observer_logs_creation_and_reschedule(): void
    {
        $user = User::create([
            'name' => 'Master User',
            'email' => 'master@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $group = Group::create(['name' => 'Party 1', 'is_active' => true]);

        $this->actingAs($user);

        $event = Event::create([
            'title' => 'Campagna 1',
            'description' => 'Sessione 0',
            'start_time' => now()->addDays(2),
            'end_time' => now()->addDays(2)->addHours(4),
            'type' => 'session',
            'group_id' => $group->id,
            'created_by' => $user->id,
        ]);

        $this->assertDatabaseHas('event_logs', [
            'event_id' => $event->id,
            'user_id' => $user->id,
            'action' => 'created',
        ]);

        // Reschedule
        $newStart = now()->addDays(3);
        $newEnd = now()->addDays(3)->addHours(4);
        $event->update([
            'start_time' => $newStart,
            'end_time' => $newEnd,
        ]);

        $this->assertDatabaseHas('event_logs', [
            'event_id' => $event->id,
            'user_id' => $user->id,
            'action' => 'rescheduled',
        ]);
    }
}
