@extends('layouts.app', ['title' => $event->title])

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Back Navigation -->
    <div>
        <a href="{{ route('calendar.index') }}"
           class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 hover:text-brand-800 transition-colors">
            <span>&larr;</span>
            <span>Torna al Calendario</span>
        </a>
    </div>

    <!-- Main Event Details Card -->
    <div class="panel p-6 sm:p-8 rounded-3xl shadow-xl">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-brand-100">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    @if($event->type === 'session')
                        <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-brand-50 text-brand-800 border border-brand-200">
                            🎲 Sessione di Gioco
                        </span>
                        @if($event->group)
                            <span class="px-2.5 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-700 border border-gray-200">
                                👥 {{ $event->group->name }}
                            </span>
                        @endif
                    @elseif($event->type === 'assembly')
                        <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-rose-50 text-rose-700 border border-rose-200">
                            🏛️ Assemblea Soci
                        </span>
                    @else
                        <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-amber-50 text-amber-700 border border-amber-200">
                            🎉 Evento Associativo
                        </span>
                    @endif
                </div>

                <h1 class="text-2xl sm:text-3xl font-extrabold text-brand-950 tracking-tight">
                    {{ $event->title }}
                </h1>
            </div>

            <!-- Action buttons -->
            @auth
                @php
                    $canEdit = false;
                    if ($event->type === 'session') {
                        $canEdit = Auth::user()->can('update-session', $event);
                    } else {
                        $canEdit = Auth::user()->can('manage-assemblies');
                    }
                @endphp

                <div class="flex items-center gap-2">
                    @if($canEdit)
                        <a href="{{ route('events.edit', $event) }}" class="btn-primary">
                            ✏️ Modifica / Riprogramma
                        </a>
                    @endif

                    @if(Auth::user()->hasRole('ADMIN') || Auth::user()->hasRole('CD') || $event->created_by === Auth::id())
                        <form method="POST" action="{{ route('events.destroy', $event) }}" onsubmit="return confirm('Confermi l\'eliminazione definitiva di questo evento?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="px-3 py-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-medium transition-colors">
                                🗑️ Elimina
                            </button>
                        </form>
                    @endif
                </div>
            @endauth
        </div>

        <!-- Event Metadata -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 py-6 border-b border-brand-100 text-xs">
            <div class="p-3.5 rounded-xl bg-brand-50 border border-brand-100">
                <span class="text-gray-500 block mb-1">📅 Data e Orario</span>
                <span class="text-brand-950 font-medium">
                    {{ $event->start_time->format('d/m/Y') }}
                </span>
                <div class="text-brand-700 font-mono mt-0.5">
                    {{ $event->start_time->format('H:i') }} &ndash; {{ $event->end_time->format('H:i') }}
                </div>
            </div>

            <div class="p-3.5 rounded-xl bg-brand-50 border border-brand-100">
                <span class="text-gray-500 block mb-1">👤 Creato da</span>
                <span class="text-brand-950 font-medium">{{ $event->creator->name ?? 'Sistema' }}</span>
                <div class="text-gray-400 text-[11px] mt-0.5">{{ $event->created_at->format('d/m/Y H:i') }}</div>
            </div>

            <div class="p-3.5 rounded-xl bg-brand-50 border border-brand-100">
                <span class="text-gray-500 block mb-1">⏳ Durata Prevista</span>
                <span class="text-brand-950 font-medium">
                    {{ $event->start_time->diffInHours($event->end_time) }} ore
                    @if($event->start_time->diffInMinutes($event->end_time) % 60 > 0)
                        e {{ $event->start_time->diffInMinutes($event->end_time) % 60 }} min
                    @endif
                </span>
            </div>
        </div>

        <!-- Description -->
        <div class="py-6">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">Descrizione</h3>
            @if($event->description)
                <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line">
                    {{ $event->description }}
                </p>
            @else
                <p class="text-xs text-gray-400 italic">Nessuna descrizione specificata.</p>
            @endif
        </div>
    </div>

    <!-- Storico Modifiche (Event Logs) -->
    <div class="panel p-6 sm:p-8 rounded-3xl shadow-xl">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <span class="text-lg">📜</span>
                <h2 class="text-lg font-bold text-brand-950">Storico Modifiche & Log</h2>
            </div>
            <span class="text-xs text-gray-500 font-medium">
                {{ $event->logs->count() }} registrazioni
            </span>
        </div>

        @if($event->logs->isEmpty())
            <div class="py-8 text-center text-xs text-gray-400 italic">
                Nessun log registrato per questo evento.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-brand-100 text-gray-500 uppercase tracking-wider text-[11px]">
                            <th class="py-3 px-3">Data/Ora</th>
                            <th class="py-3 px-3">Utente</th>
                            <th class="py-3 px-3">Azione</th>
                            <th class="py-3 px-3">Dettagli / Variazioni</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-100">
                        @foreach($event->logs as $log)
                            <tr class="hover:bg-brand-50 transition-colors">
                                <td class="py-3 px-3 font-mono text-gray-500 whitespace-nowrap">
                                    {{ $log->created_at->format('d/m/Y H:i:s') }}
                                </td>
                                <td class="py-3 px-3 font-medium text-gray-800 whitespace-nowrap">
                                    {{ $log->user->name ?? 'Utente Sconosciuto' }}
                                </td>
                                <td class="py-3 px-3 whitespace-nowrap">
                                    @if($log->action === 'created')
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-brand-50 text-brand-700 border border-brand-200">
                                            Creata
                                        </span>
                                    @elseif($log->action === 'rescheduled')
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-sky-50 text-sky-700 border border-sky-200">
                                            Riprogrammata
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                            Modificata
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 px-3 text-gray-700">
                                    @if($log->action === 'created')
                                        <span class="text-gray-500">Evento creato inizialmente</span>
                                    @else
                                        <div class="space-y-1">
                                            @if(!empty($log->new_data))
                                                @foreach($log->new_data as $field => $newVal)
                                                    @php
                                                        $oldVal = $log->old_data[$field] ?? '-';
                                                    @endphp
                                                    <div class="font-mono text-[11px]">
                                                        <strong class="text-gray-500 font-normal">{{ $field }}:</strong>
                                                        <span class="text-rose-600 line-through mr-1">{{ is_array($oldVal) ? json_encode($oldVal) : $oldVal }}</span>
                                                        <span class="text-brand-700">&rarr; {{ is_array($newVal) ? json_encode($newVal) : $newVal }}</span>
                                                    </div>
                                                @endforeach
                                            @else
                                                <span class="text-gray-400 italic">Nessun dettaglio extra</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
