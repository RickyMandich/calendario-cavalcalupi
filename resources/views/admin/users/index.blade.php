@extends('layouts.app')

@section('content')
    <div class="panel p-5 sm:p-6">
        <h2 class="text-xl font-semibold mb-4">Gestione Utenti</h2>
        <form method="GET" action="{{ route('admin.users.index') }}" class="flex items-center gap-2 mb-4">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cerca per nome o email"
                   class="field w-64" />
            <button type="submit" class="btn-secondary">Cerca</button>
            @if(request('q'))
                <a href="{{ route('admin.users.index') }}" class="btn-secondary">Reset</a>
            @endif
        </form>
        <div class="overflow-x-auto rounded-xl border border-brand-200">
        <table class="w-full">
            <thead>
                <tr class="bg-brand-50">
                    <th class="p-2 text-left">Nome</th>
                    <th class="p-2 text-left">Email</th>
                    <th class="p-2 text-left">Registrato il</th>
                    <th class="p-2 text-left">Ruolo</th>
                    <th class="p-2 text-left">Confermato</th>
                    <th class="p-2 text-left">Azioni</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    @php
                        $role = $user->roles->first()?->name ?? 'N/A';
                        $isSelf = $user->is(auth()->user());
                        $verified = $user->hasVerifiedEmail();
                        // La conferma si può togliere solo a un Membro senza gruppi (invariante: non verificato = solo Membro, nessun gruppo)
                        $canUnverify = $role === 'Membro' && $user->groups_count === 0;
                    @endphp
                    <tr class="border-t border-brand-100">
                        <td class="p-2">{{ $user->name }}</td>
                        <td class="p-2">
                            {{ $user->email }}
                            @unless($verified)
                                <span class="badge bg-amber-100 text-amber-800 border-amber-300 ml-1" title="Può essere solo Membro, senza gruppi né feed ICS">Email non verificata</span>
                            @endunless
                        </td>
                        <td class="p-2">{{ $user->created_at->format('d/m/Y') }}</td>
                        <td class="p-2">
                            @if($role === 'ADMIN')
                                <span class="badge bg-rose-100 text-rose-800 border-rose-300">ADMIN</span>
                            @elseif($role === 'CD')
                                <span class="badge bg-amber-100 text-amber-800 border-amber-300">CD</span>
                            @else
                                <span class="badge bg-brand-100 text-brand-800 border-brand-300">Membro</span>
                            @endif
                        </td>
                        <td class="p-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <form method="POST" action="{{ route('admin.users.verified', $user) }}">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="verified" value="0">
                                    <label class="inline-flex items-center gap-1.5 text-sm"
                                           @if($isSelf) title="Non puoi modificare il tuo account"
                                           @elseif($verified && ! $canUnverify) title="La conferma si può togliere solo a un Membro senza gruppi"
                                           @else title="Conferma manualmente l'email di questo utente" @endif>
                                        <input type="checkbox" name="verified" value="1"
                                               class="rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                                               @checked($verified)
                                               @disabled($isSelf || ($verified && ! $canUnverify))
                                               onchange="this.form.submit()">
                                        <span>Confermato</span>
                                    </label>
                                    <noscript><button type="submit" class="btn-secondary px-2.5 py-1.5">Salva</button></noscript>
                                </form>
                                @unless($verified)
                                    <form method="POST" action="{{ route('admin.users.verification', $user) }}">
                                        @csrf
                                        <button type="submit" class="btn-secondary px-2.5 py-1.5" title="Reinvia il link di conferma a {{ $user->email }}">✉️ Reinvia mail</button>
                                    </form>
                                @endunless
                            </div>
                        </td>
                        <td class="p-2">
                            @if(!$isSelf)
                                <div class="flex flex-wrap items-center gap-2">
                                    <form method="POST" action="{{ route('admin.users.role', $user) }}" class="inline-flex items-center gap-1">
                                        @csrf
                                        @method('PUT')
                                        <select name="role" class="field w-32">
                                            <option value="Membro" {{ $role === 'Membro' ? 'selected' : '' }}>Membro</option>
                                            <option value="CD" {{ $role === 'CD' ? 'selected' : '' }} @disabled(! $verified)>CD</option>
                                            <option value="ADMIN" {{ $role === 'ADMIN' ? 'selected' : '' }} @disabled(! $verified)>ADMIN</option>
                                        </select>
                                        <button type="submit" class="btn-primary">Salva</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                                          onsubmit="return confirm('Eliminare definitivamente questo utente? Le sessioni che ha creato restano nel calendario, ma perde accesso, gruppi e ruolo. L\'operazione non si può annullare.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="px-3 py-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-medium transition-colors">
                                            🗑️ Elimina
                                        </button>
                                    </form>
                                </div>
                            @else
                                <a href="{{ route('profile.edit') }}" class="text-gray-500 hover:text-brand-700 underline">(te stesso)</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-4 text-center">Nessun utente trovato.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <div class="mt-4">{{ $users->appends(request()->query())->links() }}</div>
    </div>
@endsection
