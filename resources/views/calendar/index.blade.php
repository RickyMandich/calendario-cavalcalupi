@extends('layouts.app', ['title' => 'Calendario Eventi & Sessioni'])

@section('content')
<div class="space-y-6">
    <!-- Header Banner -->
    <div class="panel p-6 sm:p-8 rounded-3xl relative overflow-hidden bg-gradient-to-br from-white to-brand-50">
        <div class="absolute -right-10 -bottom-10 w-72 h-72 bg-brand-200/50 rounded-full blur-3xl pointer-events-none"></div>

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 relative z-10">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-brand-100 border border-brand-200 text-brand-800 text-xs font-semibold mb-3">
                    <span>🐺 Associazione Cavalcalupi</span>
                </div>
                <h1 class="text-3xl font-extrabold text-brand-950 tracking-tight sm:text-4xl">
                    Calendario Attività & Sessioni
                </h1>
                <p class="text-sm text-gray-600 mt-2 max-w-2xl">
                    Consulta tutte le date delle campagne di gioco di ruolo, le assemblee e gli eventi associativi.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                @auth
                    <!-- ICS Feed Button (solo con email verificata) -->
                    @if(Auth::user()->hasVerifiedEmail())
                        <button type="button" onclick="openIcsModal()" class="btn-secondary">
                            <span>📲</span>
                            <span>Feed ICS Personale</span>
                        </button>
                    @else
                        <a href="{{ route('verification.notice') }}" class="btn-secondary">
                            <span>✉️</span>
                            <span>Verifica l'email per il Feed ICS</span>
                        </a>
                    @endif

                    <!-- Create Event Button (solo chi può gestire sessioni) -->
                    @if($canCreateSessions)
                        <a href="{{ route('events.create') }}" class="btn-primary">
                            <span>➕</span>
                            <span>Nuova Sessione / Evento</span>
                        </a>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="btn-primary">
                        <span>🔑 Accedi per Sincronizzare ICS</span>
                    </a>
                @endauth
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 panel rounded-2xl">
        <div class="flex items-center gap-2">
            <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Filtra Eventi:</span>
            <div class="inline-flex rounded-xl p-1 bg-brand-50 border border-brand-100">
                <a href="{{ route('calendar.index', ['filter' => 'all', 'view' => $view === 'list' ? 'list' : null]) }}"
                   class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition-all {{ $filter === 'all' ? 'bg-brand-600 text-white shadow' : 'text-gray-600 hover:text-brand-800' }}">
                    🌐 Tutti gli Eventi{{ $view === 'list' ? ' ('.$events->count().')' : '' }}
                </a>
                @auth
                    <a href="{{ route('calendar.index', ['filter' => 'my-groups', 'view' => $view === 'list' ? 'list' : null]) }}"
                       class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition-all {{ $filter === 'my-groups' ? 'bg-brand-600 text-white shadow' : 'text-gray-600 hover:text-brand-800' }}">
                        🛡️ Solo i Miei Gruppi & Istituzionali
                    </a>
                @endauth
            </div>
        </div>

        <div class="flex items-center gap-3 text-xs text-gray-500">
            <span class="inline-flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-brand-600"></span> Sessione Gruppo
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span> Assemblea
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> Evento Associativo
            </span>
        </div>
    </div>

    <!-- Selettore vista -->
    <div class="flex items-center gap-2">
        <a href="{{ route('calendar.index', ['filter' => $filter]) }}"
           class="{{ $view === 'calendar' ? 'btn-primary' : 'btn-secondary' }}">📅 Calendario</a>
        <a href="{{ route('calendar.index', ['filter' => $filter, 'view' => 'list']) }}"
           class="{{ $view === 'list' ? 'btn-primary' : 'btn-secondary' }}">📃 Elenco</a>
    </div>

    @if($view === 'calendar')
        <div class="panel p-3 sm:p-5 rounded-3xl">
            <div id="calendar"
                 data-events-url="{{ route('calendar.events') }}"
                 data-availability-url="{{ route('calendar.availability') }}"
                 data-filter="{{ $filter }}"></div>
        </div>
    @elseif($events->isEmpty())
        <div class="text-center py-16 panel rounded-3xl">
            <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-brand-50 flex items-center justify-center text-3xl">
                🐺
            </div>
            <h3 class="text-lg font-bold text-brand-950">Nessun evento in programma</h3>
            <p class="text-xs text-gray-500 mt-1 max-w-sm mx-auto">
                {{ $filter === 'my-groups' ? 'Non ci sono sessioni programmate per i tuoi gruppi attivi o eventi istituzionali.' : 'Non ci sono eventi al momento.' }}
            </p>
            @auth
                <div class="mt-4">
                    <a href="{{ route('events.create') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-700 hover:text-brand-900">
                        <span>➕ Pianifica la prima sessione</span>
                    </a>
                </div>
            @endauth
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($events as $event)
                <div class="panel p-5 rounded-2xl flex flex-col justify-between hover:border-brand-300 hover:shadow-md transition-all hover:-translate-y-0.5 group">
                    <div>
                        <!-- Header badge and date -->
                        <div class="flex items-start justify-between gap-2 mb-3">
                            @if($event->type === 'session')
                                <span class="px-2.5 py-1 text-[11px] font-semibold rounded-lg bg-brand-50 text-brand-800 border border-brand-200">
                                    🎲 Sessione
                                </span>
                            @elseif($event->type === 'assembly')
                                <span class="px-2.5 py-1 text-[11px] font-semibold rounded-lg bg-rose-50 text-rose-700 border border-rose-200">
                                    🏛️ Assemblea
                                </span>
                            @else
                                <span class="px-2.5 py-1 text-[11px] font-semibold rounded-lg bg-amber-50 text-amber-700 border border-amber-200">
                                    🎉 Evento Associativo
                                </span>
                            @endif

                            <span class="text-xs font-mono text-gray-500">
                                @if($event->swapRequests->isNotEmpty()) <span title="Scambio richiesto">🔁</span> @endif
                                {{ $event->start_time->format('d/m/Y') }}
                            </span>
                        </div>

                        <!-- Title -->
                        <h2 class="text-base font-bold text-brand-950 group-hover:text-brand-700 transition-colors line-clamp-2">
                            <a href="{{ route('events.show', $event) }}">
                                {{ $event->title }}
                            </a>
                        </h2>

                        <!-- Group info if session -->
                        @if($event->type === 'session' && $event->group)
                            <div class="mt-2 text-xs text-brand-700 flex items-center gap-1.5 font-medium">
                                <span>👥</span>
                                <span class="truncate">{{ $event->group->name }}</span>
                            </div>
                        @endif

                        <!-- Description excerpt -->
                        @if($event->description)
                            <p class="text-xs text-gray-500 mt-2 line-clamp-2">
                                {{ $event->description }}
                            </p>
                        @endif

                        <!-- Time info -->
                        <div class="mt-4 pt-3 border-t border-brand-100 flex items-center justify-between text-xs text-gray-500">
                            <div class="flex items-center gap-1.5">
                                <span>🕒</span>
                                <span>{{ $event->start_time->format('H:i') }} &ndash; {{ $event->end_time->format('H:i') }}</span>
                            </div>
                            <div class="text-[11px] text-gray-400">
                                di {{ $event->creator->name ?? 'Sistema' }}
                            </div>
                        </div>
                    </div>

                    <!-- Footer actions -->
                    <div class="mt-4 pt-3 border-t border-brand-100 flex items-center justify-between">
                        <a href="{{ route('events.show', $event) }}"
                           class="text-xs font-semibold text-gray-600 hover:text-brand-800 flex items-center gap-1 transition-colors">
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
                                   class="px-2.5 py-1 text-xs font-medium rounded-lg bg-white hover:bg-brand-50 text-brand-700 border border-brand-200 transition-colors">
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

<!-- Modale di dettaglio evento (calendario) -->
@if($view === 'calendar')
<div id="eventModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 modal-overlay backdrop-blur-sm hidden">
    <div class="panel max-w-lg w-full p-6 sm:p-8 rounded-3xl shadow-2xl relative">
        <button type="button" id="eventModalClose" class="absolute top-5 right-5 text-gray-400 hover:text-brand-800 text-xl" aria-label="Chiudi">&times;</button>
        <div id="eventModalBadge" class="text-xs font-semibold text-brand-700 mb-1"></div>
        <h3 id="eventModalTitle" class="text-xl font-bold text-brand-950 mb-2"></h3>
        <div id="eventModalWhen" class="text-sm text-gray-700 mb-1"></div>
        <div id="eventModalGroup" class="text-xs text-brand-700 mb-2"></div>
        <p id="eventModalDesc" class="text-xs text-gray-600 whitespace-pre-line mb-4"></p>
        <div class="flex justify-end gap-2">
            <a id="eventModalEdit" href="#" class="btn-secondary hidden">✏️ Modifica</a>
            <a id="eventModalDetails" href="#" class="btn-primary hidden">Dettagli e storico</a>
        </div>
    </div>
</div>
@endif

<!-- ICS Link Modal (solo con email verificata) -->
@if(Auth::check() && Auth::user()->hasVerifiedEmail())
<div id="icsModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 modal-overlay backdrop-blur-sm hidden">
    <div class="panel max-w-lg w-full p-6 sm:p-8 rounded-3xl border border-brand-200 shadow-2xl relative">
        <button type="button" onclick="closeIcsModal()" class="absolute top-5 right-5 text-gray-400 hover:text-brand-800 text-xl">
            &times;
        </button>

        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-xl bg-brand-100 text-brand-700 flex items-center justify-center text-xl">
                📲
            </div>
            <div>
                <h3 class="text-lg font-bold text-brand-950">Sincronizzazione Feed ICS</h3>
                <p class="text-xs text-gray-500">Google Calendar, Apple Calendar, Outlook o Thunderbird</p>
            </div>
        </div>

        <p class="text-xs text-gray-600 leading-relaxed mb-4">
            Questo feed include in tempo reale le <strong class="text-brand-950">sessioni dei tuoi tavoli di gioco attivi</strong>, oltre a tutte le assemblee ed eventi ufficiali dell'associazione.
        </p>

        <div class="mb-4">
            <label class="field-label">Il tuo URL ICS Personale:</label>
            <div class="flex items-center gap-2">
                <input type="text" id="icsUrlInput" readonly
                       value="{{ route('calendar.ics', ['token' => Auth::user()->ics_token]) }}"
                       class="field text-xs font-mono text-brand-800 bg-brand-50">
                <button type="button" onclick="copyIcsUrl()" class="btn-primary whitespace-nowrap">
                    Copia Link
                </button>
            </div>
            <p id="copyFeedback" class="text-[11px] text-brand-700 mt-1.5 hidden font-medium">
                ✅ URL copiato negli appunti!
            </p>
        </div>

        <div class="p-3.5 rounded-xl bg-brand-50 border border-brand-100 text-xs text-gray-600 space-y-1">
            <div class="font-semibold text-brand-900">Come usarlo:</div>
            <div>• Su Google Calendar: clicca "Aggiungi altri calendari" &rarr; "Da URL" e incolla questo link.</div>
            <div>• Su Apple / iOS: Apri Calendario &rarr; File &rarr; Nuova sottoscrizione calendario.</div>
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('calendar.ics', ['token' => Auth::user()->ics_token]) }}" class="btn-secondary">
                Scarica File .ics
            </a>
            <button type="button" onclick="closeIcsModal()" class="btn-primary">
                Chiudi
            </button>
        </div>
    </div>
</div>
@endif

@push('scripts')
@if($view === 'calendar')
    @vite('resources/js/calendar.js')
@endif
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
