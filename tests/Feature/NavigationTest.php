<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesRooms;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use CreatesRooms;
    use RefreshDatabase;

    public function test_mobile_menu_toggle_and_panel_are_present_for_guests_and_users(): void
    {
        foreach (['/calendar', '/login', '/register'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('id="nav-toggle"', false)
                ->assertSee('id="mobile-menu"', false);
        }

        $this->actingAs($this->makeUser())->get('/calendar')
            ->assertOk()
            ->assertSee('id="nav-toggle"', false)
            ->assertSee('id="mobile-menu"', false);
    }

    public function test_nav_links_are_rendered_in_both_desktop_bar_and_mobile_menu(): void
    {
        $admin = $this->makeUser('ADMIN');

        $html = $this->actingAs($admin)->get('/calendar')->assertOk()->getContent();

        // (calendar.index è escluso: compare anche nel link del logo)
        foreach (['admin.groups.index', 'admin.users.index', 'swap-requests.index'] as $route) {
            $this->assertSame(
                2,
                substr_count($html, 'href="'.route($route).'"'),
                "Il link {$route} deve comparire nella barra desktop e nel menu mobile"
            );
        }
    }

    public function test_restricted_links_are_not_shown_to_members_in_either_menu(): void
    {
        $html = $this->actingAs($this->makeUser('Membro'))->get('/calendar')->assertOk()->getContent();

        $this->assertSame(0, substr_count($html, route('admin.users.index')));
        $this->assertSame(0, substr_count($html, route('admin.groups.index')));
        $this->assertStringNotContainsString('/log-viewer', $html);
        // I link per tutti gli utenti autenticati restano in entrambi i menu.
        $this->assertSame(2, substr_count($html, 'href="'.route('swap-requests.index').'"'));
    }

    public function test_guest_menu_has_no_authenticated_links(): void
    {
        $html = $this->get('/calendar')->assertOk()->getContent();

        $this->assertSame(0, substr_count($html, route('swap-requests.index')));
        $this->assertSame(0, substr_count($html, route('admin.users.index')));
    }

    public function test_logo_and_favicons_use_files_generated_by_the_build_not_the_source_svg(): void
    {
        $logo = asset('build/icons/android-chrome-192x192.png');

        foreach (['/calendar', '/login', '/register'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee($logo, false)
                ->assertSee(asset('build/icons/favicon-32x32.png'), false)
                ->assertDontSee('icon.svg', false);
        }
    }
}
