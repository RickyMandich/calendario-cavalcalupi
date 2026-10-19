<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

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

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->assignRole('Membro');

        Auth::login($user);

        return redirect()->route('calendar.index')->with('success', 'Registrazione completata con successo!');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Quick login for local development / testing
     */
    public function quickLogin(string $role): RedirectResponse
    {
        $emailMap = [
            'admin' => 'admin@cavalcalupi.it',
            'cd' => 'cd@cavalcalupi.it',
            'membro' => 'membro@cavalcalupi.it',
        ];

        if (!isset($emailMap[$role])) {
            abort(404);
        }

        $user = User::where('email', $emailMap[$role])->firstOrFail();
        Auth::login($user);
        request()->session()->regenerate();

        return redirect()->route('calendar.index')->with('success', "Accesso eseguito come {$user->name} ({$role})");
    }
}
