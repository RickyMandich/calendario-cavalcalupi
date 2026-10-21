@extends('layouts.app', ['title' => 'Importa Calendario'])

@section('content')
<div class="space-y-6">
    <div>
        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-brand-100 border border-brand-200 text-brand-800 text-xs font-semibold mb-2">
            <span>🛡️ Sezione CD / Amministrazione</span>
        </div>
        <h1 class="text-3xl font-extrabold text-brand-950 tracking-tight">Importa il calendario dal foglio Excel</h1>
        <p class="text-xs text-gray-500 mt-1 max-w-3xl">
            Carica il foglio mensile usato oggi (titolo del mese, righe «Giovedì 3», «Venerdì 4», una cella per tavolo con sistema e campagna).
            Vedrai un'anteprima con gli errori riga per riga: <strong>se anche una sola riga è errata non viene creato nulla</strong>.
        </p>
    </div>

    @if(! $preview)
        <div class="panel p-6 space-y-5">
            <form method="POST" action="{{ route('admin.import.store') }}" enctype="multipart/form-data" class="space-y-5">
                @csrf
                <div>
                    <label for="file" class="field-label">File Excel (.xlsx, massimo 2 MB)</label>
                    <input type="file" id="file" name="file" accept=".xlsx,.xls" required class="field">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="season_year" class="field-label">Anno di inizio stagione</label>
                        <input type="number" id="season_year" name="season_year" min="2000" max="2100" required class="field"
                               value="{{ old('season_year', $options->seasonYear) }}">
                        <p class="text-[11px] text-gray-500 mt-1">Settembre–dicembre = questo anno, gennaio–agosto = quello dopo.</p>
                    </div>
                    <div>
                        <span class="field-label">Orario sessioni</span>
                        <div class="flex items-center gap-2">
                            <input type="time" name="session_start" required class="field" value="{{ old('session_start', $options->sessionStart) }}">
                            <span>–</span>
                            <input type="time" name="session_end" required class="field" value="{{ old('session_end', $options->sessionEnd) }}">
                        </div>
                        <p class="text-[11px] text-gray-500 mt-1">Se la fine è prima dell'inizio (es. 00:00) è a mezzanotte del giorno dopo.</p>
                    </div>
                    <div>
                        <span class="field-label">Orario eventi / assemblee</span>
                        <div class="flex items-center gap-2">
                            <input type="time" name="event_start" required class="field" value="{{ old('event_start', $options->eventStart) }}">
                            <span>–</span>
                            <input type="time" name="event_end" required class="field" value="{{ old('event_end', $options->eventEnd) }}">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-primary">Carica e mostra anteprima</button>
            </form>

            <div class="text-xs text-gray-600 space-y-1">
                <p><strong>Come viene letto il foglio</strong></p>
                <ul class="list-disc list-inside space-y-0.5">
                    <li>Ogni cella con testo accanto a «Giovedì 3» è un tavolo: prima riga = sistema di gioco (finisce nella descrizione), seconda riga = campagna = nome del gruppo. Una sola riga (es. «SCUOLE MASTER») è il nome del gruppo.</li>
                    <li>Una cella unita su 3 o più colonne è una banda (festività, evento, assemblea, «Gioco libero»): nell'anteprima scegli cosa farne.</li>
                    <li>Le righe senza testo (es. un giorno chiuso) non producono nulla; un «Venerdì 1» dopo il 31 è del mese successivo.</li>
                    <li>Le sessioni rispettano stanze e capienza configurate: senza stanze per quel giorno o a giorno pieno la riga è in errore.</li>
                </ul>
            </div>
        </div>
    @else
        <form method="POST" action="{{ route('admin.import.confirm') }}" class="space-y-6">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="season_year" value="{{ $options->seasonYear }}">
            <input type="hidden" name="session_start" value="{{ $options->sessionStart }}">
            <input type="hidden" name="session_end" value="{{ $options->sessionEnd }}">
            <input type="hidden" name="event_start" value="{{ $options->eventStart }}">
            <input type="hidden" name="event_end" value="{{ $options->eventEnd }}">

            <div class="panel p-5 flex flex-wrap items-center gap-4 text-sm">
                <span class="font-semibold text-brand-800">✅ Righe valide: {{ $preview->validCount() }}</span>
                <span class="font-semibold {{ $preview->errorCount() ? 'text-rose-700' : 'text-gray-500' }}">❌ Righe con errori: {{ $preview->errorCount() }}</span>
                <span class="text-xs text-gray-500">
                    Stagione {{ $options->seasonYear }}/{{ $options->seasonYear + 1 }} · sessioni {{ $options->sessionStart }}–{{ $options->sessionEnd }}
                </span>
            </div>

            <!-- Gruppi -->
            <div class="panel p-5 space-y-3">
                <h2 class="text-lg font-bold text-brand-950">Gruppi</h2>
                <p class="text-xs text-gray-500">
                    Un nome nel foglio può non coincidere con un gruppo esistente (refuso, gruppo nuovo): scegli il gruppo corretto oppure «Crea nuovo gruppo».
                    La scelta vale per tutte le sessioni con quel nome.
                </p>
                <div class="overflow-x-auto rounded-xl border border-brand-200">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-brand-50 text-gray-500 uppercase tracking-wider text-[11px]">
                                <th class="py-2 px-3">Nome nel foglio</th>
                                <th class="py-2 px-3">Sessioni</th>
                                <th class="py-2 px-3">Gruppo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($preview->groups as $key => $g)
                                <tr class="border-t border-brand-100 {{ $g['selected'] === '' ? 'bg-amber-50' : '' }}">
                                    <td class="py-2 px-3 font-medium">
                                        {{ $g['name'] }}
                                        @if($g['inactive'])
                                            <span class="block text-[11px] text-amber-700">Esiste un gruppo con questo nome ma non è attivo.</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-3">{{ $g['count'] }}</td>
                                    <td class="py-2 px-3">
                                        <select name="group_map[{{ $key }}]" class="field">
                                            <option value="" {{ $g['selected'] === '' ? 'selected' : '' }}>— scegli —</option>
                                            @foreach($activeGroups as $group)
                                                <option value="{{ $group->id }}" {{ $g['selected'] === (string) $group->id ? 'selected' : '' }}>{{ $group->name }}</option>
                                            @endforeach
                                            <option value="new" {{ $g['selected'] === 'new' ? 'selected' : '' }}>➕ Crea nuovo gruppo «{{ $g['name'] }}»</option>
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Bande -->
            @if($preview->banners)
                <div class="panel p-5 space-y-3">
                    <h2 class="text-lg font-bold text-brand-950">Bande (festività, eventi, «Gioco libero»)</h2>
                    <p class="text-xs text-gray-500">
                        Festività e serate speciali diventano eventi associativi (non bloccano le sessioni); un'assemblea invece non ammette altri eventi nello stesso orario.
                        «Ignora» salta la banda (es. «Gioco libero»: nessun tavolo in programma).
                    </p>
                    <div class="overflow-x-auto rounded-xl border border-brand-200">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="bg-brand-50 text-gray-500 uppercase tracking-wider text-[11px]">
                                    <th class="py-2 px-3">Testo</th>
                                    <th class="py-2 px-3">Giorni</th>
                                    <th class="py-2 px-3">Importa come</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($preview->banners as $key => $b)
                                    <tr class="border-t border-brand-100">
                                        <td class="py-2 px-3 font-medium">{{ $b['text'] }}</td>
                                        <td class="py-2 px-3">{{ $b['count'] }}</td>
                                        <td class="py-2 px-3">
                                            <select name="banner_action[{{ $key }}]" class="field">
                                                <option value="association_event" {{ $b['action'] === 'association_event' ? 'selected' : '' }}>Evento associativo</option>
                                                <option value="assembly" {{ $b['action'] === 'assembly' ? 'selected' : '' }}>Assemblea</option>
                                                <option value="ignore" {{ $b['action'] === 'ignore' ? 'selected' : '' }}>Ignora</option>
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <!-- Righe -->
            <div class="panel p-5 space-y-3">
                <h2 class="text-lg font-bold text-brand-950">Anteprima</h2>
                <div class="overflow-x-auto rounded-xl border border-brand-200">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-brand-50 text-gray-500 uppercase tracking-wider text-[11px]">
                                <th class="py-2 px-3">Foglio / riga</th>
                                <th class="py-2 px-3">Serata</th>
                                <th class="py-2 px-3">Tipo</th>
                                <th class="py-2 px-3">Contenuto</th>
                                <th class="py-2 px-3">Esito</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($preview->rows as $row)
                                <tr class="border-t border-brand-100 {{ $row->isIgnored() ? 'opacity-50' : '' }}">
                                    <td class="py-2 px-3 font-mono">{{ $row->sheet }} · {{ $row->number }}</td>
                                    <td class="py-2 px-3">
                                        {{ $row->label }}
                                        @if($row->date)
                                            <span class="block font-mono text-[11px] text-gray-500">{{ \Carbon\Carbon::parse($row->date)->format('d/m/Y') }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-3">
                                        @if($row->kind === 'table')
                                            Sessione
                                        @else
                                            Banda
                                        @endif
                                    </td>
                                    <td class="py-2 px-3">
                                        @if($row->kind === 'table')
                                            <strong>{{ $row->groupName }}</strong>
                                            @if($row->system)<span class="block text-[11px] text-gray-500">Sistema: {{ $row->system }}</span>@endif
                                        @else
                                            {{ $row->text }}
                                        @endif
                                    </td>
                                    <td class="py-2 px-3">
                                        @if($row->isIgnored())
                                            Ignorata
                                        @elseif($row->isValid())
                                            ✅
                                        @else
                                            <ul class="list-disc list-inside text-rose-700">
                                                @foreach($row->errors as $err)
                                                    <li>{{ $err }}</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @unless($preview->canCommit())
                    <p class="text-xs text-amber-700">Correggi il foglio (o stanze/capienza) e ricaricalo: finché ci sono errori l'importazione non è possibile.</p>
                @endunless
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="btn-primary" @unless($preview->canCommit()) disabled @endunless>
                    Conferma importazione ({{ $preview->validCount() }} eventi)
                </button>
                <button type="submit" form="cancel-import" class="btn-secondary">Annulla</button>
            </div>
        </form>

        <form id="cancel-import" method="POST" action="{{ route('admin.import.cancel') }}" class="hidden">
            @csrf
            @method('DELETE')
            <input type="hidden" name="token" value="{{ $token }}">
        </form>
    @endif
</div>
@endsection
