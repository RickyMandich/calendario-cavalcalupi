@extends('layouts.app', ['title' => 'Il mio profilo'])

@section('content')
@php
    $profileErrors = $errors->getBag('profile');
    $emailErrors = $errors->getBag('email');
    $passwordErrors = $errors->getBag('password');
@endphp
<div class="max-w-2xl mx-auto space-y-6">
    <div>
        <h1 class="text-3xl font-extrabold text-brand-950 tracking-tight">Il mio profilo</h1>
        <p class="text-xs text-gray-500 mt-1">Gestisci i tuoi dati di accesso e il link personale del calendario.</p>
    </div>

    <!-- Nome -->
    <div class="panel p-5 sm:p-6">
        <h2 class="text-lg font-semibold text-brand-950 mb-4">Nome e cognome</h2>
        <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label for="name" class="field-label">Nome reale</label>
                <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="255" class="field">
                @if($profileErrors->has('name'))
                    <p class="text-xs text-rose-600 mt-1">{{ $profileErrors->first('name') }}</p>
                @endif
            </div>
            <button type="submit" class="btn-primary">Salva nome</button>
        </form>
    </div>

    <!-- Email -->
    <div class="panel p-5 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
            <h2 class="text-lg font-semibold text-brand-950">Email</h2>
            @if($user->hasVerifiedEmail())
                <span class="badge bg-brand-100 text-brand-800 border-brand-300">Verificata</span>
            @else
                <span class="badge bg-amber-100 text-amber-800 border-amber-300">Non verificata</span>
            @endif
        </div>
        <form method="POST" action="{{ route('profile.email') }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label for="email" class="field-label">Indirizzo email</label>
                <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="255" class="field">
                @if($emailErrors->has('email'))
                    <p class="text-xs text-rose-600 mt-1">{{ $emailErrors->first('email') }}</p>
                @endif
            </div>
            <div>
                <label for="email_current_password" class="field-label">Password attuale</label>
                <input type="password" id="email_current_password" name="current_password" required autocomplete="current-password" class="field">
                @if($emailErrors->has('current_password'))
                    <p class="text-xs text-rose-600 mt-1">{{ $emailErrors->first('current_password') }}</p>
                @endif
            </div>
            <p class="text-xs text-gray-500">
                Se cambi indirizzo ti inviamo un link per confermarlo: finché non lo apri il feed ICS personale non è disponibile.
                Ruoli e gruppi restano quelli che hai.
            </p>
            <button type="submit" class="btn-primary">Salva email</button>
        </form>
    </div>

    <!-- Password -->
    <div class="panel p-5 sm:p-6">
        <h2 class="text-lg font-semibold text-brand-950 mb-4">Password</h2>
        <form method="POST" action="{{ route('profile.password') }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label for="password_current_password" class="field-label">Password attuale</label>
                <input type="password" id="password_current_password" name="current_password" required autocomplete="current-password" class="field">
                @if($passwordErrors->has('current_password'))
                    <p class="text-xs text-rose-600 mt-1">{{ $passwordErrors->first('current_password') }}</p>
                @endif
            </div>
            <div>
                <label for="password" class="field-label">Nuova password</label>
                <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password" class="field" placeholder="Minimo 8 caratteri">
                @if($passwordErrors->has('password'))
                    <p class="text-xs text-rose-600 mt-1">{{ $passwordErrors->first('password') }}</p>
                @endif
            </div>
            <div>
                <label for="password_confirmation" class="field-label">Conferma nuova password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" class="field">
            </div>
            <button type="submit" class="btn-primary">Salva password</button>
        </form>
    </div>

    <!-- Feed ICS -->
    <div class="panel p-5 sm:p-6">
        <h2 class="text-lg font-semibold text-brand-950 mb-1">Feed ICS personale</h2>
        @if($user->hasVerifiedEmail())
            <p class="text-xs text-gray-500 mb-4">
                Il link con cui il tuo calendario (Google, Apple, Outlook...) si sincronizza con le tue sessioni.
                Chi lo conosce vede le tue sessioni: se pensi sia stato condiviso per errore, generane uno nuovo.
            </p>
            <label for="ics_url" class="field-label">Il tuo URL ICS</label>
            <input type="text" id="ics_url" readonly value="{{ route('calendar.ics', ['token' => $user->ics_token]) }}"
                   class="field text-xs font-mono text-brand-800 bg-brand-50 mb-4">
            <form method="POST" action="{{ route('profile.ics-token') }}"
                  onsubmit="return confirm('Generare un nuovo link? Il link attuale smetterà di funzionare e dovrai aggiornarlo in tutti i calendari in cui lo hai aggiunto.')">
                @csrf
                <button type="submit" class="btn-secondary">🔄 Genera un nuovo link</button>
            </form>
        @else
            <p class="text-xs text-gray-500">
                Il feed ICS è disponibile solo con l'email verificata.
                <a href="{{ route('verification.notice') }}" class="text-brand-700 underline">Verifica l'email</a> per attivarlo.
            </p>
        @endif
    </div>
</div>
@endsection
