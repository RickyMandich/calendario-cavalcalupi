<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesRooms;
use Tests\TestCase;

class SessionManagementPermissionTest extends TestCase
{
    use CreatesRooms;
    use RefreshDatabase;

    private function payload(Group $group, $day, string $title = 'Nuova sessione'): array
    {
        return [
            'title' => $title,
            'type' => 'session',
            'group_id' => $group->id,
            'start_time' => $day->copy()->setTime(21, 0)->format('Y-m-d H:i:s'),
            'end_time' => $day->copy()->setTime(23, 0)->format('Y-m-d H:i:s'),
        ];
    }

    public function test_manager_creates_session_in_own_group(): void
    {
        $this->createRoom(5);
        $member = $this->makeUser();
        $group = Group::create(['name' => 'A', 'is_active' => true]);
        $group->addUser($member);
        $group->setSessionManager($member, true);

        $this->actingAs($member)
            ->post(route('events.store'), $this->payload($group, $this->nextWeekday(3)))
            ->assertRedirect();

        $this->assertDatabaseHas('events', ['title' => 'Nuova sessione', 'group_id' => $group->id]);
    }

    public function test_member_without_permission_gets_403_everywhere_even_on_own_events(): void
    {
        $this->createRoom(5);
        $member = $this->makeUser();
        $group = Group::create(['name' => 'A', 'is_active' => true]);
        $group->addUser($member);
        $day = $this->nextWeekday(3);

        // evento creato da lui stesso (regressione: created_by non basta più)
        $event = Event::create([
            'title' => 'Mia', 'type' => 'session', 'group_id' => $group->id,
            'start_time' => $day->copy()->setTime(21, 0), 'end_time' => $day->copy()->setTime(23, 0),
            'created_by' => $member->id,
        ]);

        $this->actingAs($member)->post(route('events.store'), $this->payload($group, $day))->assertForbidden();
        $this->actingAs($member)->get(route('events.edit', $event))->assertForbidden();
        $this->actingAs($member)->put(route('events.update', $event), [
            'title' => 'X',
            'start_time' => $day->copy()->setTime(20, 0)->format('Y-m-d H:i:s'),
            'end_time' => $day->copy()->setTime(22, 0)->format('Y-m-d H:i:s'),
        ])->assertForbidden();
        $this->actingAs($member)->delete(route('events.destroy', $event))->assertForbidden();
        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    public function test_manager_deletes_own_group_session_but_not_other_groups(): void
    {
        $member = $this->makeUser();
        $mine = Group::create(['name' => 'Mio', 'is_active' => true]);
        $other = Group::create(['name' => 'Altro', 'is_active' => true]);
        $mine->addUser($member);
        $mine->setSessionManager($member, true);
        $day = $this->nextWeekday(3);

        $ownEvent = $this->makeSession($mine, $day);
        $otherEvent = $this->makeSession($other, $day->copy()->addDay());

        $this->actingAs($member)->delete(route('events.destroy', $otherEvent))->assertForbidden();
        $this->actingAs($member)->delete(route('events.destroy', $ownEvent))->assertRedirect(route('calendar.index'));

        $this->assertDatabaseMissing('events', ['id' => $ownEvent->id]);
        $this->assertDatabaseHas('events', ['id' => $otherEvent->id]);
    }

    public function test_staff_assigns_permission_and_member_cannot(): void
    {
        $group = Group::create(['name' => 'A', 'is_active' => true]);
        $member = $this->makeUser();
        $other = $this->makeUser();
        $group->addUser($member);

        $url = route('admin.groups.users.manager', [$group, $member]);

        $this->actingAs($this->makeUser('CD'))->put($url, ['can_manage_sessions' => 1])->assertRedirect();
        $this->assertTrue((bool) $group->users()->find($member->id)->pivot->can_manage_sessions);

        $this->actingAs($other)->put($url, ['can_manage_sessions' => 0])->assertForbidden();
        $this->assertTrue((bool) $group->users()->find($member->id)->pivot->can_manage_sessions);
    }

    public function test_cannot_make_manager_a_non_member_or_in_inactive_group(): void
    {
        $group = Group::create(['name' => 'A', 'is_active' => true]);
        $outsider = $this->makeUser();
        $admin = $this->makeUser('ADMIN');

        $this->actingAs($admin)
            ->put(route('admin.groups.users.manager', [$group, $outsider]), ['can_manage_sessions' => 1])
            ->assertSessionHasErrors('can_manage_sessions');

        $member = $this->makeUser();
        $group->addUser($member);
        $group->update(['is_active' => false]);

        $this->actingAs($admin)
            ->put(route('admin.groups.users.manager', [$group, $member]), ['can_manage_sessions' => 1])
            ->assertSessionHasErrors('can_manage_sessions');
    }

    public function test_removing_user_drops_permission(): void
    {
        $group = Group::create(['name' => 'A', 'is_active' => true]);
        $member = $this->makeUser();
        $group->addUser($member);
        $group->setSessionManager($member, true);

        $this->actingAs($this->makeUser('ADMIN'))->delete(route('admin.groups.users.remove', [$group, $member]));
        $group->addUser($member);

        $this->assertFalse((bool) $group->users()->find($member->id)->pivot->can_manage_sessions);
    }

    public function test_two_managers_of_the_same_group_can_both_edit(): void
    {
        $this->createRoom(5);
        $group = Group::create(['name' => 'A', 'is_active' => true]);
        $a = $this->makeUser();
        $b = $this->makeUser();
        foreach ([$a, $b] as $u) {
            $group->addUser($u);
            $group->setSessionManager($u, true);
        }
        $event = $this->makeSession($group, $this->nextWeekday(3));

        $this->actingAs($a)->get(route('events.edit', $event))->assertOk();
        $this->actingAs($b)->get(route('events.edit', $event))->assertOk();
    }

    public function test_calendar_shows_new_session_button_only_to_those_who_can_create(): void
    {
        $plain = $this->makeUser();
        $this->actingAs($plain)->get('/calendar')->assertOk()->assertDontSee('Nuova Sessione / Evento');

        $group = Group::create(['name' => 'A', 'is_active' => true]);
        $manager = $this->makeUser();
        $group->addUser($manager);
        $group->setSessionManager($manager, true);

        $this->actingAs($manager)->get('/calendar')->assertSee('Nuova Sessione / Evento');
    }
}
