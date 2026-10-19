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

    public function test_membro_can_only_update_own_active_group_session(): void
    {
        $membro = User::create([
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

        // Can update session of own group
        $this->assertTrue(Gate::forUser($membro)->allows('update-session', $ownSession));
        // Cannot update session of other group
        $this->assertFalse(Gate::forUser($membro)->allows('update-session', $otherSession));
        // Cannot update assembly
        $this->assertFalse(Gate::forUser($membro)->allows('update-session', $assembly));
    }
}
