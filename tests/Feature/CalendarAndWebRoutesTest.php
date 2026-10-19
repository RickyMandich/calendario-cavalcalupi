<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarAndWebRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_calendar_page_is_accessible(): void
    {
        $response = $this->get('/calendar');
        $response->assertStatus(200);
        $response->assertSee('Calendario Attività');
    }

    public function test_login_and_quick_login(): void
    {
        $response = $this->get('/quick-login/admin');
        $response->assertRedirect('/calendar');
        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->hasRole('ADMIN'));
    }

    public function test_membro_cannot_access_admin_groups(): void
    {
        $membro = User::where('email', 'membro@cavalcalupi.it')->first();

        $response = $this->actingAs($membro)->get('/admin/groups');
        $response->assertStatus(403);
    }

    public function test_cd_can_access_and_create_group(): void
    {
        $cd = User::where('email', 'cd@cavalcalupi.it')->first();

        $response = $this->actingAs($cd)->get('/admin/groups');
        $response->assertStatus(200);

        $response = $this->actingAs($cd)->post('/admin/groups', [
            'name' => 'Tavolo Warhammer 40k',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.groups.index'));
        $this->assertDatabaseHas('groups', ['name' => 'Tavolo Warhammer 40k']);
    }

    public function test_assigning_user_to_inactive_group_fails(): void
    {
        $admin = User::where('email', 'admin@cavalcalupi.it')->first();
        $membro = User::where('email', 'membro@cavalcalupi.it')->first();
        $inactiveGroup = Group::where('is_active', false)->first();

        $response = $this->actingAs($admin)->post(route('admin.groups.users.assign', $inactiveGroup), [
            'user_id' => $membro->id,
        ]);

        $response->assertSessionHasErrors(['user_id']);
    }

    public function test_event_creation_and_update_with_logging(): void
    {
        $admin = User::where('email', 'admin@cavalcalupi.it')->first();
        $activeGroup = Group::where('is_active', true)->first();

        $startTime = now()->addDays(4)->setTime(21, 00);
        $endTime = now()->addDays(4)->setTime(23, 30);

        $response = $this->actingAs($admin)->post(route('events.store'), [
            'title' => 'Sessione Test Campagna',
            'description' => 'Descrizione iniziale',
            'type' => 'session',
            'group_id' => $activeGroup->id,
            'start_time' => $startTime->format('Y-m-d H:i:s'),
            'end_time' => $endTime->format('Y-m-d H:i:s'),
        ]);

        $event = Event::where('title', 'Sessione Test Campagna')->first();
        $this->assertNotNull($event);
        $response->assertRedirect(route('events.show', $event));

        // Verify creation log
        $this->assertDatabaseHas('event_logs', [
            'event_id' => $event->id,
            'action' => 'created',
        ]);

        // Reschedule event
        $rescheduledStart = now()->addDays(5)->setTime(21, 30);
        $rescheduledEnd = now()->addDays(5)->setTime(23, 45);

        $updateResponse = $this->actingAs($admin)->put(route('events.update', $event), [
            'title' => 'Sessione Test Campagna Riprogrammata',
            'description' => 'Nuova descrizione',
            'start_time' => $rescheduledStart->format('Y-m-d H:i:s'),
            'end_time' => $rescheduledEnd->format('Y-m-d H:i:s'),
            'group_id' => $activeGroup->id,
        ]);

        $updateResponse->assertRedirect(route('events.show', $event));

        // Verify reschedule log
        $this->assertDatabaseHas('event_logs', [
            'event_id' => $event->id,
            'action' => 'rescheduled',
        ]);

        // Show page displays the modification history
        $showResponse = $this->actingAs($admin)->get(route('events.show', $event));
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Storico Modifiche');
        $showResponse->assertSee('Riprogrammata');
    }
}
