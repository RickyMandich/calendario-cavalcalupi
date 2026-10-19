@extends('layouts.app', ['title' => 'Modifica: ' . $event->title])

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <!-- Back Navigation -->
    <div>
        <a href="{{ route('events.show', $event) }}" 
           class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-400 hover:text-white transition-colors">
            <span>&larr;</span>
            <span>Torna ai Dettagli</span>
        </a>
    </div>

    <div class="glass-panel p-6 sm:p-8 rounded-3xl border border-slate-800 shadow-xl">
        <div class="mb-6">
            <div class="flex items-center gap-2 mb-2">
                <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-indigo-500/10 text-indigo-300 border border-indigo-500/20">
                    Modifica / Riprogrammazione
                </span>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Aggiorna Evento</h1>
            <p class="text-xs text-slate-400 mt-1">
                Ogni modifica ad orari, date o testo verrà salvata automaticamente nello <strong class="text-white">Storico Modifiche</strong>.
            </p>
        </div>

        <form method="POST" action="{{ route('events.update', $event) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <!-- Titolo -->
            <div>
                <label for="title" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Titolo</label>
                <input type="text" id="title" name="title" value="{{ old('title', $event->title) }}" required
                       class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-100 placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            @if($event->type === 'session' && $isStaff)
                <div>
                    <label for="group_id" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Gruppo / Tavolo</label>
                    <select id="group_id" name="group_id"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @foreach($groups as $group)
                            <option value="{{ $group->id }}" {{ old('group_id', $event->group_id) == $group->id ? 'selected' : '' }}>
                                {{ $group->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <!-- Date e Orari -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="start_time" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Data & Ora Inizio</label>
                    <input type="datetime-local" id="start_time" name="start_time" 
                           value="{{ old('start_time', $event->start_time->format('Y-m-d\TH:i')) }}" required
                           class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label for="end_time" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Data & Ora Fine</label>
                    <input type="datetime-local" id="end_time" name="end_time" 
                           value="{{ old('end_time', $event->end_time->format('Y-m-d\TH:i')) }}" required
                           class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>

            <!-- Descrizione -->
            <div>
                <label for="description" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Descrizione</label>
                <textarea id="description" name="description" rows="4"
                          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-100 placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">{{ old('description', $event->description) }}</textarea>
            </div>

            <!-- Submit -->
            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-800">
                <a href="{{ route('events.show', $event) }}" 
                   class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium border border-slate-700 transition-colors">
                    Annulla
                </a>
                <button type="submit"
                        class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/30 transition-all">
                    Aggiorna & Registra Log
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
