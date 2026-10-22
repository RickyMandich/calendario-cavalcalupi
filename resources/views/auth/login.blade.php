@extends('layouts.app', ['title' => 'Accedi'])

@section('content')
<div class="max-w-md mx-auto my-8">
    <div class="panel p-8 rounded-2xl shadow-xl">
        <div class="text-center mb-6">
            <img src="{{ asset('build/icons/android-chrome-192x192.png') }}" alt="" width="56" height="56" class="w-14 h-14 rounded-2xl mx-auto mb-3 shadow-md shadow-brand-600/20">
            <h1 class="text-2xl font-bold text-brand-950 tracking-tight">Accesso al Portale</h1>
            <p class="text-xs text-gray-500 mt-1">Inserisci le tue credenziali per gestire o consultare il calendario</p>
        </div>

        <form method="POST" action="{{ route('login.submit') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="field-label">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                       class="field"
                       placeholder="nome@cavalcalupi.it">
            </div>

            <div>
                <label for="password" class="field-label">Password</label>
                <input type="password" id="password" name="password" required
                       class="field"
                       placeholder="••••••••">
            </div>

            <div class="flex items-center justify-between text-xs">
                <label class="flex items-center gap-2 cursor-pointer text-gray-700">
                    <input type="checkbox" name="remember" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                    <span>Ricordami</span>
                </label>
            </div>

            <button type="submit" class="btn-primary w-full py-2.5 text-sm">
                Entra nel Portale
            </button>
        </form>

        @if(app()->environment('local'))
        <!-- Quick Demo Logins (solo ambiente locale) -->
        <div class="mt-8 pt-6 border-t border-brand-100 text-center">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-3">Accesso Rapido Demo</p>
            <div class="grid grid-cols-3 gap-2">
                <a href="{{ route('quick.login', 'admin') }}"
                   class="px-2 py-2 rounded-lg bg-red-50 border border-red-200 text-red-700 hover:bg-red-100 text-xs font-medium transition-colors">
                   🔑 Admin
                </a>
                <a href="{{ route('quick.login', 'cd') }}"
                   class="px-2 py-2 rounded-lg bg-amber-50 border border-amber-200 text-amber-700 hover:bg-amber-100 text-xs font-medium transition-colors">
                   🛡️ CD
                </a>
                <a href="{{ route('quick.login', 'membro') }}"
                   class="px-2 py-2 rounded-lg bg-brand-50 border border-brand-200 text-brand-700 hover:bg-brand-100 text-xs font-medium transition-colors">
                   🎲 Membro
                </a>
            </div>
            <p class="text-[11px] text-gray-500 mt-2">Password predefinita di tutti gli account demo: <code class="text-brand-800">password</code></p>
        </div>
        @endif
    </div>
</div>
@endsection
