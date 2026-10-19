@extends('layouts.app', ['title' => 'Nuovo Evento o Sessione'])

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <!-- Back Navigation -->
    <div>
        <a href="{{ route('calendar.index') }}" 
           class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-400 hover:text-white transition-colors">
            <span>&larr;</span>
            <span>Torna al Calendario</span>
        </a>
    </div>

    <div class="glass-panel p-6 sm:p-8 rounded-3xl border border-slate-800 shadow-xl">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-white tracking-tight">Pianifica Nuova Attività</h1>
            <p class="text-xs text-slate-400 mt-1">Crea una nuova sessione di gioco, un'assemblea o un evento associativo</p>
        </div>

        <form method="POST" action="{{ route('events.store') }}" class="space-y-5">
            @csrf

            <!-- Tipologia Evento -->
            <div>
                <label for="type" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Tipologia</label>
                <select id="type" name="type" onchange="toggleGroupSelection(this.value)"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="session" {{ old('type', $defaultType) === 'session' ? 'selected' : '' }}>🎲 Sessione di Gioco (richiede Gruppo)</option>
                    @if($isStaff)
                        <option value="assembly" {{ old('type', $defaultType) === 'assembly' ? 'selected' : '' }}>🏛️ Assemblea Soci</option>
                        <option value="association_event" {{ old('type', $defaultType) === 'association_event' ? 'selected' : '' }}>🎉 Evento Associativo</option>
                    @endif
                </select>
            </div>

            <!-- Titolo -->
            <div>
                <label for="title" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Titolo Evento / Sessione</label>
                <input type="text" id="title" name="title" value="{{ old('title') }}" required
                       class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-100 placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                       placeholder="Es: D&D 5e - Capitolo 3 oppure Assemblea Ordinaria">
            </div>

            <!-- Selezione Gruppo (condizionale su type == 'session') -->
            <div id="groupContainer">
                <label for="group_id" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Tavolo / Gruppo di Gioco (solo Gruppi Attivi)</label>
                @if($groups->isEmpty())
                    <p class="text-xs text-amber-400 p-3 rounded-xl bg-amber-500/10 border border-amber-500/20">
                        ⚠️ Non risulti membro di alcun gruppo attivo. Chiedi a un responsabile CD o Admin di assegnarti al tuo tavolo.
                    </p>
                @else
                    <select id="group_id" name="group_id"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">-- Seleziona il gruppo --</option>
                        @foreach($groups as $group)
                            <option value="{{ $group->id }}" {{ old('group_id') == $group->id ? 'selected' : '' }}>
                                {{ $group->name }}
                            </option>
                        @endforeach
                    </select>
                @endif
            </div>

            <!-- Date e Orari -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="start_time" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Data & Ora Inizio</label>
                    <input type="datetime-local" id="start_time" name="start_time" 
                           value="{{ old('start_time', now()->addDay()->setTime(21, 0)->format('Y-m-d\TH:i')) }}" required
                           class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label for="end_time" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Data & Ora Fine</label>
                    <input type="datetime-local" id="end_time" name="end_time" 
                           value="{{ old('end_time', now()->addDay()->setTime(23, 30)->format('Y-m-d\TH:i')) }}" required
                           class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>

            <!-- Descrizione -->
            <div>
                <label for="description" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Descrizione / Note (opzionale)</label>
                <textarea id="description" name="description" rows="4"
                          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-100 placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                          placeholder="Note logistiche, punto di ritrovo, materiali necessari...">{{ old('description') }}</textarea>
            </div>

            <!-- Submit -->
            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-800">
                <a href="{{ route('calendar.index') }}" 
                   class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium border border-slate-700 transition-colors">
                    Annulla
                </a>
                <button type="submit"
                        class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/30 transition-all">
                    Salva e Notifica Calendario
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function toggleGroupSelection(type) {
        const groupContainer = document.getElementById('groupContainer');
        const groupSelect = document.getElementById('group_id');
        if (type === 'session') {
            groupContainer.style.display = 'block';
            if (groupSelect) groupSelect.required = true;
        } else {
            groupContainer.style.display = 'none';
            if (groupSelect) groupSelect.required = false;
        }
    }
    document.addEventListener('DOMContentLoaded', () => {
        const typeSelect = document.getElementById('type');
        if (typeSelect) toggleGroupSelection(typeSelect.value);
    });
</script>
@endpush
@endsection
