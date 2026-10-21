<?php

namespace Tests\Feature;

use App\Exceptions\SchedulingException;
use App\Models\Event;
use App\Models\Group;
use App\Models\SessionSwapRequest;
use App\Models\User;
use App\Notifications\SessionSwapped;
use App\Services\SessionSwapService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesRooms;
use Tests\TestCase;

class SessionSwapTest extends TestCase
{
    use CreatesRooms;
    use RefreshDatabase;

    private Group $gA;
    private Group $gB;
    private User $uA;
    private User $uB;
    private $thu;
    private $fri;
    private Event $evA;
    private Event $evB;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->createRoom(1, [4, 5]);
        $this->thu = $this->nextWeekday(4);
        $this->fri = $this->nextWeekday(5);

        $this->gA = Group::create(['name' => 'Alfa', 'is_active' => true]);
        $this->gB = Group::create(['name' => 'Beta', 'is_active' => true]);
        $this->uA = $this->makeUser();
        $this->uB = $this->makeUser();
        foreach ([[$this->gA, $this->uA], [$this->gB, $this->uB]] as [$g, $u]) {
            $g->addUser($u);
            $g->setSessionManager($u, true);
        }

        // A giovedì 21-23, B venerdì 18:30-20 (orari e durate diversi)
        $this->evA = $this->makeSession($this->gA, $this->thu, '21:00', '23:00', 'Sessione A');
        $this->evB = $this->makeSession($this->gB, $this->fri, '18:30', '20:00', 'Sessione B');
    }

    private function service(): SessionSwapService
    {
        return app(SessionSwapService::class);
    }

    public function test_single_request_stays_pending_and_changes_nothing(): void
    {
        $result = $this->service()->request($this->evA, $this->fri, $this->uA);

        $this->assertSame('pending', $result['status']);
        $r = SessionSwapRequest::firstOrFail();
        $this->assertSame('pending', $r->status);
        $this->assertSame(1, $r->pending_flag);
        $this->assertSame($this->thu->toDateString(), $this->evA->fresh()->start_time->toDateString());
        $this->assertDatabaseMissing('event_logs', ['event_id' => $this->evA->id, 'action' => 'rescheduled']);
        Notification::assertNothingSent();
    }

    public function test_mirror_requests_swap_days_keeping_times_and_durations(): void
    {
        $this->service()->request($this->evA, $this->fri, $this->uA);
        $result = $this->service()->request($this->evB, $this->thu, $this->uB);

        $this->assertSame('completed', $result['status']);

        $a = $this->evA->fresh();
        $b = $this->evB->fresh();
        $this->assertSame($this->fri->toDateString().' 21:00', $a->start_time->format('Y-m-d H:i'));
        $this->assertSame($this->fri->toDateString().' 23:00', $a->end_time->format('Y-m-d H:i'));
        $this->assertSame($this->thu->toDateString().' 18:30', $b->start_time->format('Y-m-d H:i'));
        $this->assertSame($this->thu->toDateString().' 20:00', $b->end_time->format('Y-m-d H:i'));

        $reqs = SessionSwapRequest::orderBy('id')->get();
        $this->assertSame(['completed', 'completed'], $reqs->pluck('status')->all());
        $this->assertSame($reqs[1]->id, $reqs[0]->matched_request_id);
        $this->assertSame($reqs[0]->id, $reqs[1]->matched_request_id);
        $this->assertNull($reqs[0]->pending_flag);

        foreach ([$this->evA, $this->evB] as $e) {
            $log = $e->logs()->where('action', 'rescheduled')->first();
            $this->assertNotNull($log);
            $this->assertSame('swap', $log->new_data['_context']['reason']);
        }

        Notification::assertSentTo($this->uA, SessionSwapped::class);
        Notification::assertSentTo($this->uB, SessionSwapped::class);
        Notification::assertCount(2);
    }

    public function test_non_mirror_requests_do_not_match(): void
    {
        $sat = $this->nextWeekday(6);
        $this->service()->request($this->evA, $this->fri, $this->uA);
        $result = $this->service()->request($this->evB, $sat, $this->uB);

        $this->assertSame('pending', $result['status']);
        $this->assertSame(2, SessionSwapRequest::pending()->count());
    }

    public function test_one_pending_per_session_but_other_sessions_of_same_group_are_ok(): void
    {
        $this->service()->request($this->evA, $this->fri, $this->uA);

        try {
            $this->service()->request($this->evA, $this->nextWeekday(6), $this->uA);
            $this->fail('Seconda richiesta sulla stessa sessione: doveva essere rifiutata');
        } catch (SchedulingException) {
            $this->addToAssertionCount(1);
        }

        $second = $this->makeSession($this->gA, $this->thu->copy()->addWeek(), '21:00', '23:00', 'Altra A');
        $this->service()->request($second, $this->fri->copy()->addWeek(), $this->uA);
        $this->assertSame(2, SessionSwapRequest::pending()->count());
    }

    public function test_permissions(): void
    {
        $plain = $this->makeUser();
        $this->gA->addUser($plain);

        try {
            $this->service()->request($this->evA, $this->fri, $plain);
            $this->fail('Membro senza permesso: doveva essere negato');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        // il permesso su A non vale per la sessione di B
        try {
            $this->service()->request($this->evB, $this->thu, $this->uA);
            $this->fail('Permesso di un altro gruppo: doveva essere negato');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        // staff sempre
        $result = $this->service()->request($this->evA, $this->fri, $this->makeUser('CD'));
        $this->assertSame('pending', $result['status']);

        $this->post(route('swap-requests.store', $this->evA), ['target_date' => $this->fri->toDateString()])
            ->assertRedirect(route('login'));
    }

    public function test_cancel_by_requester_and_staff_not_by_stranger_and_cancelled_request_does_not_match(): void
    {
        $this->service()->request($this->evA, $this->fri, $this->uA);
        $r = SessionSwapRequest::firstOrFail();

        $stranger = $this->makeUser();
        $this->actingAs($stranger)->delete(route('swap-requests.destroy', $r))->assertForbidden();
        $this->assertSame('pending', $r->fresh()->status);

        $this->actingAs($this->uA)->delete(route('swap-requests.destroy', $r))->assertRedirect();
        $this->assertSame('cancelled', $r->fresh()->status);
        $this->assertNull($r->fresh()->pending_flag);

        // la richiesta annullata non si abbina più
        $result = $this->service()->request($this->evB, $this->thu, $this->uB);
        $this->assertSame('pending', $result['status']);

        // e si può inviare una nuova richiesta dopo l'annullamento
        $again = $this->service()->request($this->evA, $this->fri, $this->uA);
        $this->assertSame('completed', $again['status']);
    }

    public function test_changing_the_session_date_cancels_pending_requests(): void
    {
        $this->service()->request($this->evA, $this->fri, $this->uA);

        $this->evA->fresh()->update([
            'start_time' => $this->thu->copy()->setTime(20, 0),
            'end_time' => $this->thu->copy()->setTime(22, 0),
        ]);
        $this->assertSame('pending', SessionSwapRequest::first()->status); // stesso giorno: resta

        $this->evA->fresh()->update([
            'start_time' => $this->thu->copy()->addDay()->setTime(20, 0),
            'end_time' => $this->thu->copy()->addDay()->setTime(22, 0),
        ]);

        $r = SessionSwapRequest::first();
        $this->assertSame('cancelled', $r->status);
        $this->assertSame('session_changed', $r->cancel_reason);
    }

    public function test_deleting_a_session_removes_its_requests(): void
    {
        $this->service()->request($this->evA, $this->fri, $this->uA);
        $this->evA->delete();

        $this->assertSame(0, SessionSwapRequest::count());
    }

    public function test_assembly_conflict_leaves_everything_unchanged_and_pending(): void
    {
        $this->service()->request($this->evA, $this->fri, $this->uA);
        // assemblea venerdì che si sovrappone al 21-23 che A manterrebbe
        $this->makeEvent('assembly', $this->fri, '20:30', '22:30', 'Assemblea');

        $result = $this->service()->request($this->evB, $this->thu, $this->uB);

        $this->assertSame('conflict', $result['status']);
        $this->assertSame($this->thu->toDateString(), $this->evA->fresh()->start_time->toDateString());
        $this->assertSame($this->fri->toDateString(), $this->evB->fresh()->start_time->toDateString());
        $this->assertSame(2, SessionSwapRequest::pending()->count());
        Notification::assertNothingSent();
    }

    public function test_past_and_inactive_group_are_rejected(): void
    {
        $past = Event::create([
            'title' => 'Passata', 'type' => 'session', 'group_id' => $this->gA->id,
            'start_time' => now()->subDays(2)->setTime(21, 0), 'end_time' => now()->subDays(2)->setTime(23, 0),
            'created_by' => $this->uA->id,
        ]);

        try {
            $this->service()->request($past, $this->fri, $this->uA);
            $this->fail('Sessione passata: doveva essere rifiutata');
        } catch (SchedulingException) {
            $this->addToAssertionCount(1);
        }

        try {
            $this->service()->request($this->evA, now()->subDay(), $this->uA);
            $this->fail('Data di destinazione nel passato: doveva essere rifiutata');
        } catch (SchedulingException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_lowest_id_candidate_is_matched(): void
    {
        // due sessioni di B in giorni diversi chiedono entrambe il giovedì di A... ma solo una "specchia" la from_date di A
        $evB2 = $this->makeSession($this->gB, $this->fri->copy()->addWeek(), '18:00', '19:00', 'B2');

        $this->service()->request($this->evB, $this->thu, $this->uB);   // from fri, to thu
        $this->service()->request($evB2, $this->thu, $this->uB);        // from fri+7, to thu

        $result = $this->service()->request($this->evA, $this->fri, $this->uA); // from thu, to fri: specchia solo evB
        $this->assertSame('completed', $result['status']);
        $this->assertSame($this->thu->toDateString(), $this->evB->fresh()->start_time->toDateString());
        $this->assertSame($this->fri->copy()->addWeek()->toDateString(), $evB2->fresh()->start_time->toDateString());
    }

    public function test_swap_is_atomic_when_a_save_fails(): void
    {
        $this->service()->request($this->evA, $this->fri, $this->uA);

        $failing = $this->evB->id;
        Event::saving(function (Event $e) use ($failing) {
            if ($e->id === $failing && $e->isDirty('start_time')) {
                throw new \RuntimeException('boom');
            }
        });

        try {
            $this->service()->request($this->evB, $this->thu, $this->uB);
            $this->fail('Doveva fallire');
        } catch (\RuntimeException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame($this->thu->toDateString(), $this->evA->fresh()->start_time->toDateString());
        $this->assertSame($this->fri->toDateString(), $this->evB->fresh()->start_time->toDateString());
        $this->assertSame(1, SessionSwapRequest::pending()->count()); // quella di A; la seconda è stata annullata col rollback
    }

    public function test_ics_feed_reflects_swapped_dates(): void
    {
        $this->service()->request($this->evA, $this->fri, $this->uA);
        $this->service()->request($this->evB, $this->thu, $this->uB);

        $content = $this->get(route('calendar.ics', ['token' => $this->uA->ics_token]))->getContent();
        $this->assertMatchesRegularExpression('/DTSTART[^:]*:'.$this->fri->format('Ymd').'T/', $content);
    }

    public function test_direct_move_to_free_day_and_full_day_hint(): void
    {
        $admin = $this->makeUser('ADMIN');
        $sat = $this->nextWeekday(6);
        $this->createRoom(1, [6]);

        $this->actingAs($admin)->put(route('events.update', $this->evA), [
            'title' => 'Sessione A', 'group_id' => $this->gA->id,
            'start_time' => $sat->copy()->setTime(21, 0)->format('Y-m-d H:i:s'),
            'end_time' => $sat->copy()->setTime(23, 0)->format('Y-m-d H:i:s'),
        ])->assertRedirect();
        $this->assertDatabaseHas('event_logs', ['event_id' => $this->evA->id, 'action' => 'rescheduled']);

        // venerdì è pieno (evB): messaggio con suggerimento scambio
        $this->actingAs($admin)->put(route('events.update', $this->evA), [
            'title' => 'Sessione A', 'group_id' => $this->gA->id,
            'start_time' => $this->fri->copy()->setTime(21, 0)->format('Y-m-d H:i:s'),
            'end_time' => $this->fri->copy()->setTime(23, 0)->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('start_time');
        $this->assertStringContainsString('scambio', session('errors')->first('start_time'));
    }
}
