<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\User;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function __construct()
    {
        // Require manage-groups permission for all group actions
    }

    /**
     * Display a listing of groups.
     */
    public function index(): View
    {
        Gate::authorize('manage-groups');

        $groups = Group::with(['users' => function ($q) {
            $q->orderBy('name');
        }])->withCount('events')->orderByDesc('is_active')->orderBy('name')->get();

        $allUsers = User::orderBy('name')->get();

        return view('admin.groups.index', [
            'groups' => $groups,
            'allUsers' => $allUsers,
        ]);
    }

    /**
     * Store a newly created group.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-groups');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $group = Group::create([
            'name' => $validated['name'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.groups.index')->with('success', "Gruppo '{$group->name}' creato con successo!");
    }

    /**
     * Update an existing group.
     */
    public function update(Request $request, Group $group): RedirectResponse
    {
        Gate::authorize('manage-groups');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        $group->update($validated);

        return redirect()->route('admin.groups.index')->with('success', "Gruppo '{$group->name}' aggiornato con successo!");
    }

    /**
     * Assign a user to the group.
     */
    public function assignUser(Request $request, Group $group): RedirectResponse
    {
        Gate::authorize('manage-groups');

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        try {
            $user = User::findOrFail($validated['user_id']);
            $group->addUser($user);

            return redirect()->route('admin.groups.index')->with('success', "Utente {$user->name} aggiunto a {$group->name}.");
        } catch (DomainException $e) {
            return back()->withErrors(['user_id' => $e->getMessage()]);
        }
    }

    /**
     * Remove a user from the group.
     */
    public function removeUser(Group $group, User $user): RedirectResponse
    {
        Gate::authorize('manage-groups');

        $group->users()->detach($user->id);

        return redirect()->route('admin.groups.index')->with('success', "Utente {$user->name} rimosso da {$group->name}.");
    }
}
