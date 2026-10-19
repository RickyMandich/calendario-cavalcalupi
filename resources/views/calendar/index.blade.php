@extends('layouts.app', ['title' => 'Calendario Eventi & Sessioni'])

@section('content')
<div class="space-y-6">
    <!-- Header Banner -->
    <div class="glass-panel p-6 sm:p-8 rounded-3xl relative overflow-hidden">
        <div class="absolute -right-10 -bottom-10 w-72 h-72 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 relative z-10">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-300 text-xs font-semibold mb-3">
                    <span>🐺 Associazione Cavalcalupi</span>
                </div>
                <h1 class="text-3xl font-extrabold text-white tracking-tight sm:text-4xl">
                    Calendario Attività & Sessioni
                </h1>
                <p class="text-sm text-slate-400 mt-2 max-w-2xl">
                    Consulta tutte le date delle campagne di gioco di ruolo, le assemblee e gli eventi associativi.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                @auth
                    <!-- ICS Feed Button -->
                    <button type="button" onclick="openIcsModal()" 
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-indigo-300 border border-indigo-500/30 hover:border-indigo-500/50 text-xs font-semibold shadow-md transition-all">
                        <span>📲</span>
                        <span>Feed ICS Personale</span>
                    </button>

                    <!-- Create Event Button -->
                    <a href="{{ route('events.create') }}" 
                       class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/30 transition-all">
                        <span>➕</span>
                        <span>Nuova Sessione / Evento</span>
                    </a>
                @else
                    <a href="{{ route('login') }}" 
                       class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/30 transition-all">
                        <span>🔑 Accedi per Sincronizzare ICS</span>
                    </a>
                @endauth
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 glass-panel rounded-2xl">
        <div class="flex items-center gap-2">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Filtra Eventi:</span>
            <div class="inline-flex rounded-xl p-1 bg-slate-900 border border-slate-800">
                <a href="{{ route('calendar.index', ['filter' => 'all']) }}" 
                   class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition-all {{ $filter === 'all' ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-slate-200' }}">
                    🌐 Tutti gli Eventi ({{ $events->count() }})
                </a>
                @auth
                    <a href="{{ route('calendar.index', ['filter' => 'my-groups']) }}" 
                       class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition-all {{ $filter === 'my-groups' ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-slate-200' }}">
                        🛡️ Solo i Miei Gruppi & Istituzionali
                    </a>
                @endauth
            </div>
        </div>

        <div class="flex items-center gap-3 text-xs text-slate-400">
            <span class="inline-flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span> Sessione Gruppo
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span> Assemblea
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> Evento Associativo
            </span>
        </div>
    </div>

    <!-- Events List -->
    @if($events->isEmpty())
        <div class="text-center py-16 glass-panel rounded-3xl">
            <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-slate-800/80 flex items-center justify-center text-3xl">
                🐺
            </div>
            <h3 class="text-lg font-bold text-white">Nessun evento in programma</h3>
            <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                {{ $filter === 'my-groups' ? 'Non ci sono sessioni programmate per i tuoi gruppi attivi o eventi istituzionali.' : 'Non ci sono eventi al momento.' }}
            </p>
            @auth
                <div class="mt-4">
                    <a href="{{ route('events.create') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-400 hover:text-indigo-300">
                        <span>➕ Pianifica la prima sessione</span>
                    </a>
                </div>
            @endauth
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($events as $event)
                <div class="glass-panel p-5 rounded-2xl flex flex-col justify-between hover:border-slate-700 transition-all hover:-translate-y-0.5 group">
                    <div>
                        <!-- Header badge and date -->
                        <div class="flex items-start justify-between gap-2 mb-3">
                            @if($event->type === 'session')
                                <span class="px-2.5 py-1 text-[11px] font-semibold rounded-lg bg-indigo-500/10 text-indigo-300 border border-indigo-500/20">
                                    🎲 Sessione
                                </span>
                            @elseif($event->type === 'assembly')
                                <span class="px-2.5 py-1 text-[11px] font-semibold rounded-lg bg-rose-500/10 text-rose-300 border border-rose-500/20">
                                    🏛️ Assemblea
                                </span>
                            @else
                                <span class="px-2.5 py-1 text-[11px] font-semibold rounded-lg bg-amber-500/10 text-amber-300 border border-amber-500/20">
                                    🎉 Evento Associativo
                                </span>
                            @endif

                            <span class="text-xs font-mono text-slate-400">
                                {{ $event->start_time->format('d/m/Y') }}
                            </span>
                        </div>

                        <!-- Title -->
                        <h2 class="text-base font-bold text-white group-hover:text-indigo-300 transition-colors line-clamp-2">
                            <a href="{{ route('events.show', $event) }}">
                                {{ $event->title }}
                            </a>
                        </h2>

                        <!-- Group info if session -->
                        @if($event->type === 'session' && $event->group)
                            <div class="mt-2 text-xs text-indigo-200 flex items-center gap-1.5 font-medium">
                                <span>👥</span>
                                <span class="truncate">{{ $event->group->name }}</span>
                            </div>
                        @endif

                        <!-- Description excerpt -->
                        @if($event->description)
                            <p class="text-xs text-slate-400 mt-2 line-clamp-2">
                                {{ $event->description }}
                            </p>
                        @endif

                        <!-- Time info -->
                        <div class="mt-4 pt-3 border-t border-slate-800/80 flex items-center justify-between text-xs text-slate-400">
                            <div class="flex items-center gap-1.5">
                                <span>🕒</span>
                                <span>{{ $event->start_time->format('H:i') }} &ndash; {{ $event->end_time->format('H:i') }}</span>
                            </div>
                            <div class="text-[11px] text-slate-500">
                                di {{ $event->creator->name ?? 'Sistema' }}
                            </div>
                        </div>
                    </div>

                    <!-- Footer actions -->
                    <div class="mt-4 pt-3 border-t border-slate-800/80 flex items-center justify-between">
                        <a href="{{ route('events.show', $event) }}" 
                           class="text-xs font-semibold text-slate-300 hover:text-white flex items-center gap-1 transition-colors">
                            <span>Dettagli e Storico</span>
                            <span>&rarr;</span>
                        </a>

                        @auth
                            @php
                                $canEdit = false;
                                if ($event->type === 'session') {
                                    $canEdit = Auth::user()->can('update-session', $event);
                                } else {
                                    $canEdit = Auth::user()->can('manage-assemblies');
                                }
                            @endphp

                            @if($canEdit)
                                <a href="{{ route('events.edit', $event) }}" 
                                   class="px-2.5 py-1 text-xs font-medium rounded-lg bg-slate-800 hover:bg-slate-700 text-indigo-300 border border-slate-700 transition-colors">
                                    ✏️ Modifica
                                </a>
                            @endif
                        @endauth
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<!-- ICS Link Modal -->
@auth
<div id="icsModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm hidden">
    <div class="glass-panel max-w-lg w-full p-6 sm:p-8 rounded-3xl border border-slate-700 shadow-2xl relative">
        <button type="button" onclick="closeIcsModal()" class="absolute top-5 right-5 text-slate-400 hover:text-white text-xl">
            &times;
        </button>

        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center text-xl">
                📲
            </div>
            <div>
                <h3 class="text-lg font-bold text-white">Sincronizzazione Feed ICS</h3>
                <p class="text-xs text-slate-400">Google Calendar, Apple Calendar, Outlook o Thunderbird</p>
            </div>
        </div>

        <p class="text-xs text-slate-300 leading-relaxed mb-4">
            Questo feed include in tempo reale le <strong class="text-white">sessioni dei tuoi tavoli di gioco attivi</strong>, oltre a tutte le assemblee ed eventi ufficiali dell'associazione.
        </p>

        <div class="mb-4">
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Il tuo URL ICS Personale:</label>
            <div class="flex items-center gap-2">
                <input type="text" id="icsUrlInput" readonly
                       value="{{ route('calendar.ics', Auth::user()->ics_token) }}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs font-mono text-indigo-300 focus:outline-none">
                <button type="button" onclick="copyIcsUrl()"
                        class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold whitespace-nowrap shadow-md transition-all">
                    Copia Link
                </button>
            </div>
            <p id="copyFeedback" class="text-[11px] text-emerald-400 mt-1.5 hidden font-medium">
                ✅ URL copiato negli appunti!
            </p>
        </div>

        <div class="p-3.5 rounded-xl bg-slate-900/80 border border-slate-800 text-xs text-slate-400 space-y-1">
            <div class="font-semibold text-slate-300">Come usarlo:</div>
            <div>• Su Google Calendar: clicca "Aggiungi altri calendari" &rarr; "Da URL" e incolla questo link.</div>
            <div>• Su Apple / iOS: Apri Calendario &rarr; File &rarr; Nuova sottoscrizione calendario.</div>
        </div>

        <div class="mt-6 flex justify-end">
            <a href="{{ route('calendar.ics', Auth::user()->ics_token) }}" 
               class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium border border-slate-700 transition-colors mr-2">
                Scarica File .ics
            </a>
            <button type="button" onclick="closeIcsModal()" 
                    class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold">
                Chiudi
            </button>
        </div>
    </div>
</div>
@endauth

@push('scripts')
<script>
    function openIcsModal() {
        const modal = document.getElementById('icsModal');
        if (modal) modal.classList.remove('hidden');
    }

    function closeIcsModal() {
        const modal = document.getElementById('icsModal');
        if (modal) modal.classList.add('hidden');
    }

    function copyIcsUrl() {
        const input = document.getElementById('icsUrlInput');
        if (!input) return;
        input.select();
        navigator.clipboard.writeText(input.value).then(() => {
            const feedback = document.getElementById('copyFeedback');
            if (feedback) {
                feedback.classList.remove('hidden');
                setTimeout(() => feedback.classList.add('hidden'), 3000);
            }
        });
    }
</script>
@endpush
@endsection
