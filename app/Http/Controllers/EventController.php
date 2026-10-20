<?php

namespace App\Http\Controllers;

use App\Services\CalendarScheduler;
use App\Exceptions\SchedulingException;

// inside store method replace Event::create with scheduler
        try {
            $event = $scheduler->createEvent($validated, $user);
        } catch (SchedulingException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

// inside update method replace $event->update with scheduler update
        try {
            $event = $scheduler->updateEvent($event, $validated);
        } catch (SchedulingException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }


class EventController extends Controller
{
    /**
     * Show form to create an event or session.
     */
    public function create(Request $request): View
    {
        $user = Auth::user();
        $isStaff = $user->isStaff();

        if ($isStaff) {
            $groups = Group::active()->orderBy('name')->get();
        } else {
            $groups = $user->managedGroups()->orderBy('name')->get();
            $noPermission = $groups->isEmpty();
        }

        $defaultType = $request->query('type', $isStaff ? 'session' : 'session');

        return view('events.create', [
            'groups' => $groups,
            'isStaff' => $isStaff,
            'defaultType' => $defaultType,
            'noPermission' => $noPermission ?? false,
        ]);
    }

    /**
     * Store a new event or session.
     */
    public function store(Request $request): RedirectResponse
    {
                $user = Auth::user();
        $isStaff = $user->isStaff();

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
        }

        if ($validated['type'] === 'session') {
            $group = Group::findOrFail($validated['group_id']);
            if (!$group->is_active) {
                return back()->withErrors(['group_id' => 'Non è possibile creare sessioni per gruppi non attivi.'])->withInput();
            }
            // Authorize session creation for the group
            Gate::authorize('manage-group-sessions', $group);
        }


        $scheduler = app(CalendarScheduler::class);
        try {
            $event = $scheduler->createEvent($validated, $user);
        } catch (SchedulingException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        return redirect()->route('events.show', $event)->with('success', 'Evento creato con successo!');
    }

    /**
     * Show event details along with modification history.
     */
    public function show(Event $event): View
    {
        $event->load(['group', 'creator', 'logs.user']);

        return view('events.show', [
            'event' => $event,
        ]);
    }

    /**
     * Show form to edit / reschedule an event.
     */
    public function edit(Event $event): View
    {
        if ($event->type === 'session') {
            Gate::authorize('update-session', $event);
        } else {
            Gate::authorize('manage-assemblies');
        }

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
     * Update / reschedule an event.
     */
    public function update(Request $request, Event $event): RedirectResponse
    {
        if ($event->type === 'session') {
            Gate::authorize('update-session', $event);
        } else {
            Gate::authorize('manage-assemblies');
        }

        $user = Auth::user();
        $isStaff = $user->isStaff();

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
        ];

        if ($isStaff && $event->type === 'session') {
            $rules['group_id'] = ['required', 'exists:groups,id'];
        }

        $validated = $request->validate($rules);

        if (isset($validated['group_id'])) {
            $group = Group::findOrFail($validated['group_id']);
            if (!$group->is_active) {
                return back()->withErrors(['group_id' => 'Il gruppo selezionato non è attivo.'])->withInput();
            }
        }

        $scheduler = app(CalendarScheduler::class);
        try {
            $event = $scheduler->updateEvent($event, $validated);
        } catch (SchedulingException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        return redirect()->route('events.show', $event)->with('success', 'Evento aggiornato con successo!');
    }

    /**
     * Delete an event.
     */
    public function destroy(Event $event): RedirectResponse
    {
        Gate::authorize('delete-event', $event);
        $event->delete();

        return redirect()->route('calendar.index')->with('success', 'Evento rimosso con successo.');
    }
}
