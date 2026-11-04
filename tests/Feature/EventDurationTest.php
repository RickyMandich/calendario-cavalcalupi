<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Group;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesRooms;
use Tests\TestCase;

class EventDurationTest extends TestCase
{
    use CreatesRooms;
    use RefreshDatabase;

    private function eventBetween(Carbon $start, Carbon $end): Event
    {
        $group = Group::create(['name' => 'Alfa '.uniqid(), 'is_active' => true]);

        return Event::create([
            'title' => 'Durata',
            'type' => 'session',
            'group_id' => $group->id,
            'start_time' => $start,
            'end_time' => $end,
            'created_by' => $this->makeUser()->id,
        ]);
    }

    public static function durations(): array
    {
        return [
            'ore e minuti' => ['21:30', '00:00', '2 ore e 30 min'],
            'ore tonde' => ['21:00', '23:00', '2 ore'],
            'una sola ora' => ['21:00', '22:00', '1 ora'],
            'un\'ora e mezza' => ['21:00', '22:30', '1 ora e 30 min'],
            'solo minuti' => ['21:00', '21:45', '45 min'],
        ];
    }

    #[Test]
    #[DataProvider('durations')]
    public function duration_label_is_formatted_with_whole_numbers(string $start, string $end, string $expected): void
    {
        $day = now()->addDays(10)->startOfDay();
        $startAt = $day->copy()->setTimeFromTimeString($start);
        $endAt = $day->copy()->setTimeFromTimeString($end);

        // fine a mezzanotte = giorno dopo
        if ($endAt <= $startAt) {
            $endAt->addDay();
        }

        $this->assertSame($expected, $this->eventBetween($startAt, $endAt)->durationLabel());
    }

    #[Test]
    public function show_page_renders_the_duration_without_decimals(): void
    {
        $day = now()->addDays(10)->startOfDay();
        $event = $this->eventBetween($day->copy()->setTime(21, 30), $day->copy()->addDay()->startOfDay());

        $this->actingAs($this->makeUser())
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('2 ore e 30 min')
            ->assertDontSee('2.5 ore');
    }
}
