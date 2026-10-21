<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesRooms;
use Tests\TestCase;

class ThemeToggleTest extends TestCase
{
    use CreatesRooms;
    use RefreshDatabase;

    public function test_toggle_and_anti_flash_script_are_present_for_guests_and_users(): void
    {
        foreach (['/calendar', '/login', '/register'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('id="theme-toggle"', false)
                ->assertSee("localStorage.getItem('theme')", false);
        }

        $this->actingAs($this->makeUser())->get('/calendar')
            ->assertOk()
            ->assertSee('id="theme-toggle"', false);
    }
}
