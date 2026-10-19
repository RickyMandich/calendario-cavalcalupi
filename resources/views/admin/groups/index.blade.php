@extends('layouts.app', ['title' => 'Gestione Gruppi & Assegnazioni'])

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-300 text-xs font-semibold mb-2">
                <span>🛡️ Sezione CD / Amministrazione</span>
            </div>
            <h1 class="text-3xl font-extrabold text-white tracking-tight">Gestione Gruppi & Tavoli</h1>
            <p class="text-xs text-slate-400 mt-1">Crea nuovi tavoli di gioco, assegna membri e gestisci lo stato di attività</p>
        </div>

        <button type="button" onclick="openNewGroupModal()"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/30 transition-all self-start sm:self-auto">
            <span>➕</span>
            <span>Nuovo Gruppo</span>
        </button>
    </div>

    <!-- Groups Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach($groups as $group)
            <div class="glass-panel p-6 rounded-3xl border border-slate-800 shadow-xl flex flex-col justify-between {{ !$group->is_active ? 'opacity-70 bg-slate-950/80' : '' }}">
                <div>
                    <!-- Header with status badge & edit toggle -->
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <div class="flex items-center gap-2">
                            @if($group->is_active)
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                    🟢 Attivo
                                </span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                    🔴 Disattivato
                                </span>
                            @endif

                            <span class="text-xs text-slate-500">
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
                                    class="text-[11px] font-medium px-2.5 py-1 rounded-lg border transition-colors {{ $group->is_active ? 'border-rose-900/60 bg-rose-950/40 text-rose-300 hover:bg-rose-900/60' : 'border-emerald-900/60 bg-emerald-950/40 text-emerald-300 hover:bg-emerald-900/60' }}">
                                {{ $group->is_active ? 'Disattiva' : 'Riattiva' }}
                            </button>
                        </form>
                    </div>

                    <h2 class="text-lg font-bold text-white mb-2">
                        {{ $group->name }}
                    </h2>
                    <p class="text-[11px] text-slate-400 mb-4 font-mono">
                        Creato il {{ $group->created_at->format('d/m/Y') }}
                    </p>

                    <!-- Members list -->
                    <div class="space-y-2">
                        <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">
                            Membri Assegnati ({{ $group->users->count() }}):
                        </span>

                        @if($group->users->isEmpty())
                            <p class="text-xs text-slate-500 italic p-2 rounded-lg bg-slate-900/40 border border-slate-800">
                                Nessun utente assegnato a questo gruppo.
                            </p>
                        @else
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($group->users as $member)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-900/80 border border-slate-700/80 text-xs text-slate-300">
                                        <span>👤 {{ $member->name }}</span>
                                        <form method="POST" action="{{ route('admin.groups.users.remove', [$group, $member]) }}" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" title="Rimuovi dal gruppo" class="text-slate-500 hover:text-rose-400 font-bold ml-1">
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
                <div class="mt-6 pt-4 border-t border-slate-800">
                    @if($group->is_active)
                        <form method="POST" action="{{ route('admin.groups.users.assign', $group) }}" class="flex items-center gap-2">
                            @csrf
                            <select name="user_id" required
                                    class="flex-grow px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-200 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                <option value="">-- Aggiungi utente --</option>
                                @foreach($allUsers as $u)
                                    @if(!$group->users->contains($u))
                                        <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                                    @endif
                                @endforeach
                            </select>
                            <button type="submit" 
                                    class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-medium whitespace-nowrap shadow transition-all">
                                + Aggiungi
                            </button>
                        </form>
                    @else
                        <p class="text-[11px] text-slate-500 italic">
                            🔒 Non è possibile assegnare utenti a un gruppo disattivato. Riattivalo prima.
                        </p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>

<!-- Modal Nuovo Gruppo -->
<div id="newGroupModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm hidden">
    <div class="glass-panel max-w-md w-full p-6 sm:p-8 rounded-3xl border border-slate-700 shadow-2xl relative">
        <button type="button" onclick="closeNewGroupModal()" class="absolute top-5 right-5 text-slate-400 hover:text-white text-xl">
            &times;
        </button>

        <h3 class="text-xl font-bold text-white mb-1">Crea Nuovo Gruppo / Tavolo</h3>
        <p class="text-xs text-slate-400 mb-5">I tavoli attivi potranno pianificare sessioni ed essere sincronizzati nei feed ICS.</p>

        <form method="POST" action="{{ route('admin.groups.store') }}" class="space-y-4">
            @csrf

            <div>
                <label for="name" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Nome del Tavolo</label>
                <input type="text" id="name" name="name" required
                       class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-100 placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                       placeholder="Es: Vampiri: La Mascherata - Clan Tremere">
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" id="is_active" name="is_active" value="1" checked
                       class="rounded bg-slate-800 border-slate-700 text-indigo-600 focus:ring-0">
                <label for="is_active" class="text-xs text-slate-300 font-medium">Imposta immediatamente come Attivo</label>
            </div>

            <div class="pt-4 flex justify-end gap-2 border-t border-slate-800">
                <button type="button" onclick="closeNewGroupModal()" 
                        class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium border border-slate-700">
                    Annulla
                </button>
                <button type="submit" 
                        class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-md">
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
