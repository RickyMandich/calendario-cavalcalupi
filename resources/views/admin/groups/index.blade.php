@extends('layouts.app', ['title' => 'Gestione Gruppi & Assegnazioni'])

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-brand-100 border border-brand-200 text-brand-800 text-xs font-semibold mb-2">
                <span>🛡️ Sezione CD / Amministrazione</span>
            </div>
            <h1 class="text-3xl font-extrabold text-brand-950 tracking-tight">Gestione Gruppi & Tavoli</h1>
            <p class="text-xs text-gray-500 mt-1">Crea nuovi tavoli di gioco, assegna membri e gestisci lo stato di attività</p>
            @if($unverifiedCount > 0)
                <p class="text-[11px] text-amber-700 mt-1">
                    Possono essere assegnati solo utenti con email verificata ({{ $unverifiedCount }} non {{ $unverifiedCount === 1 ? 'verificato' : 'verificati' }}, non elencati).
                </p>
            @endif
        </div>

        <button type="button" onclick="openNewGroupModal()" class="btn-primary self-start sm:self-auto">
            <span>➕</span>
            <span>Nuovo Gruppo</span>
        </button>
    </div>

    <!-- Groups Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach($groups as $group)
            <div class="panel p-6 rounded-3xl shadow-xl flex flex-col justify-between {{ !$group->is_active ? 'opacity-70 bg-gray-50' : '' }}">
                <div>
                    <!-- Header with status badge & edit toggle -->
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <div class="flex items-center gap-2">
                            @if($group->is_active)
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-brand-50 text-brand-700 border border-brand-200">
                                    🟢 Attivo
                                </span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                    🔴 Disattivato
                                </span>
                            @endif

                            <span class="text-xs text-gray-400">
                                {{ $group->events_count }} sessioni
                            </span>
                        </div>

                        <!-- Toggle active form -->
                        <form method="POST" action="{{ route('admin.groups.update', $group) }}" class="inline">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="name" value="{{ $group->name }}">
                            <input type="hidden" name="is_active" value="{{ $group->is_active ? 0 : 1 }}">
                            <button type="submit"
                                    class="text-[11px] font-medium px-2.5 py-1 rounded-lg border transition-colors {{ $group->is_active ? 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100' : 'border-brand-200 bg-brand-50 text-brand-700 hover:bg-brand-100' }}">
                                {{ $group->is_active ? 'Disattiva' : 'Riattiva' }}
                            </button>
                        </form>
                    </div>

                    <h2 class="text-lg font-bold text-brand-950 mb-2">
                        {{ $group->name }}
                    </h2>
                    <p class="text-[11px] text-gray-500 mb-4 font-mono">
                        Creato il {{ $group->created_at->format('d/m/Y') }}
                    </p>

                    @php $managersCount = $group->users->filter(fn ($u) => $u->pivot->can_manage_sessions)->count(); @endphp
                    <p class="text-xs mb-3 {{ $managersCount ? 'text-brand-700' : 'text-amber-700' }}">
                        @if($managersCount)
                            ⭐ Responsabili: {{ $managersCount }}
                        @else
                            Nessun responsabile: solo CD/Admin possono modificare le sessioni
                        @endif
                    </p>

                    <!-- Members list -->
                    <div class="space-y-2">
                        <span class="block text-xs font-semibold uppercase tracking-wider text-gray-500">
                            Membri Assegnati ({{ $group->users->count() }}):
                        </span>

                        @if($group->users->isEmpty())
                            <p class="text-xs text-gray-400 italic p-2 rounded-lg bg-brand-50 border border-brand-100">
                                Nessun utente assegnato a questo gruppo.
                            </p>
                        @else
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($group->users as $member)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-brand-50 border border-brand-200 text-xs text-brand-900">
                                        <span>👤 {{ $member->name }}</span>
                                        @if($member->pivot->can_manage_sessions)
                                            <span title="Può gestire le sessioni del gruppo">⭐ Responsabile</span>
                                        @endif
                                        @if($group->is_active)
                                            <form method="POST" action="{{ route('admin.groups.users.manager', [$group, $member]) }}" class="inline">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="can_manage_sessions" value="{{ $member->pivot->can_manage_sessions ? 0 : 1 }}">
                                                <button type="submit" class="text-[10px] underline text-brand-700 hover:text-brand-900">
                                                    {{ $member->pivot->can_manage_sessions ? 'Togli responsabilità' : 'Rendi responsabile' }}
                                                </button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('admin.groups.users.remove', [$group, $member]) }}" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" title="Rimuovi dal gruppo" class="text-gray-400 hover:text-rose-600 font-bold ml-1">
                                                &times;
                                            </button>
                                        </form>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Assign User Form -->
                <div class="mt-6 pt-4 border-t border-brand-100">
                    @if($group->is_active)
                        <form method="POST" action="{{ route('admin.groups.users.assign', $group) }}" class="flex items-center gap-2">
                            @csrf
                            <select name="user_id" required class="field flex-grow py-1.5 text-xs">
                                <option value="">-- Aggiungi utente --</option>
                                @foreach($allUsers as $u)
                                    @if(!$group->users->contains($u))
                                        <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                                    @endif
                                @endforeach
                            </select>
                            <button type="submit" class="btn-primary px-3 py-1.5 whitespace-nowrap">
                                + Aggiungi
                            </button>
                        </form>
                    @else
                        <p class="text-[11px] text-gray-400 italic">
                            🔒 Non è possibile assegnare utenti a un gruppo disattivato. Riattivalo prima.
                        </p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>

<!-- Modal Nuovo Gruppo -->
<div id="newGroupModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 modal-overlay backdrop-blur-sm hidden">
    <div class="panel max-w-md w-full p-6 sm:p-8 rounded-3xl border border-brand-200 shadow-2xl relative">
        <button type="button" onclick="closeNewGroupModal()" class="absolute top-5 right-5 text-gray-400 hover:text-brand-800 text-xl">
            &times;
        </button>

        <h3 class="text-xl font-bold text-brand-950 mb-1">Crea Nuovo Gruppo / Tavolo</h3>
        <p class="text-xs text-gray-500 mb-5">I tavoli attivi potranno pianificare sessioni ed essere sincronizzati nei feed ICS.</p>

        <form method="POST" action="{{ route('admin.groups.store') }}" class="space-y-4">
            @csrf

            <div>
                <label for="name" class="field-label">Nome del Tavolo</label>
                <input type="text" id="name" name="name" required
                       class="field"
                       placeholder="Es: Vampiri: La Mascherata - Clan Tremere">
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" id="is_active" name="is_active" value="1" checked
                       class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                <label for="is_active" class="text-xs text-gray-700 font-medium">Imposta immediatamente come Attivo</label>
            </div>

            <div class="pt-4 flex justify-end gap-2 border-t border-brand-100">
                <button type="button" onclick="closeNewGroupModal()" class="btn-secondary">
                    Annulla
                </button>
                <button type="submit" class="btn-primary px-5">
                    Salva Gruppo
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function openNewGroupModal() {
        const modal = document.getElementById('newGroupModal');
        if (modal) modal.classList.remove('hidden');
    }
    function closeNewGroupModal() {
        const modal = document.getElementById('newGroupModal');
        if (modal) modal.classList.add('hidden');
    }
</script>
@endpush
@endsection
