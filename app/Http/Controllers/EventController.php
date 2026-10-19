<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EventController extends Controller
{
    /**
     * Show form to create an event or session.
     */
    public function create(Request $request): View
    {
        $user = Auth::user();
        $isStaff = $user->hasRole('ADMIN') || $user->hasRole('CD');

        if ($isStaff) {
            $groups = Group::active()->orderBy('name')->get();
        } else {
            $groups = $user->groups()->where('is_active', true)->orderBy('name')->get();
        }

        $defaultType = $request->query('type', $isStaff ? 'session' : 'session');

        return view('events.create', [
            'groups' => $groups,
            'isStaff' => $isStaff,
            'defaultType' => $defaultType,
        ]);
    }

    /**
     * Store a new event or session.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $isStaff = $user->hasRole('ADMIN') || $user->hasRole('CD');

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

            if (!$isStaff && !$user->groups()->where('groups.id', $group->id)->exists()) {
                abort(403, 'Non appartieni a questo gruppo di gioco.');
            }
        }

        $event = Event::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'group_id' => $validated['group_id'] ?? null,
            'created_by' => $user->id,
        ]);

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
        $isStaff = $user->hasRole('ADMIN') || $user->hasRole('CD');
        $groups = $isStaff ? Group::active()->orderBy('name')->get() : $user->groups()->where('is_active', true)->orderBy('name')->get();

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
        $isStaff = $user->hasRole('ADMIN') || $user->hasRole('CD');

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

        $event->update($validated);

        return redirect()->route('events.show', $event)->with('success', 'Evento aggiornato con successo!');
    }

    /**
     * Delete an event.
     */
    public function destroy(Event $event): RedirectResponse
    {
        $user = Auth::user();
        $isStaff = $user->hasRole('ADMIN') || $user->hasRole('CD');

        if (!$isStaff && $event->created_by !== $user->id) {
            abort(403, 'Non hai i permessi per eliminare questo evento.');
        }

        $event->delete();

        return redirect()->route('calendar.index')->with('success', 'Evento rimosso con successo.');
    }
}
