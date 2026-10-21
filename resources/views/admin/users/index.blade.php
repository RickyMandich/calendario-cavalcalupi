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
                    <th class="p-2 text-left">Azioni</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr class="border-t border-brand-100">
                        <td class="p-2">{{ $user->name }}</td>
                        <td class="p-2">{{ $user->email }}</td>
                        <td class="p-2">{{ $user->created_at->format('d/m/Y') }}</td>
                        <td class="p-2">
                            @php $role = $user->roles->first()?->name ?? 'N/A'; @endphp
                            @if($role === 'ADMIN')
                                <span class="badge bg-rose-100 text-rose-800 border-rose-300">ADMIN</span>
                            @elseif($role === 'CD')
                                <span class="badge bg-amber-100 text-amber-800 border-amber-300">CD</span>
                            @else
                                <span class="badge bg-brand-100 text-brand-800 border-brand-300">Membro</span>
                            @endif
                        </td>
                        <td class="p-2">
                            @if(!$user->is(auth()->user()))
                                <form method="POST" action="{{ route('admin.users.role', $user) }}" class="inline-flex items-center gap-1">
                                    @csrf
                                    @method('PUT')
                                    <select name="role" class="field w-32">
                                        <option value="Membro" {{ $role === 'Membro' ? 'selected' : '' }}>Membro</option>
                                        <option value="CD" {{ $role === 'CD' ? 'selected' : '' }}>CD</option>
                                        <option value="ADMIN" {{ $role === 'ADMIN' ? 'selected' : '' }}>ADMIN</option>
                                    </select>
                                    <button type="submit" class="btn-primary">Salva</button>
                                </form>
                            @else
                                <span class="text-gray-500">(te stesso)</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-4 text-center">Nessun utente trovato.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <div class="mt-4">{{ $users->appends(request()->query())->links() }}</div>
    </div>
@endsection
