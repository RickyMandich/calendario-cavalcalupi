@extends('layouts.app')

@section('content')
    <div class="panel">
        <h2 class="text-xl font-semibold mb-4">Gestione Utenti</h2>
        <form method="GET" action="{{ route('admin.users.index') }}" class="flex items-center gap-2 mb-4">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cerca per nome o email"
                   class="field w-64" />
            <button type="submit" class="btn-secondary">Cerca</button>
            @if(request('q'))
                <a href="{{ route('admin.users.index') }}" class="btn-secondary">Reset</a>
            @endif
        </form>
        <table class="w-full">
            <thead>
                <tr class="bg-gray-100">
                    <th class="p-2 text-left">Nome</th>
                    <th class="p-2 text-left">Email</th>
                    <th class="p-2 text-left">Registrato il</th>
                    <th class="p-2 text-left">Ruolo</th>
                    <th class="p-2 text-left">Azioni</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr class="border-b">
                        <td class="p-2">{{ $user->name }}</td>
                        <td class="p-2">{{ $user->email }}</td>
                        <td class="p-2">{{ $user->created_at->format('d/m/Y') }}</td>
                        <td class="p-2">
                            @php $role = $user->roles->first()?->name ?? 'N/A'; @endphp
                            @if($role === 'ADMIN')
                                <span class="badge bg-red-500 text-white">ADMIN</span>
                            @elseif($role === 'CD')
                                <span class="badge bg-amber-500 text-white">CD</span>
                            @else
                                <span class="badge bg-green-500 text-white">Membro</span>
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
        <div class="mt-4">{{ $users->appends(request()->query())->links() }}</div>
    </div>
@endsection
