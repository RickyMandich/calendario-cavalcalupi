<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended(route('calendar.index'));
        }

        return back()->withErrors([
            'email' => 'Credenziali non valide.',
        ])->onlyInput('email');
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // INVARIANTE: dalla registrazione pubblica esce SOLO un utente "Membro", senza gruppi
        // e senza permessi. Ruoli superiori e gruppi li assegna solo CD/Admin da interfaccia.
        // Si usano esplicitamente solo i campi validati (mai $request->all()).
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Il ruolo viene creato se manca, così la registrazione non si rompe su un DB non seedato.
        Role::firstOrCreate(['name' => 'Membro', 'guard_name' => 'web']);
        $user->assignRole('Membro');

        // Invia il link di verifica email. Se l'SMTP non è configurato/raggiungibile la registrazione
        // non deve fallire: l'utente può chiedere un nuovo invio dal banner.
        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            report($e);
        }

        Auth::login($user);

        return redirect()->route('calendar.index')->with('success', 'Registrazione completata! Ti abbiamo inviato un\'email: conferma l\'indirizzo per essere assegnato ai gruppi e usare il feed ICS.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Quick login: disponibile SOLO con APP_ENV=local (la rotta non è nemmeno registrata altrove).
     */
    public function quickLogin(string $role): RedirectResponse
    {
        abort_unless(app()->environment('local'), 404);

        $emailMap = [
            'admin' => 'admin@cavalcalupi.it',
            'cd' => 'cd@cavalcalupi.it',
            'membro' => 'membro@cavalcalupi.it',
        ];

        if (! isset($emailMap[$role])) {
            abort(404);
        }

        $user = User::where('email', $emailMap[$role])->firstOrFail();
        Auth::login($user);
        request()->session()->regenerate();

        return redirect()->route('calendar.index')->with('success', "Accesso eseguito come {$user->name} ({$role})");
    }
}
