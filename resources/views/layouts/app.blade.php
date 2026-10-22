<!DOCTYPE html>
<html lang="it" class="h-full bg-white text-gray-900">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#15803d">
    <meta name="color-scheme" content="light dark">
    <!-- Anti-lampo: applica il tema prima del rendering (preferenza: chiaro / scuro / sistema) -->
    <script>
        (function () {
            try {
                var t = localStorage.getItem('theme') || 'system';
                var dark = t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
            } catch (e) {}
        })();
    </script>

    <title>{{ $title ?? 'Calendario Cavalcalupi' }} - Associazione Cavalcalupi</title>

    {{-- Icone del sito: file generati nel build (vedi docker/icons/generate-icons.sh) --}}
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
                <div class="flex items-center gap-3 min-w-0">
                    <a href="{{ route('calendar.index') }}" class="flex items-center gap-3 group">
                        <img src="{{ asset('build/icons/android-chrome-192x192.png') }}" alt="Cavalcalupi" width="40" height="40"
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

                <!-- Navigation Links (da md in su; sotto md c'è il menu a comparsa #mobile-menu) -->
                <nav class="hidden md:flex items-center gap-1 sm:gap-2" aria-label="Menu principale">
                    @include('layouts.partials.nav-links', ['mobile' => false])
                </nav>

                <!-- User actions -->
                <div class="flex items-center gap-3">
                    <button type="button" id="theme-toggle" class="btn-secondary px-2.5 py-1.5" title="Tema: sistema" aria-label="Cambia tema">🖥️</button>
                    @auth
                        <div class="hidden sm:flex items-center gap-2">
                            <div class="text-right">
                                <div class="text-xs font-semibold text-gray-900 leading-tight"><a href="{{ route('profile.edit') }}" class="hover:text-brand-700 hover:underline" title="Il mio profilo">{{ Auth::user()->name }}</a>
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
                                class="hidden sm:inline-block px-3 py-1.5 text-xs font-medium rounded-lg bg-white hover:bg-brand-50 text-gray-700 border border-gray-300 transition-colors">
                                Registrati
                            </a>
                        </div>
                    @endauth

                    <!-- Pulsante del menu a comparsa (solo sotto md) -->
                    <button type="button" id="nav-toggle"
                        class="md:hidden btn-secondary px-2.5 py-1.5"
                        aria-controls="mobile-menu" aria-expanded="false" aria-label="Apri il menu">
                        <svg data-nav-icon="open" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="2" stroke="currentColor" class="w-5 h-5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                        </svg>
                        <svg data-nav-icon="close" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="2" stroke="currentColor" class="w-5 h-5 hidden" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Menu a comparsa (sotto md) -->
        <div id="mobile-menu" class="hidden md:hidden border-t border-brand-100 max-h-[calc(100vh-4rem)] overflow-y-auto">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3">
                <nav class="flex flex-col gap-1" aria-label="Menu principale (mobile)">
                    @include('layouts.partials.nav-links', ['mobile' => true])
                </nav>

                <!-- Account: nell'header manca lo spazio sotto sm, quindi sta qui -->
                <div class="sm:hidden mt-3 pt-3 border-t border-brand-100">
                    @auth
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-gray-900 truncate"><a href="{{ route('profile.edit') }}" class="hover:text-brand-700 hover:underline" title="Il mio profilo">{{ Auth::user()->name }}</a></div>
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
                            <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                                @csrf
                                <button type="submit"
                                    class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white hover:bg-brand-50 text-gray-700 hover:text-brand-800 border border-gray-300 transition-colors">
                                    Esci
                                </button>
                            </form>
                        </div>
                    @else
                        <a href="{{ route('register') }}"
                            class="block w-full text-center px-3 py-2 text-sm font-medium rounded-lg bg-white hover:bg-brand-50 text-gray-700 border border-gray-300 transition-colors">
                            Registrati
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </header>

    <!-- Avviso email non verificata -->
    @auth
        @unless (Auth::user()->hasVerifiedEmail())
            <div id="verify-email-banner" class="bg-amber-50 border-b border-amber-200">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs text-amber-800">
                    <span>✉️ Verifica il tuo indirizzo email: finché non lo fai non puoi essere assegnato a un gruppo né usare il feed ICS.</span>
                    <form method="POST" action="{{ route('verification.send') }}" class="shrink-0">
                        @csrf
                        <button type="submit" class="px-3 py-1 font-semibold rounded-lg border border-amber-300 bg-white hover:bg-amber-100 text-amber-800 transition-colors">
                            Reinvia email di verifica
                        </button>
                    </form>
                </div>
            </div>
        @endunless
    @endauth

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
    <footer class="site-footer py-6 text-center text-xs">
        <p>Associazione Cavalcalupi &bull; Sistema Calendario & Feed ICS &bull; {{ date('Y') }} &bull; Versione {{ config('app.version') }}</p>
    </footer>

    <!-- Bladewind UI Scripts -->
    <script src="{{ asset('vendor/bladewind/js/helpers.js') }}"></script>
    <x-bladewind::notification />

    @stack('scripts')
</body>

</html>
