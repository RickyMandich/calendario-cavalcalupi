<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationGatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_admin_can_access_all_gates(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('secret123'),
        ]);
        $admin->assignRole('ADMIN');

        $group = Group::create(['name' => 'Tavolo 1', 'is_active' => true]);
        $event = Event::create([
            'title' => 'Sessione 1',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(2),
            'type' => 'session',
            'group_id' => $group->id,
            'created_by' => $admin->id,
        ]);

        $this->assertTrue(Gate::forUser($admin)->allows('viewLogViewer'));
        $this->assertTrue(Gate::forUser($admin)->allows('manage-groups'));
        $this->assertTrue(Gate::forUser($admin)->allows('manage-assemblies'));
        $this->assertTrue(Gate::forUser($admin)->allows('update-session', $event));
    }

    public function test_cd_can_manage_groups_and_assemblies_and_sessions(): void
    {
        $cdUser = User::create([
            'name' => 'CD User',
            'email' => 'cd@test.com',
            'password' => bcrypt('secret123'),
        ]);
        $cdUser->assignRole('CD');

        $group = Group::create(['name' => 'Tavolo 2', 'is_active' => true]);
        $event = Event::create([
            'title' => 'Sessione 2',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(2),
            'type' => 'session',
            'group_id' => $group->id,
            'created_by' => $cdUser->id,
        ]);

        $this->assertFalse(Gate::forUser($cdUser)->allows('viewLogViewer'));
        $this->assertTrue(Gate::forUser($cdUser)->allows('manage-groups'));
        $this->assertTrue(Gate::forUser($cdUser)->allows('manage-assemblies'));
        $this->assertTrue(Gate::forUser($cdUser)->allows('update-session', $event));
    }

    public function test_membro_without_permission_cannot_update_sessions_but_manager_can_in_own_group_only(): void
    {
        // Factory: email verificata, altrimenti non potrebbe essere aggiunto al gruppo
        $membro = User::factory()->create([
            'name' => 'Membro 1',
            'email' => 'membro1@test.com',
            'password' => bcrypt('secret123'),
        ]);
        $membro->assignRole('Membro');

        $group1 = Group::create(['name' => 'Gruppo Mio', 'is_active' => true]);
        $group2 = Group::create(['name' => 'Gruppo Altro', 'is_active' => true]);
        $group1->addUser($membro);

        $ownSession = Event::create([
            'title' => 'Sessione Nostra',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(2),
            'type' => 'session',
            'group_id' => $group1->id,
            'created_by' => $membro->id,
        ]);

        $otherSession = Event::create([
            'title' => 'Sessione Loro',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(2),
            'type' => 'session',
            'group_id' => $group2->id,
            'created_by' => $membro->id,
        ]);

        $assembly = Event::create([
            'title' => 'Assemblea',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(2),
            'type' => 'assembly',
            'created_by' => $membro->id,
        ]);

        $this->assertFalse(Gate::forUser($membro)->allows('viewLogViewer'));
        $this->assertFalse(Gate::forUser($membro)->allows('manage-groups'));
        $this->assertFalse(Gate::forUser($membro)->allows('manage-assemblies'));
        $this->assertFalse(Gate::forUser($membro)->allows('manage-users'));

        // Membro semplice: nessun permesso, neppure sulle sessioni create da lui
        $this->assertFalse(Gate::forUser($membro)->allows('update-session', $ownSession));
        $this->assertFalse(Gate::forUser($membro)->allows('delete-event', $ownSession));

        // Con il permesso di gestione sul gruppo 1
        $group1->setSessionManager($membro, true);
        $membro = $membro->fresh();
        $this->assertTrue(Gate::forUser($membro)->allows('update-session', $ownSession));
        $this->assertTrue(Gate::forUser($membro)->allows('delete-event', $ownSession));
        $this->assertTrue(Gate::forUser($membro)->allows('manage-group-sessions', $group1));
        // Il permesso non vale su un altro gruppo
        $this->assertFalse(Gate::forUser($membro)->allows('update-session', $otherSession));
        $this->assertFalse(Gate::forUser($membro)->allows('manage-group-sessions', $group2));
        // Né sulle assemblee
        $this->assertFalse(Gate::forUser($membro)->allows('update-session', $assembly));

        // Gruppo disattivato: il permesso non conta più
        $group1->update(['is_active' => false]);
        $this->assertFalse(Gate::forUser($membro->fresh())->allows('manage-group-sessions', $group1));
    }

    public function test_manage_users_gate_is_for_staff_only(): void
    {
        $admin = User::factory()->create()->assignRole('ADMIN');
        $cd = User::factory()->create()->assignRole('CD');
        $membro = User::factory()->create()->assignRole('Membro');

        $this->assertTrue(Gate::forUser($admin)->allows('manage-users'));
        $this->assertTrue(Gate::forUser($cd)->allows('manage-users'));
        $this->assertFalse(Gate::forUser($membro)->allows('manage-users'));
    }
}
