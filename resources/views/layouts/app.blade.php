<!DOCTYPE html>
<html lang="it" class="h-full bg-slate-900 text-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Calendario Cavalcalupi' }} - Associazione Cavalcalupi</title>

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
        .glass-panel {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
    </style>
</head>
<body class="min-h-full flex flex-col bg-slate-950 selection:bg-indigo-500 selection:text-white">
    <!-- Top Navigation Bar -->
    <header class="sticky top-0 z-40 glass-panel border-b border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Brand / Logo -->
                <div class="flex items-center gap-3">
                    <a href="{{ route('calendar.index') }}" class="flex items-center gap-3 group">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center shadow-lg shadow-indigo-500/20 group-hover:scale-105 transition-transform">
                            <span class="text-xl">🐺</span>
                        </div>
                        <div>
                            <span class="text-lg font-bold bg-clip-text text-transparent bg-gradient-to-r from-white via-slate-200 to-indigo-300">
                                Cavalcalupi
                            </span>
                            <span class="hidden sm:inline-block ml-1.5 px-2 py-0.5 text-xs font-medium rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                                Calendario
                            </span>
                        </div>
                    </a>
                </div>

                <!-- Navigation Links -->
                <nav class="hidden md:flex items-center gap-1 sm:gap-2">
                    <a href="{{ route('calendar.index') }}" 
                       class="px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('calendar.*') ? 'bg-indigo-600/20 text-indigo-300 border border-indigo-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' }}">
                        📅 Calendario
                    </a>

                    @auth
                        @can('manage-groups')
                            <a href="{{ route('admin.groups.index') }}" 
                               class="px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('admin.groups.*') ? 'bg-indigo-600/20 text-indigo-300 border border-indigo-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' }}">
                                👥 Gestione Gruppi
                            </a>
                        @endcan

                        @role('ADMIN')
                            <a href="/log-viewer" target="_blank"
                               class="px-3 py-2 text-sm font-medium rounded-lg text-amber-400 hover:text-amber-300 hover:bg-amber-500/10 border border-amber-500/20 transition-colors">
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
                                <div class="text-xs font-semibold text-white leading-tight">{{ Auth::user()->name }}</div>
                                <div class="text-[10px] text-slate-400 uppercase tracking-wider">
                                    @if(Auth::user()->hasRole('ADMIN'))
                                        <span class="text-red-400 font-bold">Admin</span>
                                    @elseif(Auth::user()->hasRole('CD'))
                                        <span class="text-amber-400 font-bold">CD</span>
                                    @else
                                        <span class="text-emerald-400">Membro</span>
                                    @endif
                                </div>
                            </div>

                            <form method="POST" action="{{ route('logout') }}" class="inline">
                                @csrf
                                <button type="submit" 
                                        class="px-3 py-1.5 text-xs font-medium rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700 transition-colors">
                                    Esci
                                </button>
                            </form>
                        </div>
                    @else
                        <div class="flex items-center gap-2">
                            <a href="{{ route('login') }}" 
                               class="px-3.5 py-1.5 text-xs font-medium rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white shadow-md shadow-indigo-600/30 transition-all">
                                Accedi
                            </a>
                            <a href="{{ route('register') }}" 
                               class="px-3 py-1.5 text-xs font-medium rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 transition-colors">
                                Registrati
                            </a>
                        </div>
                    @endauth
                </div>
            </div>
        </div>
    </header>

    <!-- Flash Messages -->
    @if(session('success'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 w-full">
            <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm flex items-center justify-between shadow-lg">
                <div class="flex items-center gap-2">
                    <span>✅</span>
                    <span>{{ session('success') }}</span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-300">&times;</button>
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 w-full">
            <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-sm shadow-lg">
                <div class="font-semibold mb-1 flex items-center gap-2">
                    <span>⚠️</span> Attenzione:
                </div>
                <ul class="list-disc list-inside space-y-0.5 text-xs text-rose-400">
                    @foreach($errors->all() as $error)
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
    <footer class="border-t border-slate-800 py-6 text-center text-xs text-slate-500">
        <p>Associazione Culturale e Ludica Cavalcalupi &bull; Sistema Calendario & Feed ICS &bull; {{ date('Y') }}</p>
    </footer>

    <!-- Bladewind UI Scripts -->
    <script src="{{ asset('vendor/bladewind/js/helpers.js') }}"></script>
    <x-bladewind::notification />

    @stack('scripts')
</body>
</html>
