<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed roles
        Role::create(['name' => 'ADMIN', 'guard_name' => 'web']);
        Role::create(['name' => 'CD', 'guard_name' => 'web']);
        Role::create(['name' => 'Membro', 'guard_name' => 'web']);
    }

    /** @test */
    public function guest_cannot_access_user_management()
    {
        $response = $this->get(route('admin.users.index'));
        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function member_cannot_access_user_management()
    {
        $member = User::factory()->create()->assignRole('Membro');
        $response = $this->actingAs($member)->get(route('admin.users.index'));
        $response->assertStatus(403);
    }

    /** @test */
    public function cd_and_admin_can_view_user_management_page()
    {
        $cd = User::factory()->create()->assignRole('CD');
        $admin = User::factory()->create()->assignRole('ADMIN');

        $this->actingAs($cd)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
    }

    /** @test */
    public function admin_can_promote_user_to_admin_and_last_admin_protected()
    {
        $admin = User::factory()->create()->assignRole('ADMIN');
        $other = User::factory()->create()->assignRole('Membro');

        // Attempt to demote the only ADMIN (should fail)
        $this->actingAs($admin)
            ->put(route('admin.users.role', $admin), ['role' => 'Membro'])
            ->assertSessionHasErrors('role');

        // Promote other to ADMIN (now two admins)
        $this->actingAs($admin)
            ->put(route('admin.users.role', $other), ['role' => 'ADMIN'])
            ->assertRedirect();
        $this->assertTrue($other->fresh()->hasRole('ADMIN'));

        // Demote original admin now that another admin exists
        $this->actingAs($other)
            ->put(route('admin.users.role', $admin), ['role' => 'Membro'])
            ->assertRedirect();
        $this->assertFalse($admin->fresh()->hasRole('ADMIN'));
    }

    /** @test */
    public function invalid_role_is_rejected()
    {
        $admin = User::factory()->create()->assignRole('ADMIN');
        $user = User::factory()->create()->assignRole('Membro');
        $this->actingAs($admin)
            ->put(route('admin.users.role', $user), ['role' => 'INVALID'])
            ->assertSessionHasErrors('role');
    }

    /** @test */
    public function search_filter_returns_correct_users()
    {
        $admin = User::factory()->create(['name' => 'Alice'])->assignRole('ADMIN');
        User::factory()->create(['name' => 'Bob'])->assignRole('Membro');
        $response = $this->actingAs($admin)->get(route('admin.users.index', ['q' => 'Alice']));
        $response->assertSee('Alice');
        $response->assertDontSee('Bob');
    }
}

