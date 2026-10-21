<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_only_a_membro_without_groups(): void
    {
        $this->seed(RoleSeeder::class);

        $this->post('/register', [
            'name' => 'Nuovo Utente',
            'email' => 'nuovo@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('calendar.index'));

        $user = User::where('email', 'nuovo@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('Membro'));
        $this->assertFalse($user->isStaff());
        $this->assertSame(0, $user->groups()->count());
    }

    public function test_registration_works_even_without_seeded_roles(): void
    {
        $this->post('/register', [
            'name' => 'Senza Seed',
            'email' => 'noseed@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('calendar.index'));

        $this->assertTrue(User::where('email', 'noseed@example.com')->firstOrFail()->hasRole('Membro'));
    }

    public function test_registration_ignores_extra_fields(): void
    {
        $this->seed(RoleSeeder::class);

        $this->post('/register', [
            'name' => 'Furbo',
            'email' => 'furbo@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'ADMIN',
            'is_admin' => true,
            'ics_token' => 'token-scelto-da-me',
        ]);

        $user = User::where('email', 'furbo@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('Membro'));
        $this->assertFalse($user->hasRole('ADMIN'));
        $this->assertNotSame('token-scelto-da-me', $user->ics_token);
    }

    public function test_demo_seeder_does_nothing_in_production(): void
    {
        $this->app['env'] = 'production';

        // Istanziato direttamente: db:seed in produzione chiederebbe una conferma interattiva.
        (new DemoSeeder)->run();

        $this->assertSame(0, User::count());
    }

    public function test_database_seeder_creates_roles_but_no_users_outside_local(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('roles', ['name' => 'ADMIN']);
        $this->assertDatabaseHas('roles', ['name' => 'CD']);
        $this->assertDatabaseHas('roles', ['name' => 'Membro']);
        $this->assertSame(0, User::count());
    }

    public function test_login_is_throttled(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/login', ['email' => 'x@example.com', 'password' => 'sbagliata']);
        }

        $this->post('/login', ['email' => 'x@example.com', 'password' => 'sbagliata'])->assertStatus(429);
    }

    public function test_public_calendar_does_not_expose_tokens_or_demo_block(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $token = $user->ics_token;

        $this->get('/calendar')
            ->assertOk()
            ->assertDontSee($token)
            ->assertDontSee('Accesso Rapido Demo');

        $this->get('/login')->assertOk()->assertDontSee('Accesso Rapido Demo');
    }

    public function test_event_detail_requires_login(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $event = Event::create([
            'title' => 'Assemblea', 'type' => 'assembly',
            'start_time' => now()->addDay(), 'end_time' => now()->addDay()->addHour(),
            'created_by' => $user->id,
        ]);

        $this->get(route('events.show', $event))->assertRedirect(route('login'));
    }
}
