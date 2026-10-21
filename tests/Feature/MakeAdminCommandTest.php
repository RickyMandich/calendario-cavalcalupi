<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesRooms;
use Tests\TestCase;

class MakeAdminCommandTest extends TestCase
{
    use CreatesRooms;
    use RefreshDatabase;

    public function test_promotes_an_existing_user_to_admin(): void
    {
        $user = $this->makeUser('Membro', ['email' => 'primo@example.com']);

        $this->artisan('app:make-admin', ['email' => 'primo@example.com'])->assertSuccessful();

        $user = $user->fresh();
        $this->assertTrue($user->hasRole('ADMIN'));
        $this->assertFalse($user->hasRole('Membro'));
    }

    public function test_unknown_email_fails(): void
    {
        $this->artisan('app:make-admin', ['email' => 'nessuno@example.com'])->assertExitCode(1);
    }

    public function test_is_idempotent(): void
    {
        $this->makeUser('ADMIN', ['email' => 'admin2@example.com']);

        $this->artisan('app:make-admin', ['email' => 'admin2@example.com'])->assertSuccessful();

        $this->assertTrue(User::where('email', 'admin2@example.com')->first()->hasRole('ADMIN'));
    }
}
