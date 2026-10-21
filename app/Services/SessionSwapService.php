<?php

namespace App\Services;

use App\Exceptions\SchedulingException;
use App\Models\Event;
use App\Models\SessionSwapRequest;
use App\Models\User;
use App\Notifications\SessionSwapped;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Scambio di giorno tra due sessioni di gruppi diversi.
 * Ogni gruppo invia una richiesta; quando le due richieste combaciano "a specchio"
 * i giorni si scambiano in un'unica transazione, mantenendo orario e durata di ciascuna sessione.
 */
class SessionSwapService
{
    public function __construct(private CalendarScheduler $scheduler)
    {
    }

    protected function tz(): string
    {
        return config('app.timezone');
    }

    /**
     * @return array{request: SessionSwapRequest, status: string, message: string}  status: pending|completed|conflict
     *
     * @throws SchedulingException
     * @throws AuthorizationException
     */
    public function request(Event $event, CarbonInterface $targetDate, User $by): array
    {
        $outcome = $this->scheduler->withLock(function () use ($event, $targetDate, $by) {
            $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();

            Gate::forUser($by)->authorize('update-session', $event);

            if ($event->type !== 'session' || ! $event->group || ! $event->group->is_active) {
                throw new SchedulingException('Solo le sessioni di gruppi attivi possono essere scambiate.', 'target_date');
            }

            $now = now($this->tz());
            $target = Carbon::parse($targetDate->toDateString(), $this->tz())->startOfDay();

            if (! $by->isStaff() && $event->start_time < $now) {
                throw new SchedulingException('Non si possono scambiare sessioni già iniziate.', 'target_date', 'past');
            }
            if ($target < $now->copy()->startOfDay()) {
                throw new SchedulingException('La data di destinazione non può essere nel passato.', 'target_date', 'past');
            }
            if ($target->toDateString() === $event->start_time->toDateString()) {
                throw new SchedulingException('La sessione è già in quel giorno.', 'target_date');
            }
            if (SessionSwapRequest::pending()->where('event_id', $event->id)->exists()) {
                throw new SchedulingException('Questa sessione ha già una richiesta in corso: annullala prima di inviarne un\'altra.', 'target_date');
            }

            $swapRequest = SessionSwapRequest::create([
                'event_id' => $event->id,
                'group_id' => $event->group_id,
                'requested_by' => $by->id,
                'from_date' => $event->start_time->toDateString(),
                'target_date' => $target->toDateString(),
                'status' => 'pending',
                'pending_flag' => 1,
            ]);

            return $this->tryMatch($swapRequest);
        });

        // Notifiche dopo il commit
        foreach ($outcome['notify'] ?? [] as $n) {
            $n['user']->notify(new SessionSwapped(
                $n['event'], $n['old_start'], $n['new_start'], $n['other_title'], $n['other_group']
            ));
        }

        return [
            'request' => $outcome['request'],
            'status' => $outcome['status'],
            'message' => $outcome['message'],
        ];
    }

    /**
     * Annulla una richiesta pendente (staff, richiedente o chi gestisce le sessioni del gruppo).
     */
    public function cancel(SessionSwapRequest $swapRequest, User $by): void
    {
        $this->scheduler->withLock(function () use ($swapRequest, $by) {
            $fresh = SessionSwapRequest::whereKey($swapRequest->id)->firstOrFail();

            if ($fresh->status !== 'pending') {
                throw new SchedulingException('La richiesta non è più in attesa.', 'target_date');
            }
            if (! $this->canCancel($fresh, $by)) {
                throw new AuthorizationException('Non puoi annullare questa richiesta.');
            }

            $fresh->markCancelled($by, 'manual');
        });
    }

    public function canCancel(SessionSwapRequest $swapRequest, User $by): bool
    {
        return $by->isStaff()
            || $swapRequest->requested_by === $by->id
            || $by->canManageSessionsOf($swapRequest->group_id);
    }

    /**
     * @return array{request: SessionSwapRequest, status: string, message: string, notify?: array}
     */
    protected function tryMatch(SessionSwapRequest $r): array
    {
        $candidates = SessionSwapRequest::pending()
            ->where('id', '!=', $r->id)
            ->where('group_id', '!=', $r->group_id)
            ->whereDate('from_date', $r->target_date->toDateString())
            ->whereDate('target_date', $r->from_date->toDateString())
            ->with(['event.group'])
            ->orderBy('id')
            ->get()
            ->filter(fn (SessionSwapRequest $c) => $c->event
                && $c->event->type === 'session'
                && $c->event->group?->is_active
                && $c->event->start_time->toDateString() === $c->from_date->toDateString());

        $match = $candidates->first();
        if (! $match) {
            return [
                'request' => $r,
                'status' => 'pending',
                'message' => "Richiesta registrata: in attesa che l'altro gruppo invii la richiesta speculare.",
            ];
        }

        return $this->executeSwap($r, $match);
    }

    protected function executeSwap(SessionSwapRequest $a, SessionSwapRequest $b): array
    {
        $ea = Event::with('group')->whereKey($a->event_id)->lockForUpdate()->firstOrFail();
        $eb = Event::with('group')->whereKey($b->event_id)->lockForUpdate()->firstOrFail();

        if ($ea->start_time->toDateString() !== $a->from_date->toDateString()
            || $eb->start_time->toDateString() !== $b->from_date->toDateString()) {
            return ['request' => $a, 'status' => 'pending', 'message' => 'Le sessioni sono cambiate: richiesta lasciata in attesa.'];
        }

        $plan = [];
        foreach ([[$ea, $a], [$eb, $b]] as [$event, $req]) {
            $duration = abs($event->end_time->getTimestamp() - $event->start_time->getTimestamp());
            $newStart = Carbon::parse($req->target_date->toDateString(), $this->tz())->setTimeFrom($event->start_time);
            $newEnd = $newStart->copy()->addSeconds($duration);
            $plan[] = [$event, $req, $newStart, $newEnd];
        }

        // La capienza non cambia (un posto liberato, uno occupato per giorno): si ricontrollano solo le assemblee.
        foreach ($plan as [$event, , $newStart, $newEnd]) {
            if ($this->scheduler->overlapping($newStart, $newEnd, ['assembly'], $event)) {
                return [
                    'request' => $a,
                    'status' => 'conflict',
                    'message' => "Scambio non eseguito: nel nuovo giorno e orario c'è un'assemblea. Le richieste restano in attesa.",
                ];
            }
        }

        // Prima si marcano completate (così l'observer non le annulla come "session_changed")
        $a->markCompleted($b);
        $b->markCompleted($a);

        $notify = [];
        foreach ($plan as $i => [$event, $req, $newStart, $newEnd]) {
            [$other] = $plan[1 - $i];
            $oldStart = $event->start_time->copy();

            $event->logContext = [
                'reason' => 'swap',
                'swap_with_event_id' => $other->id,
                'swap_with_title' => $other->title,
                'swap_with_group' => $other->group?->name,
                'request_ids' => [$a->id, $b->id],
            ];
            $event->start_time = $newStart;
            $event->end_time = $newEnd;
            $event->save();
            $event->logContext = null;

            $notify[] = [
                'user' => $req->requester,
                'event' => $event,
                'old_start' => $oldStart,
                'new_start' => $newStart,
                'other_title' => $other->title,
                'other_group' => $other->group?->name,
            ];
        }

        return [
            'request' => $a->fresh(),
            'status' => 'completed',
            'message' => 'Scambio eseguito: le due sessioni hanno cambiato giorno.',
            'notify' => $notify,
        ];
    }
}
