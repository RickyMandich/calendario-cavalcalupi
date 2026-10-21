<?php

namespace Tests\Feature;

use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesRooms;
use Tests\TestCase;

class CalendarEventsFeedTest extends TestCase
{
    use CreatesRooms;
    use RefreshDatabase;

    private function feed(array $params = [], $user = null)
    {
        $base = ['start' => now()->subDay()->toDateString(), 'end' => now()->addDays(60)->toDateString()];

        $request = $user ? $this->actingAs($user) : $this;

        return $request->getJson(route('calendar.events', array_merge($base, $params)));
    }

    public function test_guest_gets_events_in_range_only(): void
    {
        $group = Group::create(['name' => 'Alfa', 'is_active' => true]);
        $inside = $this->makeSession($group, now()->addDays(10)->startOfDay(), '21:00', '23:00', 'Dentro');
        $this->makeSession($group, now()->addYears(2)->startOfDay(), '21:00', '23:00', 'Fuori');

        $json = $this->feed()->assertOk()->json();

        $titles = collect($json)->pluck('title')->all();
        $this->assertContains('Alfa · Dentro', $titles);
        $this->assertNotContains('Alfa · Fuori', $titles);

        $item = collect($json)->firstWhere('id', $inside->id);
        $this->assertSame('session', $item['extendedProps']['type']);
        $this->assertNull($item['extendedProps']['url']);
        $this->assertNull($item['extendedProps']['editUrl']);
        $this->assertMatchesRegularExpression('/[+-]\d{2}:\d{2}$/', $item['start']);
        $this->assertStringNotContainsString('@', json_encode($json)); // nessuna email
    }

    public function test_event_straddling_the_interval_is_included(): void
    {
        $group = Group::create(['name' => 'Alfa', 'is_active' => true]);
        $day = now()->addDays(10)->startOfDay();
        $this->makeSession($group, $day, '21:00', '23:00', 'Notte');

        $json = $this->getJson(route('calendar.events', [
            'start' => $day->copy()->setTime(22, 0)->toIso8601String(),
            'end' => $day->copy()->addDay()->toIso8601String(),
        ]))->assertOk()->json();

        $this->assertCount(1, $json);
    }

    public function test_urls_are_present_for_authorized_users(): void
    {
        $group = Group::create(['name' => 'Alfa', 'is_active' => true]);
        $this->makeSession($group, now()->addDays(10)->startOfDay(), '21:00', '23:00');

        $json = $this->feed([], $this->makeUser('ADMIN'))->assertOk()->json();

        $this->assertNotNull($json[0]['extendedProps']['url']);
        $this->assertNotNull($json[0]['extendedProps']['editUrl']);

        $json = $this->feed([], $this->makeUser())->json();
        $this->assertNotNull($json[0]['extendedProps']['url']);
        $this->assertNull($json[0]['extendedProps']['editUrl']);
    }

    public function test_my_groups_filter_excludes_other_groups_but_keeps_institutional_events(): void
    {
        $mine = Group::create(['name' => 'Mio', 'is_active' => true]);
        $other = Group::create(['name' => 'Altro', 'is_active' => true]);
        $user = $this->makeUser();
        $mine->addUser($user);

        $day = now()->addDays(10)->startOfDay();
        $this->makeSession($mine, $day, '18:00', '19:00', 'S-mia');
        $this->makeSession($other, $day->copy()->addDay(), '18:00', '19:00', 'S-altra');
        $this->makeEvent('assembly', $day->copy()->addDays(2), '20:00', '22:00', 'Assemblea');
        $this->makeEvent('association_event', $day->copy()->addDays(3), '20:00', '22:00', 'Cena');

        $filtered = collect($this->feed(['filter' => 'my-groups'], $user)->json())->pluck('title')->all();
        $this->assertContains('Mio · S-mia', $filtered);
        $this->assertContains('Assemblea', $filtered);
        $this->assertContains('Cena', $filtered);
        $this->assertNotContains('Altro · S-altra', $filtered);

        $all = collect($this->feed(['filter' => 'all'], $user)->json())->pluck('title')->all();
        $this->assertContains('Altro · S-altra', $all);
    }

    public function test_invalid_parameters_are_rejected(): void
    {
        $this->getJson(route('calendar.events'))->assertStatus(422);
        $this->getJson(route('calendar.events', ['start' => '2026-01-01', 'end' => '2026-12-31']))->assertStatus(422);
    }

    public function test_calendar_page_and_list_view(): void
    {
        $group = Group::create(['name' => 'Alfa', 'is_active' => true]);
        $this->makeSession($group, now()->addDays(5)->startOfDay(), '21:00', '23:00', 'In elenco');

        $this->get('/calendar')->assertOk()->assertSee('id="calendar"', false)->assertSee('Calendario Attività');
        $this->get('/calendar?view=list')->assertOk()->assertSee('Dettagli e Storico')->assertSee('In elenco');
    }
}
