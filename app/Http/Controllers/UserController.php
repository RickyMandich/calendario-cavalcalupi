<?php

namespace App\Http\Controllers;

use App\Models\SessionSwapRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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
        $users = User::with('roles')->withCount('groups')
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

    /**
     * Conferma a mano l'email di un utente (o ne revoca la conferma).
     * La conferma può essere tolta solo a un Membro senza gruppi: altrimenti si violerebbe l'invariante
     * "non verificato = solo Membro, nessun gruppo" (vedi User).
     */
    public function setVerified(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manage-users');

        $validated = $request->validate([
            'verified' => ['required', 'boolean'],
        ]);

        if ($user->is($request->user())) {
            return back()->withErrors(['verified' => 'Non puoi modificare la conferma email del tuo stesso account.']);
        }

        if ($request->boolean('verified')) {
            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
                Log::info('Email utente confermata manualmente', ['by' => $request->user()->id, 'user' => $user->id]);
            }

            return redirect()->route('admin.users.index')->with('success', "Email di {$user->name} confermata.");
        }

        if ($user->hasVerifiedEmail()) {
            if ($user->isStaff() || $user->groups()->exists()) {
                return back()->withErrors(['verified' => 'La conferma si può togliere solo a un Membro che non appartiene a nessun gruppo.']);
            }

            $user->forceFill(['email_verified_at' => null])->save();
            Log::info('Conferma email utente revocata', ['by' => $request->user()->id, 'user' => $user->id]);
        }

        return redirect()->route('admin.users.index')->with('success', "Conferma email di {$user->name} rimossa.");
    }

    /**
     * Reinvia all'utente il link di verifica dell'email.
     */
    public function resendVerification(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manage-users');

        if ($user->hasVerifiedEmail()) {
            return back()->withErrors(['email' => 'L\'email di questo utente è già confermata.']);
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            // SMTP non configurato/raggiungibile: niente 500, errore leggibile e traccia nei log.
            report($e);

            return back()->withErrors(['email' => 'Non è stato possibile inviare l\'email in questo momento. Riprova più tardi.']);
        }

        return back()->with('success', "Email di conferma inviata a {$user->email}.");
    }

    /**
     * Elimina un utente. Sessioni/eventi creati da lui e relativi log restano (riferimento a NULL, vedi migrazione
     * `make_user_references_nullable_on_user_delete`); le sue richieste di scambio pendenti vengono annullate;
     * appartenenze ai gruppi e ruoli spariscono con l'utente.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manage-users');

        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'Non puoi eliminare il tuo stesso account.']);
        }

        if ($user->hasRole('ADMIN') && User::role('ADMIN')->count() <= 1) {
            return back()->withErrors(['user' => 'Deve restare almeno un Admin.']);
        }

        $id = $user->id;
        $name = $user->name;

        DB::transaction(function () use ($user, $request) {
            SessionSwapRequest::pending()
                ->where('requested_by', $user->id)
                ->get()
                ->each(fn (SessionSwapRequest $r) => $r->markCancelled($request->user(), 'user_deleted'));

            $user->delete();
        });

        // Con le sessioni su database l'utente eliminato viene scollegato subito.
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $id)->delete();
        }

        Log::warning('Utente eliminato', ['by' => $request->user()->id, 'user' => $id]);

        return redirect()->route('admin.users.index')->with('success', "Utente {$name} eliminato.");
    }
}
?>
