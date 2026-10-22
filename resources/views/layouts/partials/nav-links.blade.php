{{-- Voci di navigazione principali. Usata due volte da layouts/app.blade.php:
     $mobile = false -> link in linea nella barra (da md in su)
     $mobile = true  -> voci a tutta larghezza nel menu a comparsa (sotto md) --}}
@php
    $base = ($mobile ? 'block w-full px-3 py-2.5' : 'px-3 py-2') . ' text-sm font-medium rounded-lg transition-colors';
    $on = 'bg-brand-100 text-brand-800 border border-brand-200';
    $off = 'text-gray-600 hover:text-brand-800 hover:bg-brand-50';
    $link = fn (bool $active) => $base . ' ' . ($active ? $on : $off);
@endphp

<a href="{{ route('calendar.index') }}" class="{{ $link(request()->routeIs('calendar.*')) }}">
    📅 Calendario
</a>

@auth
    @can('manage-groups')
        <a href="{{ route('admin.groups.index') }}" class="{{ $link(request()->routeIs('admin.groups.*')) }}">
            👥 Gestione Gruppi
        </a>
    @endcan

    @can('manage-rooms')
        <a href="{{ route('admin.rooms.index') }}" class="{{ $link(request()->routeIs('admin.rooms.*')) }}">
            🏠 Stanze
        </a>
    @endcan

    @can('import-calendar')
        <a href="{{ route('admin.import.create') }}" class="{{ $link(request()->routeIs('admin.import.*')) }}">
            📥 Import
        </a>
    @endcan

    <a href="{{ route('swap-requests.index') }}" class="{{ $link(request()->routeIs('swap-requests.*')) }}">
        🔁 Scambi
    </a>

    @can('manage-users')
        <a href="{{ route('admin.users.index') }}" class="{{ $link(request()->routeIs('admin.users.*')) }}">
            👤 Utenti
        </a>
    @endcan

    @role('ADMIN')
        <a href="/log-viewer" target="_blank"
            class="{{ $base }} text-amber-700 hover:text-amber-800 hover:bg-amber-50 border border-amber-200">
            📋 Log Viewer
        </a>
    @endrole
@endauth
