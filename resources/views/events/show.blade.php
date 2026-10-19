@extends('layouts.app', ['title' => $event->title])

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Back Navigation -->
    <div>
        <a href="{{ route('calendar.index') }}" 
           class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-400 hover:text-white transition-colors">
            <span>&larr;</span>
            <span>Torna al Calendario</span>
        </a>
    </div>

    <!-- Main Event Details Card -->
    <div class="glass-panel p-6 sm:p-8 rounded-3xl border border-slate-800 shadow-xl">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-800">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    @if($event->type === 'session')
                        <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-indigo-500/10 text-indigo-300 border border-indigo-500/20">
                            🎲 Sessione di Gioco
                        </span>
                        @if($event->group)
                            <span class="px-2.5 py-0.5 text-xs font-medium rounded-full bg-slate-800 text-slate-300 border border-slate-700">
                                👥 {{ $event->group->name }}
                            </span>
                        @endif
                    @elseif($event->type === 'assembly')
                        <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-rose-500/10 text-rose-300 border border-rose-500/20">
                            🏛️ Assemblea Soci
                        </span>
                    @else
                        <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-amber-500/10 text-amber-300 border border-amber-500/20">
                            🎉 Evento Associativo
                        </span>
                    @endif
                </div>

                <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">
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
                        <a href="{{ route('events.edit', $event) }}" 
                           class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-md transition-all">
                            ✏️ Modifica / Riprogramma
                        </a>
                    @endif

                    @if(Auth::user()->hasRole('ADMIN') || Auth::user()->hasRole('CD') || $event->created_by === Auth::id())
                        <form method="POST" action="{{ route('events.destroy', $event) }}" onsubmit="return confirm('Confermi l\'eliminazione definitiva di questo evento?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" 
                                    class="px-3 py-2 rounded-xl bg-rose-950/40 hover:bg-rose-900/60 text-rose-300 border border-rose-800/50 text-xs font-medium transition-colors">
                                🗑️ Elimina
                            </button>
                        </form>
                    @endif
                </div>
            @endauth
        </div>

        <!-- Event Metadata -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 py-6 border-b border-slate-800 text-xs">
            <div class="p-3.5 rounded-xl bg-slate-900/60 border border-slate-800/80">
                <span class="text-slate-400 block mb-1">📅 Data e Orario</span>
                <span class="text-white font-medium">
                    {{ $event->start_time->format('d/m/Y') }}
                </span>
                <div class="text-indigo-400 font-mono mt-0.5">
                    {{ $event->start_time->format('H:i') }} &ndash; {{ $event->end_time->format('H:i') }}
                </div>
            </div>

            <div class="p-3.5 rounded-xl bg-slate-900/60 border border-slate-800/80">
                <span class="text-slate-400 block mb-1">👤 Creato da</span>
                <span class="text-white font-medium">{{ $event->creator->name ?? 'Sistema' }}</span>
                <div class="text-slate-500 text-[11px] mt-0.5">{{ $event->created_at->format('d/m/Y H:i') }}</div>
            </div>

            <div class="p-3.5 rounded-xl bg-slate-900/60 border border-slate-800/80">
                <span class="text-slate-400 block mb-1">⏳ Durata Prevista</span>
                <span class="text-white font-medium">
                    {{ $event->start_time->diffInHours($event->end_time) }} ore 
                    @if($event->start_time->diffInMinutes($event->end_time) % 60 > 0)
                        e {{ $event->start_time->diffInMinutes($event->end_time) % 60 }} min
                    @endif
                </span>
            </div>
        </div>

        <!-- Description -->
        <div class="py-6">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Descrizione</h3>
            @if($event->description)
                <p class="text-sm text-slate-200 leading-relaxed whitespace-pre-line">
                    {{ $event->description }}
                </p>
            @else
                <p class="text-xs text-slate-500 italic">Nessuna descrizione specificata.</p>
            @endif
        </div>
    </div>

    <!-- Storico Modifiche (Event Logs) -->
    <div class="glass-panel p-6 sm:p-8 rounded-3xl border border-slate-800 shadow-xl">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <span class="text-lg">📜</span>
                <h2 class="text-lg font-bold text-white">Storico Modifiche & Log</h2>
            </div>
            <span class="text-xs text-slate-400 font-medium">
                {{ $event->logs->count() }} registrazioni
            </span>
        </div>

        @if($event->logs->isEmpty())
            <div class="py-8 text-center text-xs text-slate-500 italic">
                Nessun log registrato per questo evento.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-800 text-slate-400 uppercase tracking-wider text-[11px]">
                            <th class="py-3 px-3">Data/Ora</th>
                            <th class="py-3 px-3">Utente</th>
                            <th class="py-3 px-3">Azione</th>
                            <th class="py-3 px-3">Dettagli / Variazioni</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @foreach($event->logs as $log)
                            <tr class="hover:bg-slate-900/40 transition-colors">
                                <td class="py-3 px-3 font-mono text-slate-400 whitespace-nowrap">
                                    {{ $log->created_at->format('d/m/Y H:i:s') }}
                                </td>
                                <td class="py-3 px-3 font-medium text-slate-200 whitespace-nowrap">
                                    {{ $log->user->name ?? 'Utente Sconosciuto' }}
                                </td>
                                <td class="py-3 px-3 whitespace-nowrap">
                                    @if($log->action === 'created')
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                            Creata
                                        </span>
                                    @elseif($log->action === 'rescheduled')
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                                            Riprogrammata
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                            Modificata
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 px-3 text-slate-300">
                                    @if($log->action === 'created')
                                        <span class="text-slate-400">Evento creato inizialmente</span>
                                    @else
                                        <div class="space-y-1">
                                            @if(!empty($log->new_data))
                                                @foreach($log->new_data as $field => $newVal)
                                                    @php
                                                        $oldVal = $log->old_data[$field] ?? '-';
                                                    @endphp
                                                    <div class="font-mono text-[11px]">
                                                        <strong class="text-slate-400 font-normal">{{ $field }}:</strong>
                                                        <span class="text-rose-400 line-through mr-1">{{ is_array($oldVal) ? json_encode($oldVal) : $oldVal }}</span>
                                                        <span class="text-emerald-400">&rarr; {{ is_array($newVal) ? json_encode($newVal) : $newVal }}</span>
                                                    </div>
                                                @endforeach
                                            @else
                                                <span class="text-slate-500 italic">Nessun dettaglio extra</span>
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
