<?php

namespace App\Http\Controllers;

use App\Exceptions\SchedulingException;
use App\Models\Event;
use App\Models\Group;
use App\Services\CalendarScheduler;
use App\Services\SessionSwapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EventController extends Controller
{
    public function __construct(private CalendarScheduler $scheduler, private SessionSwapService $swaps)
    {
    }

    /**
     * Form di creazione evento/sessione.
     */
    public function create(Request $request): View
    {
        $user = Auth::user();
        $isStaff = $user->isStaff();

        if ($isStaff) {
            $groups = Group::active()->orderBy('name')->get();
            $noPermission = false;
        } else {
            $groups = $user->managedGroups()->orderBy('name')->get();
            $noPermission = $groups->isEmpty();
        }

        return view('events.create', [
            'groups' => $groups,
            'isStaff' => $isStaff,
            'defaultType' => $request->query('type', 'session'),
            'noPermission' => $noPermission,
            'noRooms' => $isStaff && ! \App\Models\Room::active()->exists(),
        ]);
    }

    /**
     * Salvataggio di un nuovo evento/sessione.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:session,assembly,association_event'],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'group_id' => ['nullable', 'required_if:type,session', 'exists:groups,id'],
        ]);

        if (in_array($validated['type'], ['assembly', 'association_event'])) {
            Gate::authorize('manage-assemblies');
            $validated['group_id'] = null;
        } else {
            $group = Group::findOrFail($validated['group_id']);
            if (! $group->is_active) {
                return back()->withErrors(['group_id' => 'Non è possibile creare sessioni per gruppi non attivi.'])->withInput();
            }
            Gate::authorize('manage-group-sessions', $group);
        }

        try {
            $event = $this->scheduler->createEvent($validated, $user);
        } catch (SchedulingException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        return redirect()->route('events.show', $event)->with('success', 'Evento creato con successo!');
    }

    /**
     * Dettaglio con storico modifiche.
     */
    public function show(Event $event): View
    {
        $event->load(['group', 'creator', 'logs.user', 'swapRequests.requester']);

        return view('events.show', ['event' => $event]);
    }

    /**
     * Form di modifica / spostamento.
     */
    public function edit(Event $event): View
    {
        $this->authorizeEdit($event);

        $user = Auth::user();
        $isStaff = $user->isStaff();
        $groups = $isStaff ? Group::active()->orderBy('name')->get() : $user->managedGroups()->orderBy('name')->get();

        return view('events.edit', [
            'event' => $event,
            'groups' => $groups,
            'isStaff' => $isStaff,
        ]);
    }

    /**
     * Aggiornamento / spostamento.
     */
    public function update(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeEdit($event);

        $user = Auth::user();

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];

        // Giorno e orario si cambiano a mano solo in casi eccezionali (staff);
        // gli altri usano "Richiedi cambio giorno" dalla pagina della sessione.
        if ($user->isStaff()) {
            $rules['start_time'] = ['required', 'date'];
            $rules['end_time'] = ['required', 'date', 'after:start_time'];

            if ($event->type === 'session') {
                $rules['group_id'] = ['required', 'exists:groups,id'];
            }
        }

        $validated = $request->validate($rules);

        if (isset($validated['group_id'])) {
            $group = Group::findOrFail($validated['group_id']);
            if (! $group->is_active) {
                return back()->withErrors(['group_id' => 'Il gruppo selezionato non è attivo.'])->withInput();
            }
        }

        $oldStart = $event->start_time->copy();

        try {
            $event = $this->scheduler->updateEvent($event, $validated, $user);
        } catch (SchedulingException $e) {
            $message = $e->getMessage();
            if ($e->reason === 'full') {
                $message .= ' Per cambiare giorno senza forzare usa «Richiedi cambio giorno» dalla pagina dell\'evento.';
            }

            return back()->withErrors([$e->field => $message])->withInput();
        }

        // Se la sessione ha lasciato un giorno, chi era in attesa di quel posto può passare.
        if ($event->type === 'session' && $oldStart->toDateString() !== $event->start_time->toDateString()) {
            $this->swaps->fulfillWaiting($oldStart);
        }

        return redirect()->route('events.show', $event)->with('success', 'Evento aggiornato con successo!');
    }

    /**
     * Annullamento sessione / eliminazione evento (staff o chi gestisce le sessioni del gruppo).
     * Annullare una sessione libera il posto: le richieste in attesa di quel giorno vengono eseguite.
     */
    public function destroy(Event $event): RedirectResponse
    {
        Gate::authorize('delete-event', $event);

        $isSession = $event->type === 'session';
        $day = $event->start_time->copy();

        $this->scheduler->withLock(fn () => $event->delete());

        if ($isSession) {
            $this->swaps->fulfillWaiting($day);
        }

        return redirect()->route('calendar.index')->with('success', $isSession ? 'Sessione annullata.' : 'Evento rimosso con successo.');
    }

    private function authorizeEdit(Event $event): void
    {
        if ($event->type === 'session') {
            Gate::authorize('update-session', $event);
        } else {
            Gate::authorize('manage-assemblies');
        }
    }
}
