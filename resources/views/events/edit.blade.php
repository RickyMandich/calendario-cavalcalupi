@extends('layouts.app', ['title' => 'Modifica: ' . $event->title])

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <!-- Back Navigation -->
    <div>
        <a href="{{ route('events.show', $event) }}"
           class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 hover:text-brand-800 transition-colors">
            <span>&larr;</span>
            <span>Torna ai Dettagli</span>
        </a>
    </div>

    <div class="panel p-6 sm:p-8 rounded-3xl shadow-xl">
        <div class="mb-6">
            <div class="flex items-center gap-2 mb-2">
                <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-brand-50 text-brand-800 border border-brand-200">
                    Modifica / Riprogrammazione
                </span>
            </div>
            <h1 class="text-2xl font-bold text-brand-950 tracking-tight">Aggiorna Evento</h1>
            <p class="text-xs text-gray-500 mt-1">
                Ogni modifica ad orari, date o testo verrà salvata automaticamente nello <strong class="text-brand-950">Storico Modifiche</strong>.
            </p>
        </div>

        <form method="POST" action="{{ route('events.update', $event) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <!-- Titolo -->
            <div>
                <label for="title" class="field-label">Titolo</label>
                <input type="text" id="title" name="title" value="{{ old('title', $event->title) }}" required class="field">
            </div>

            @if($event->type === 'session' && $isStaff)
                <div>
                    <label for="group_id" class="field-label">Gruppo / Tavolo</label>
                    <select id="group_id" name="group_id" class="field">
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
                    <label for="start_time" class="field-label">Data & Ora Inizio</label>
                    <input type="datetime-local" id="start_time" name="start_time"
                           value="{{ old('start_time', $event->start_time->format('Y-m-d\TH:i')) }}" required
                           class="field">
                </div>

                <div>
                    <label for="end_time" class="field-label">Data & Ora Fine</label>
                    <input type="datetime-local" id="end_time" name="end_time"
                           value="{{ old('end_time', $event->end_time->format('Y-m-d\TH:i')) }}" required
                           class="field">
                </div>
            </div>

            <div id="availabilityBox" data-url="{{ route('calendar.availability') }}" class="text-xs p-3 rounded-xl bg-brand-50 border border-brand-100 text-gray-600 hidden"></div>

            <!-- Descrizione -->
            <div>
                <label for="description" class="field-label">Descrizione</label>
                <textarea id="description" name="description" rows="4" class="field">{{ old('description', $event->description) }}</textarea>
            </div>

            <!-- Submit -->
            <div class="pt-4 flex items-center justify-end gap-3 border-t border-brand-100">
                <a href="{{ route('events.show', $event) }}" class="btn-secondary">
                    Annulla
                </a>
                <button type="submit" class="btn-primary px-5">
                    Aggiorna & Registra Log
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
    @include('events._availability')
@endpush
@endsection
