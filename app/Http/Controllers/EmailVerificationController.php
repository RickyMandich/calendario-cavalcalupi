<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Verifica dell'indirizzo email. Le rotte stanno nel gruppo `auth` ma NON sotto il middleware `verified`:
 * un utente non verificato può accedere e consultare il calendario, ma non può far parte di gruppi,
 * avere ruoli diversi da Membro né usare il feed ICS (vedi README, sezione "Verifica email").
 */
class EmailVerificationController extends Controller
{
    /**
     * Pagina "controlla la tua email" con il pulsante di reinvio.
     */
    public function notice(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('calendar.index');
        }

        return view('auth.verify-email');
    }

    /**
     * Link firmato ricevuto per email.
     */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->route('calendar.index')
            ->with('success', 'Email verificata! Ora puoi essere assegnato ai gruppi e usare il feed ICS.');
    }

    /**
     * Reinvia il link di verifica.
     */
    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('calendar.index');
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            // SMTP non configurato/raggiungibile: non si mostra un 500, si avvisa e si registra l'errore.
            report($e);

            return back()->withErrors(['email' => 'Non è stato possibile inviare l\'email in questo momento. Riprova più tardi.']);
        }

        return back()->with('success', 'Ti abbiamo inviato un nuovo link di verifica.');
    }
}
