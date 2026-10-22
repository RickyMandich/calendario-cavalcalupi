<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Show the user management page.
     */
    public function index(Request $request): View
    {
        Gate::authorize('manage-users');

        $query = $request->query('q');
        $users = User::with('roles')
            ->when($query, fn($q) => $q->where('name', 'like', "%{$query}%")
                ->orWhere('email', 'like', "%{$query}%"))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'query'));
    }

    /**
     * Update a user's role.
     */
    public function updateRole(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manage-users');

        $validated = $request->validate([
            'role' => ['required', Rule::in(['Membro', 'CD', 'ADMIN'])],
        ]);

        $newRole = $validated['role'];
        $oldRoles = $user->getRoleNames()->toArray();
        $old = $oldRoles ? $oldRoles[0] : null;

        // Un utente con email non verificata può essere solo Membro
        if ($newRole !== 'Membro' && ! $user->hasVerifiedEmail()) {
            return back()->withErrors(['role' => 'Solo gli utenti con email verificata possono essere CD o Admin.']);
        }

        // Prevent removing the last ADMIN
        if ($user->hasRole('ADMIN') && $newRole !== 'ADMIN' && User::role('ADMIN')->count() <= 1) {
            return back()->withErrors(['role' => 'Deve restare almeno un Admin.']);
        }

        $user->syncRoles([$newRole]);
        Log::info('Ruolo utente modificato', [
            'by' => $request->user()->id,
            'user' => $user->id,
            'from' => $old,
            'to' => $newRole,
        ]);

        return redirect()->route('admin.users.index')->with('success', "Ruolo aggiornato a {$newRole}.");
    }
}
?>
