@extends('layouts.app', ['title' => 'Registrati'])

@section('content')
<div class="max-w-md mx-auto my-8">
    <div class="glass-panel p-8 rounded-2xl shadow-2xl border border-slate-800">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-white tracking-tight">Crea un Account</h1>
            <p class="text-xs text-slate-400 mt-1">Registrati come Membro dell'associazione Cavalcalupi</p>
        </div>

        <form method="POST" action="{{ route('register.submit') }}" class="space-y-4">
            @csrf

            <div>
                <label for="name" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Nome e Cognome</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus
                       class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all text-sm"
                       placeholder="Mario Rossi">
            </div>

            <div>
                <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required
                       class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all text-sm"
                       placeholder="mario@example.com">
            </div>

            <div>
                <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Password</label>
                <input type="password" id="password" name="password" required
                       class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all text-sm"
                       placeholder="Minimo 8 caratteri">
            </div>

            <div>
                <label for="password_confirmation" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Conferma Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required
                       class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all text-sm"
                       placeholder="Ripeti la password">
            </div>

            <button type="submit"
                    class="w-full py-2.5 px-4 rounded-xl font-medium bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white shadow-lg shadow-indigo-600/30 transition-all text-sm">
                Registrati & Ottieni Link Calendario
            </button>
        </form>

        <div class="mt-6 text-center text-xs text-slate-400">
            Hai già un account?
            <a href="{{ route('login') }}" class="text-indigo-400 hover:text-indigo-300 font-medium ml-1">Accedi qui</a>
        </div>
    </div>
</div>
@endsection
