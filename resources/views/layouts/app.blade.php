<!DOCTYPE html>
<html lang="it" class="h-full bg-white text-gray-900">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#15803d">

    <title>{{ $title ?? 'Calendario Cavalcalupi' }} - Associazione Cavalcalupi</title>

    <!-- Icone del sito (generate da public/icon.svg, vedi docker/icons/generate-icons.sh) -->
    @include('layouts.favicons')

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bladewind UI Assets -->
    <link href="{{ asset('vendor/bladewind/css/animate.min.css') }}" rel="stylesheet" />
    <link href="{{ asset('vendor/bladewind/css/bladewind-ui.min.css') }}" rel="stylesheet" />

    <!-- Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Outfit', sans-serif;
        }
    </style>
</head>

<body class="min-h-full flex flex-col bg-white text-gray-900 selection:bg-brand-600 selection:text-white">
    <!-- Top Navigation Bar -->
    <header class="sticky top-0 z-40 bg-white/90 backdrop-blur border-b border-brand-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Brand / Logo -->
                <div class="flex items-center gap-3">
                    <a href="{{ route('calendar.index') }}" class="flex items-center gap-3 group">
                        <img src="{{ asset('icon.svg') }}" alt="Cavalcalupi" width="40" height="40"
                            class="w-10 h-10 rounded-xl shadow-md shadow-brand-600/20 group-hover:scale-105 transition-transform">
                        <div>
                            <span class="text-lg font-bold text-brand-800">
                                Cavalcalupi
                            </span>
                            <span
                                class="hidden sm:inline-block ml-1.5 px-2 py-0.5 text-xs font-medium rounded-full bg-brand-50 text-brand-700 border border-brand-200">
                                Calendario
                            </span>
                        </div>
                    </a>
                </div>

                <!-- Navigation Links -->
                <nav class="hidden md:flex items-center gap-1 sm:gap-2">
                    <a href="{{ route('calendar.index') }}"
                        class="px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('calendar.*') ? 'bg-brand-100 text-brand-800 border border-brand-200' : 'text-gray-600 hover:text-brand-800 hover:bg-brand-50' }}">
                        📅 Calendario
                    </a>

                    @auth
                        @can('manage-groups')
                            <a href="{{ route('admin.groups.index') }}"
                                class="px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('admin.groups.*') ? 'bg-brand-100 text-brand-800 border border-brand-200' : 'text-gray-600 hover:text-brand-800 hover:bg-brand-50' }}">
                                👥 Gestione Gruppi
                            </a>
                        @endcan

                        @role('ADMIN')
                            <a href="/log-viewer" target="_blank"
                                class="px-3 py-2 text-sm font-medium rounded-lg text-amber-700 hover:text-amber-800 hover:bg-amber-50 border border-amber-200 transition-colors">
                                📋 Log Viewer
                            </a>
                        @endrole
                    @endauth
                </nav>

                <!-- User actions -->
                <div class="flex items-center gap-3">
                    @auth
                        <div class="flex items-center gap-2">
                            <div class="text-right hidden sm:block">
                                <div class="text-xs font-semibold text-gray-900 leading-tight">{{ Auth::user()->name }}
                                </div>
                                <div class="text-[10px] text-gray-500 uppercase tracking-wider">
                                    @if (Auth::user()->hasRole('ADMIN'))
                                        <span class="text-red-600 font-bold">Admin</span>
                                    @elseif(Auth::user()->hasRole('CD'))
                                        <span class="text-amber-600 font-bold">CD</span>
                                    @else
                                        <span class="text-brand-700 font-semibold">Membro</span>
                                    @endif
                                </div>
                            </div>

                            <form method="POST" action="{{ route('logout') }}" class="inline">
                                @csrf
                                <button type="submit"
                                    class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white hover:bg-brand-50 text-gray-700 hover:text-brand-800 border border-gray-300 transition-colors">
                                    Esci
                                </button>
                            </form>
                        </div>
                    @else
                        <div class="flex items-center gap-2">
                            <a href="{{ route('login') }}"
                                class="px-3.5 py-1.5 text-xs font-medium rounded-lg bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-600/20 transition-all">
                                Accedi
                            </a>
                            <a href="{{ route('register') }}"
                                class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white hover:bg-brand-50 text-gray-700 border border-gray-300 transition-colors">
                                Registrati
                            </a>
                        </div>
                    @endauth
                </div>
            </div>
        </div>
    </header>

    <!-- Flash Messages -->
    @if (session('success'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 w-full">
            <div
                class="p-4 rounded-xl bg-brand-50 border border-brand-200 text-brand-800 text-sm flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-2">
                    <span>✅</span>
                    <span>{{ session('success') }}</span>
                </div>
                <button onclick="this.parentElement.remove()"
                    class="text-brand-600 hover:text-brand-800">&times;</button>
            </div>
        </div>
    @endif

    @if ($errors->any())
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 w-full">
            <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm shadow-sm">
                <div class="font-semibold mb-1 flex items-center gap-2">
                    <span>⚠️</span> Attenzione:
                </div>
                <ul class="list-disc list-inside space-y-0.5 text-xs text-rose-600">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <!-- Main Content Area -->
    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 w-full">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-brand-800 py-6 text-center text-xs text-brand-100">
        <p>Associazione Cavalcalupi &bull; Sistema Calendario & Feed ICS &bull; {{ date('Y') }}</p>
    </footer>

    <!-- Bladewind UI Scripts -->
    <script src="{{ asset('vendor/bladewind/js/helpers.js') }}"></script>
    <x-bladewind::notification />

    @stack('scripts')
</body>

</html>
