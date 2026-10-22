<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesRooms;
use Tests\TestCase;

class CalendarAndWebRoutesTest extends TestCase
{
    use CreatesRooms;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // L'ambiente di test non è produzione: il DemoSeeder gira (utenti, gruppo e stanze demo).
        $this->seed(RoleSeeder::class);
        $this->seed(DemoSeeder::class);
    }

    public function test_calendar_page_is_accessible(): void
    {
        $this->get('/calendar')->assertOk()->assertSee('Calendario Attività');
    }

    public function test_authenticated_user_even_with_null_token_loads_calendar_and_auto_generates_token(): void
    {
        // Factory: utente con email verificata (il token è mostrato solo a chi ha verificato l'email)
        $user = User::factory()->create([
            'name' => 'Legacy User',
            'email' => 'legacy@test.com',
            'password' => bcrypt('secret123'),
        ]);
        \Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->update(['ics_token' => null]);
        $user = $user->fresh();

        $this->assertNull($user->getRawOriginal('ics_token'));

        $this->actingAs($user)->get('/calendar')->assertOk();

        $this->assertNotNull(\Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->value('ics_token'));
    }

    public function test_real_login_with_demo_admin(): void
    {
        $this->post('/login', ['email' => 'admin@cavalcalupi.it', 'password' => 'password'])
            ->assertRedirect('/calendar');

        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->hasRole('ADMIN'));
    }

    public function test_quick_login_is_not_available_outside_local(): void
    {
        $this->get('/quick-login/admin')->assertNotFound();
        $this->assertGuest();
    }

    public function test_membro_cannot_access_admin_groups(): void
    {
        $membro = User::where('email', 'membro@cavalcalupi.it')->first();

        $this->actingAs($membro)->get('/admin/groups')->assertForbidden();
    }

    public function test_cd_can_access_and_create_group(): void
    {
        $cd = User::where('email', 'cd@cavalcalupi.it')->first();

        $this->actingAs($cd)->get('/admin/groups')->assertOk();

        $this->actingAs($cd)->post('/admin/groups', [
            'name' => 'Tavolo Warhammer 40k',
            'is_active' => 1,
        ])->assertRedirect(route('admin.groups.index'));

        $this->assertDatabaseHas('groups', ['name' => 'Tavolo Warhammer 40k']);
    }

    public function test_assigning_user_to_inactive_group_fails(): void
    {
        $admin = User::where('email', 'admin@cavalcalupi.it')->first();
        $membro = User::where('email', 'membro@cavalcalupi.it')->first();
        $inactiveGroup = Group::create(['name' => 'Tavolo Vecchio (stagione passata)', 'is_active' => false]);

        $this->actingAs($admin)
            ->post(route('admin.groups.users.assign', $inactiveGroup), ['user_id' => $membro->id])
            ->assertSessionHasErrors(['user_id']);
    }

    public function test_event_creation_and_update_with_logging(): void
    {
        $admin = User::where('email', 'admin@cavalcalupi.it')->first();
        $activeGroup = Group::create(['name' => 'Tavolo Attivo', 'is_active' => true]);

        // Stanza aperta tutti i giorni, altrimenti la creazione via HTTP viene (correttamente) rifiutata
        $this->createRoom(5);

        $startTime = now()->addDays(4)->setTime(21, 0);
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

        $this->assertDatabaseHas('event_logs', ['event_id' => $event->id, 'action' => 'created']);

        $updateResponse = $this->actingAs($admin)->put(route('events.update', $event), [
            'title' => 'Sessione Test Campagna Riprogrammata',
            'description' => 'Nuova descrizione',
            'start_time' => now()->addDays(5)->setTime(21, 30)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(5)->setTime(23, 45)->format('Y-m-d H:i:s'),
            'group_id' => $activeGroup->id,
        ]);

        $updateResponse->assertRedirect(route('events.show', $event));
        $this->assertDatabaseHas('event_logs', ['event_id' => $event->id, 'action' => 'rescheduled']);

        $this->actingAs($admin)->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('Storico Modifiche')
            ->assertSee('Riprogrammata');
    }
}
