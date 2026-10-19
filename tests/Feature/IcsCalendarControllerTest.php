<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IcsCalendarControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_invalid_token_returns_404(): void
    {
        $response = $this->get('/calendar/ics/invalid-token-12345');
        $response->assertStatus(404);
    }

    public function test_valid_token_returns_calendar_feed_with_correct_filtering(): void
    {
        $user = User::create([
            'name' => 'Mario',
            'email' => 'mario@test.com',
            'password' => bcrypt('secret123'),
        ]);

        $activeGroup = Group::create(['name' => 'Tavolo Alfa', 'is_active' => true]);
        $otherGroup = Group::create(['name' => 'Tavolo Beta', 'is_active' => true]);
        $activeGroup->addUser($user);

        // Session for user's group
        $mySession = Event::create([
            'title' => 'Campagna Alfa - Atto 1',
            'description' => 'Si gioca stasera',
            'start_time' => now()->addDays(2),
            'end_time' => now()->addDays(2)->addHours(3),
            'type' => 'session',
            'group_id' => $activeGroup->id,
            'created_by' => $user->id,
        ]);

        // Session for other group
        $otherSession = Event::create([
            'title' => 'Campagna Beta - Sessione segreta',
            'start_time' => now()->addDays(3),
            'end_time' => now()->addDays(3)->addHours(3),
            'type' => 'session',
            'group_id' => $otherGroup->id,
            'created_by' => $user->id,
        ]);

        // Assembly
        $assembly = Event::create([
            'title' => 'Assemblea Ordinaria dei Soci',
            'description' => 'Approvazione bilancio',
            'start_time' => now()->addDays(4),
            'end_time' => now()->addDays(4)->addHours(2),
            'type' => 'assembly',
            'created_by' => $user->id,
        ]);

        $response = $this->get(route('calendar.ics', ['token' => $user->ics_token]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $this->assertStringContainsString('ATTACHMENT; FILENAME="CAVALCALUPI-CALENDAR.ICS"', strtoupper($response->headers->get('Content-Disposition')));

        $content = $response->getContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $content);
        $this->assertStringContainsString('Campagna Alfa - Atto 1', $content);
        $this->assertStringContainsString('Assemblea Ordinaria dei Soci', $content);
        $this->assertStringNotContainsString('Campagna Beta - Sessione segreta', $content);
    }
}
