@extends('layouts.app', ['title' => 'Accedi'])

@section('content')
<div class="max-w-md mx-auto my-8">
    <div class="glass-panel p-8 rounded-2xl shadow-2xl border border-slate-800">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-white tracking-tight">Accesso al Portale</h1>
            <p class="text-xs text-slate-400 mt-1">Inserisci le tue credenziali per gestire o consultare il calendario</p>
        </div>

        <form method="POST" action="{{ route('login.submit') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                       class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all text-sm"
                       placeholder="nome@cavalcalupi.it">
            </div>

            <div>
                <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Password</label>
                <input type="password" id="password" name="password" required
                       class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all text-sm"
                       placeholder="••••••••">
            </div>

            <div class="flex items-center justify-between text-xs">
                <label class="flex items-center gap-2 cursor-pointer text-slate-300">
                    <input type="checkbox" name="remember" class="rounded bg-slate-800 border-slate-700 text-indigo-600 focus:ring-0">
                    <span>Ricordami</span>
                </label>
            </div>

            <button type="submit"
                    class="w-full py-2.5 px-4 rounded-xl font-medium bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white shadow-lg shadow-indigo-600/30 transition-all text-sm">
                Entra nel Portale
            </button>
        </form>

        <!-- Quick Demo Logins -->
        <div class="mt-8 pt-6 border-t border-slate-800 text-center">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-3">Accesso Rapido Demo</p>
            <div class="grid grid-cols-3 gap-2">
                <a href="{{ route('quick.login', 'admin') }}" 
                   class="px-2 py-2 rounded-lg bg-red-950/40 border border-red-800/40 text-red-300 hover:bg-red-900/50 text-xs font-medium transition-colors">
                   🔑 Admin
                </a>
                <a href="{{ route('quick.login', 'cd') }}" 
                   class="px-2 py-2 rounded-lg bg-amber-950/40 border border-amber-800/40 text-amber-300 hover:bg-amber-900/50 text-xs font-medium transition-colors">
                   🛡️ CD
                </a>
                <a href="{{ route('quick.login', 'membro') }}" 
                   class="px-2 py-2 rounded-lg bg-emerald-950/40 border border-emerald-800/40 text-emerald-300 hover:bg-emerald-900/50 text-xs font-medium transition-colors">
                   🎲 Membro
                </a>
            </div>
            <p class="text-[11px] text-slate-500 mt-2">Password predefinita di tutti gli account demo: <code class="text-slate-300">password</code></p>
        </div>
    </div>
</div>
@endsection
