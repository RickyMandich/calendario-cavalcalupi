@extends('layouts.app', ['title' => 'Registrati'])

@section('content')
<div class="max-w-md mx-auto my-8">
    <div class="panel p-8 rounded-2xl shadow-xl">
        <div class="text-center mb-6">
            <img src="{{ asset('build/icons/android-chrome-192x192.png') }}" alt="" width="56" height="56" class="w-14 h-14 rounded-2xl mx-auto mb-3 shadow-md shadow-brand-600/20">
            <h1 class="text-2xl font-bold text-brand-950 tracking-tight">Crea un Account</h1>
            <p class="text-xs text-gray-500 mt-1">Registrati come Membro dell'associazione Cavalcalupi</p>
        </div>

        <form method="POST" action="{{ route('register.submit') }}" class="space-y-4">
            @csrf

            <div>
                <label for="name" class="field-label">Nome e Cognome</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus
                       class="field"
                       placeholder="Mario Rossi">
            </div>

            <div>
                <label for="email" class="field-label">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required
                       class="field"
                       placeholder="mario@example.com">
            </div>

            <div>
                <label for="password" class="field-label">Password</label>
                <input type="password" id="password" name="password" required
                       class="field"
                       placeholder="Minimo 8 caratteri">
            </div>

            <div>
                <label for="password_confirmation" class="field-label">Conferma Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required
                       class="field"
                       placeholder="Ripeti la password">
            </div>

            <button type="submit" class="btn-primary w-full py-2.5 text-sm">
                Registrati & Ottieni Link Calendario
            </button>
        </form>

        <div class="mt-6 text-center text-xs text-gray-500">
            Hai già un account?
            <a href="{{ route('login') }}" class="text-brand-700 hover:text-brand-900 font-medium ml-1">Accedi qui</a>
        </div>
    </div>
</div>
@endsection
