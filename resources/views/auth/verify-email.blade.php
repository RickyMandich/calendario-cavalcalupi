@extends('layouts.app', ['title' => 'Verifica email'])

@section('content')
<div class="max-w-md mx-auto my-8">
    <div class="panel p-8 rounded-2xl shadow-xl text-center">
        <div class="w-14 h-14 rounded-2xl mx-auto mb-3 bg-brand-100 flex items-center justify-center text-3xl">✉️</div>
        <h1 class="text-2xl font-bold text-brand-950 tracking-tight">Controlla la tua email</h1>
        <p class="text-sm text-gray-600 mt-3">
            Abbiamo inviato un link di verifica a <strong class="text-brand-900">{{ auth()->user()->email }}</strong>.
            Aprilo per confermare l'indirizzo.
        </p>

        <div class="mt-5 p-3.5 rounded-xl bg-brand-50 border border-brand-100 text-xs text-gray-600 text-left space-y-1">
            <div class="font-semibold text-brand-900">Finché l'email non è verificata:</div>
            <div>• puoi consultare il calendario, ma non puoi essere assegnato a un gruppo;</div>
            <div>• non è disponibile il feed ICS personale;</div>
            <div>• il tuo ruolo resta Membro.</div>
        </div>

        <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
            @csrf
            <button type="submit" class="btn-primary w-full py-2.5 text-sm">Reinvia l'email di verifica</button>
        </form>

        <a href="{{ route('calendar.index') }}" class="inline-block mt-4 text-xs text-brand-700 hover:text-brand-900 font-medium">
            Torna al calendario
        </a>
    </div>
</div>
@endsection
