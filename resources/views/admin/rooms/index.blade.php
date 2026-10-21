@extends('layouts.app', ['title' => 'Stanze e Capienza'])

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-brand-100 border border-brand-200 text-brand-800 text-xs font-semibold mb-2">
                <span>🛡️ Sezione CD / Amministrazione</span>
            </div>
            <h1 class="text-3xl font-extrabold text-brand-950 tracking-tight">Stanze e Capienza</h1>
            <p class="text-xs text-gray-500 mt-1">
                Ogni stanza attiva offre tanti posti quanti ne indica la capienza nei giorni selezionati. Un giorno ha tante sessioni possibili quanti sono i posti totali. Le stanze non vengono assegnate alle sessioni.
            </p>
        </div>
        <button type="button" onclick="document.getElementById('newRoomModal').classList.remove('hidden')" class="btn-primary self-start sm:self-auto">
            <span>➕</span><span>Nuova Stanza</span>
        </button>
    </div>

    <!-- Riepilogo capienza per giorno -->
    <div class="panel p-4 rounded-2xl">
        <span class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">Capienza per giorno della settimana (stanze attive)</span>
        <div class="grid grid-cols-7 gap-2 text-center">
            @foreach($weekdays as $num => $label)
                <div class="p-2 rounded-xl bg-brand-50 border border-brand-100">
                    <div class="text-[11px] text-gray-500">{{ $label }}</div>
                    <div class="text-lg font-bold {{ $capacity[$num] > 0 ? 'text-brand-800' : 'text-gray-400' }}">{{ $capacity[$num] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    @if($rooms->isEmpty())
        <div class="panel p-6 rounded-2xl text-center text-xs text-amber-700">
            Nessuna stanza configurata: finché non ne crei almeno una, le sessioni non possono essere programmate.
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach($rooms as $room)
            @php $days = $room->weekdays->pluck('weekday')->all(); @endphp
            <form method="POST" action="{{ route('admin.rooms.update', $room) }}" class="panel p-5 rounded-3xl space-y-3 {{ $room->is_active ? '' : 'opacity-70' }}">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <label class="field-label">Nome</label>
                        <input type="text" name="name" value="{{ $room->name }}" required class="field">
                    </div>
                    <div>
                        <label class="field-label">Capienza</label>
                        <input type="number" name="capacity" min="1" max="20" value="{{ $room->capacity }}" required class="field">
                    </div>
                </div>
                <div>
                    <span class="field-label">Giorni utilizzabili</span>
                    <div class="flex flex-wrap gap-2">
                        @foreach($weekdays as $num => $label)
                            <label class="inline-flex items-center gap-1 text-xs">
                                <input type="checkbox" name="weekdays[]" value="{{ $num }}" {{ in_array($num, $days) ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="flex items-center justify-between pt-2 border-t border-brand-100">
                    <label class="inline-flex items-center gap-2 text-xs">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" {{ $room->is_active ? 'checked' : '' }}
                               class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                        Attiva
                    </label>
                    <div class="flex items-center gap-2">
                        <button type="submit" class="btn-primary">Salva</button>
                        <button type="submit" form="delete-room-{{ $room->id }}" class="btn-secondary"
                                onclick="return confirm('Eliminare la stanza? La capienza dei suoi giorni diminuirà (per interventi temporanei usa Attiva).')">Elimina</button>
                    </div>
                </div>
            </form>
            <form id="delete-room-{{ $room->id }}" method="POST" action="{{ route('admin.rooms.destroy', $room) }}" class="hidden">
                @csrf
                @method('DELETE')
            </form>
        @endforeach
    </div>
</div>

<!-- Modale nuova stanza -->
<div id="newRoomModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 modal-overlay backdrop-blur-sm hidden">
    <div class="panel max-w-md w-full p-6 sm:p-8 rounded-3xl shadow-2xl relative">
        <button type="button" onclick="document.getElementById('newRoomModal').classList.add('hidden')" class="absolute top-5 right-5 text-gray-400 hover:text-brand-800 text-xl">&times;</button>
        <h3 class="text-xl font-bold text-brand-950 mb-4">Nuova Stanza</h3>
        <form method="POST" action="{{ route('admin.rooms.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="field-label" for="room-name">Nome</label>
                <input id="room-name" type="text" name="name" required class="field">
            </div>
            <div>
                <label class="field-label" for="room-capacity">Capienza (gruppi contemporanei)</label>
                <input id="room-capacity" type="number" name="capacity" min="1" max="20" value="1" required class="field">
            </div>
            <div>
                <span class="field-label">Giorni utilizzabili</span>
                <div class="flex flex-wrap gap-2">
                    @foreach($weekdays as $num => $label)
                        <label class="inline-flex items-center gap-1 text-xs">
                            <input type="checkbox" name="weekdays[]" value="{{ $num }}" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
            <input type="hidden" name="is_active" value="1">
            <div class="pt-4 flex justify-end gap-2 border-t border-brand-100">
                <button type="button" onclick="document.getElementById('newRoomModal').classList.add('hidden')" class="btn-secondary">Annulla</button>
                <button type="submit" class="btn-primary px-5">Salva Stanza</button>
            </div>
        </form>
    </div>
</div>
@endsection
