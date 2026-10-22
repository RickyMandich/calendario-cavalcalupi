<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pagina profilo dell'utente loggato. Ogni modulo ha il suo error bag (`profile`, `email`, `password`)
 * perché `current_password` compare in più moduli. Le rotte stanno nel gruppo `auth` ma NON sotto `verified`:
 * anche chi non ha ancora confermato l'email deve poter correggere il proprio indirizzo.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    /**
     * Nome reale.
     */
    public function updateName(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('profile', [
            'name' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->update(['name' => $validated['name']]);

        return redirect()->route('profile.edit')->with('success', 'Nome aggiornato.');
    }

    /**
     * Email. Se cambia, la verifica riparte: il feed ICS resta bloccato finché il nuovo indirizzo non è confermato.
     * Ruoli e gruppi già assegnati non vengono toccati.
     */
    public function updateEmail(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validateWithBag('email', [
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['required', 'current_password'],
        ]);

        if (strcasecmp($validated['email'], $user->email) === 0) {
            return redirect()->route('profile.edit')->with('success', 'L\'indirizzo email è già quello attuale: nessuna modifica.');
        }

        $user->forceFill([
            'email' => $validated['email'],
            'email_verified_at' => null,
        ])->save();

        Log::info('Email utente modificata', ['user' => $user->id]);

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            // SMTP non configurato/raggiungibile: la modifica resta, l'utente può reinviare dal banner.
            report($e);

            return redirect()->route('profile.edit')->with('success', 'Email aggiornata, ma non è stato possibile inviare il link di verifica: usa «Reinvia email di verifica» dal banner.');
        }

        return redirect()->route('profile.edit')->with('success', 'Email aggiornata. Ti abbiamo inviato un link per confermare il nuovo indirizzo.');
    }

    /**
     * Password (il cast `hashed` del modello si occupa dell'hash).
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $request->user()->update(['password' => $validated['password']]);

        Log::info('Password utente modificata', ['user' => $request->user()->id]);

        return redirect()->route('profile.edit')->with('success', 'Password aggiornata.');
    }

    /**
     * Nuovo token del feed ICS: il vecchio link smette subito di funzionare.
     */
    public function regenerateIcsToken(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['ics_token' => Str::random(64)])->save();

        return redirect()->route('profile.edit')->with('success', 'Nuovo link del feed ICS generato: il precedente non funziona più, ricorda di aggiornarlo nel tuo calendario.');
    }
}
