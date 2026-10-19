@extends('layouts.app', ['title' => 'Nuovo Evento o Sessione'])

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <!-- Back Navigation -->
    <div>
        <a href="{{ route('calendar.index') }}"
           class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 hover:text-brand-800 transition-colors">
            <span>&larr;</span>
            <span>Torna al Calendario</span>
        </a>
    </div>

    <div class="panel p-6 sm:p-8 rounded-3xl shadow-xl">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-brand-950 tracking-tight">Pianifica Nuova Attività</h1>
            <p class="text-xs text-gray-500 mt-1">Crea una nuova sessione di gioco, un'assemblea o un evento associativo</p>
        </div>

        <form method="POST" action="{{ route('events.store') }}" class="space-y-5">
            @csrf

            <!-- Tipologia Evento -->
            <div>
                <label for="type" class="field-label">Tipologia</label>
                <select id="type" name="type" onchange="toggleGroupSelection(this.value)" class="field">
                    <option value="session" {{ old('type', $defaultType) === 'session' ? 'selected' : '' }}>🎲 Sessione di Gioco (richiede Gruppo)</option>
                    @if($isStaff)
                        <option value="assembly" {{ old('type', $defaultType) === 'assembly' ? 'selected' : '' }}>🏛️ Assemblea Soci</option>
                        <option value="association_event" {{ old('type', $defaultType) === 'association_event' ? 'selected' : '' }}>🎉 Evento Associativo</option>
                    @endif
                </select>
            </div>

            <!-- Titolo -->
            <div>
                <label for="title" class="field-label">Titolo Evento / Sessione</label>
                <input type="text" id="title" name="title" value="{{ old('title') }}" required
                       class="field"
                       placeholder="Es: D&D 5e - Capitolo 3 oppure Assemblea Ordinaria">
            </div>

            <!-- Selezione Gruppo (condizionale su type == 'session') -->
            <div id="groupContainer">
                <label for="group_id" class="field-label">Tavolo / Gruppo di Gioco (solo Gruppi Attivi)</label>
                @if($groups->isEmpty())
                    <p class="text-xs text-amber-700 p-3 rounded-xl bg-amber-50 border border-amber-200">
                        ⚠️ Non risulti membro di alcun gruppo attivo. Chiedi a un responsabile CD o Admin di assegnarti al tuo tavolo.
                    </p>
                @else
                    <select id="group_id" name="group_id" class="field">
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
                    <label for="start_time" class="field-label">Data & Ora Inizio</label>
                    <input type="datetime-local" id="start_time" name="start_time"
                           value="{{ old('start_time', now()->addDay()->setTime(21, 0)->format('Y-m-d\TH:i')) }}" required
                           class="field">
                </div>

                <div>
                    <label for="end_time" class="field-label">Data & Ora Fine</label>
                    <input type="datetime-local" id="end_time" name="end_time"
                           value="{{ old('end_time', now()->addDay()->setTime(23, 30)->format('Y-m-d\TH:i')) }}" required
                           class="field">
                </div>
            </div>

            <!-- Descrizione -->
            <div>
                <label for="description" class="field-label">Descrizione / Note (opzionale)</label>
                <textarea id="description" name="description" rows="4"
                          class="field"
                          placeholder="Note logistiche, punto di ritrovo, materiali necessari...">{{ old('description') }}</textarea>
            </div>

            <!-- Submit -->
            <div class="pt-4 flex items-center justify-end gap-3 border-t border-brand-100">
                <a href="{{ route('calendar.index') }}" class="btn-secondary">
                    Annulla
                </a>
                <button type="submit" class="btn-primary px-5">
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
