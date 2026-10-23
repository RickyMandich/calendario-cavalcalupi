<?php

namespace Tests\Feature;

use App\Exceptions\SchedulingException;
use App\Models\Event;
use App\Models\Group;
use App\Models\SessionSwapRequest;
use App\Models\User;
use App\Notifications\SessionMoved;
use App\Notifications\SessionSwapped;
use App\Services\SessionSwapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesRooms;
use Tests\TestCase;

/**
 * Flusso "Richiedi cambio giorno" / "Annulla sessione": spostamento immediato se c'è posto,
 * attesa se il giorno è pieno, esecuzione automatica quando si libera un posto.
 */
class DayChangeRequestTest extends TestCase
{
    use CreatesRooms;
    use RefreshDatabase;

    private Group $gA;
    private Group $gB;
    private Group $gC;
    private User $uA;
    private User $uB;
    private User $uC;
    private $thu;
    private $fri;
    private $sat;
    private Event $evA;
    private Event $evB;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->createRoom(1, [4, 5, 6]);
        $this->thu = $this->nextWeekday(4);
        $this->fri = $this->nextWeekday(5);
        $this->sat = $this->nextWeekday(6);

        foreach (['A', 'B', 'C'] as $n) {
            $group = Group::create(['name' => 'Gruppo '.$n, 'is_active' => true]);
            $user = $this->makeUser();
            $group->addUser($user);
            $group->setSessionManager($user, true);
            $this->{'g'.$n} = $group;
            $this->{'u'.$n} = $user;
        }

        $this->evA = $this->makeSession($this->gA, $this->thu, '21:00', '23:00', 'Sessione A');
        $this->evB = $this->makeSession($this->gB, $this->fri, '18:30', '20:00', 'Sessione B');
    }

    private function service(): SessionSwapService
    {
        return app(SessionSwapService::class);
    }

    public function test_request_on_free_day_moves_immediately_keeping_time_and_duration(): void
    {
        $this->actingAs($this->uA)
            ->post(route('swap-requests.store', $this->evA), ['target_date' => $this->sat->toDateString()])
            ->assertRedirect(route('events.show', $this->evA))
            ->assertSessionHas('success');

        $a = $this->evA->fresh();
        $this->assertSame($this->sat->toDateString().' 21:00', $a->start_time->format('Y-m-d H:i'));
        $this->assertSame($this->sat->toDateString().' 23:00', $a->end_time->format('Y-m-d H:i'));
        $this->assertSame(0, SessionSwapRequest::count());

        $log = $a->logs()->where('action', 'rescheduled')->first();
        $this->assertNotNull($log);
        $this->assertSame('direct_move', $log->new_data['_context']['reason']);
        Notification::assertNothingSent();
    }

    public function test_request_on_day_without_rooms_is_rejected(): void
    {
        $monday = $this->nextWeekday(1);

        try {
            $this->service()->request($this->evA, $monday, $this->uA);
            $this->fail('Giorno senza stanze: doveva essere rifiutato');
        } catch (SchedulingException $e) {
            $this->assertSame('no_rooms', $e->reason);
        }

        $this->assertSame($this->thu->toDateString(), $this->evA->fresh()->start_time->toDateString());
        $this->assertSame(0, SessionSwapRequest::count());
    }

    public function test_immediate_move_onto_an_assembly_is_rejected_and_changes_nothing(): void
    {
        $this->makeEvent('assembly', $this->sat, '20:30', '22:30', 'Assemblea');

        try {
            $this->service()->request($this->evA, $this->sat, $this->uA);
            $this->fail('Assemblea sovrapposta: doveva essere rifiutato');
        } catch (SchedulingException $e) {
            $this->assertSame('target_date', $e->field);
        }

        $this->assertSame($this->thu->toDateString(), $this->evA->fresh()->start_time->toDateString());
        $this->assertSame(0, SessionSwapRequest::count());
    }

    public function test_waiting_request_runs_when_the_blocking_session_is_cancelled(): void
    {
        $this->service()->request($this->evA, $this->fri, $this->uA); // venerdì pieno: resta in attesa
        $this->assertSame(1, SessionSwapRequest::pending()->count());

        $this->actingAs($this->uB)
            ->delete(route('events.destroy', $this->evB))
            ->assertRedirect(route('calendar.index'))
            ->assertSessionHas('success', 'Sessione annullata.');

        $a = $this->evA->fresh();
        $this->assertSame($this->fri->toDateString().' 21:00', $a->start_time->format('Y-m-d H:i'));
        $this->assertSame($this->fri->toDateString().' 23:00', $a->end_time->format('Y-m-d H:i'));

        $r = SessionSwapRequest::firstOrFail();
        $this->assertSame('completed', $r->status);
        $this->assertNull($r->matched_request_id);
        $this->assertNull($r->pending_flag);

        $log = $a->logs()->where('action', 'rescheduled')->first();
        $this->assertSame('waiting_request', $log->new_data['_context']['reason']);

        Notification::assertSentTo($this->uA, SessionMoved::class);
        Notification::assertNotSentTo($this->uA, SessionSwapped::class);
    }

    public function test_waiting_request_runs_when_staff_moves_the_blocking_session_away(): void
    {
        $this->service()->request($this->evA, $this->fri, $this->uA);

        $admin = $this->makeUser('ADMIN');
        $this->actingAs($admin)->put(route('events.update', $this->evB), [
            'title' => 'Sessione B', 'group_id' => $this->gB->id,
            'start_time' => $this->sat->copy()->setTime(18, 30)->format('Y-m-d H:i:s'),
            'end_time' => $this->sat->copy()->setTime(20, 0)->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $this->assertSame($this->fri->toDateString(), $this->evA->fresh()->start_time->toDateString());
        $this->assertSame('completed', SessionSwapRequest::firstOrFail()->status);
        Notification::assertSentTo($this->uA, SessionMoved::class);
    }

    public function test_oldest_waiting_request_gets_the_free_slot(): void
    {
        $evC = $this->makeSession($this->gC, $this->sat, '20:00', '22:00', 'Sessione C');

        $this->service()->request($this->evA, $this->fri, $this->uA);
        $this->service()->request($evC, $this->fri, $this->uC);

        $this->actingAs($this->uB)->delete(route('events.destroy', $this->evB));

        $this->assertSame($this->fri->toDateString(), $this->evA->fresh()->start_time->toDateString());
        $this->assertSame($this->sat->toDateString(), $evC->fresh()->start_time->toDateString());
        $this->assertSame(1, SessionSwapRequest::pending()->count());
        $this->assertSame($evC->id, SessionSwapRequest::pending()->firstOrFail()->event_id);
    }

    public function test_freed_day_cascades_to_other_waiting_requests(): void
    {
        $evC = $this->makeSession($this->gC, $this->sat, '20:00', '22:00', 'Sessione C');

        $this->service()->request($this->evA, $this->fri, $this->uA); // A: gio -> ven (in attesa)
        $this->service()->request($evC, $this->thu, $this->uC);       // C: sab -> gio (in attesa)

        $this->actingAs($this->uB)->delete(route('events.destroy', $this->evB));

        // ven libero -> A si sposta -> gio libero -> C si sposta
        $this->assertSame($this->fri->toDateString(), $this->evA->fresh()->start_time->toDateString());
        $this->assertSame($this->thu->toDateString().' 20:00', $evC->fresh()->start_time->format('Y-m-d H:i'));
        $this->assertSame(0, SessionSwapRequest::pending()->count());
        Notification::assertSentTo($this->uA, SessionMoved::class);
        Notification::assertSentTo($this->uC, SessionMoved::class);
    }

    public function test_waiting_request_blocked_by_an_assembly_stays_pending(): void
    {
        $this->service()->request($this->evA, $this->fri, $this->uA);
        $this->makeEvent('assembly', $this->fri, '21:30', '22:30', 'Assemblea');

        $this->actingAs($this->uB)->delete(route('events.destroy', $this->evB));

        $this->assertSame($this->thu->toDateString(), $this->evA->fresh()->start_time->toDateString());
        $this->assertSame(1, SessionSwapRequest::pending()->count());
        Notification::assertNothingSent();
    }

    public function test_manager_cannot_change_day_from_the_edit_form_but_can_edit_text(): void
    {
        $this->actingAs($this->uA)->get(route('events.edit', $this->evA))
            ->assertOk()
            ->assertDontSee('name="start_time"', false);

        $this->actingAs($this->uA)->put(route('events.update', $this->evA), [
            'title' => 'Nuovo titolo',
            'description' => 'Nuova descrizione',
            'start_time' => $this->sat->copy()->setTime(21, 0)->format('Y-m-d H:i:s'),
            'end_time' => $this->sat->copy()->setTime(23, 0)->format('Y-m-d H:i:s'),
        ])->assertRedirect(route('events.show', $this->evA));

        $a = $this->evA->fresh();
        $this->assertSame('Nuovo titolo', $a->title);
        $this->assertSame($this->thu->toDateString().' 21:00', $a->start_time->format('Y-m-d H:i'));
    }

    public function test_staff_can_still_edit_day_by_hand(): void
    {
        $admin = $this->makeUser('CD');

        $this->actingAs($admin)->get(route('events.edit', $this->evA))
            ->assertOk()
            ->assertSee('name="start_time"', false);
    }

    public function test_show_page_offers_day_change_request_and_cancel_session(): void
    {
        $this->actingAs($this->uA)->get(route('events.show', $this->evA))
            ->assertOk()
            ->assertSee('Richiedi cambio giorno')
            ->assertSee('Annulla sessione');

        // un membro senza permesso non vede i pulsanti
        $plain = $this->makeUser();
        $this->gA->addUser($plain);
        $this->actingAs($plain)->get(route('events.show', $this->evA))
            ->assertOk()
            ->assertDontSee('Richiedi cambio giorno')
            ->assertDontSee('Annulla sessione');
    }
}
