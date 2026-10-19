{{-- Icone del sito: generate dal Dockerfile (stage icon-builder) a partire da public/icon.svg,
     consegnate in public/build/icons. Vedi docker/icons/generate-icons.sh.
     L'SVG è il primo della lista: i browser moderni lo preferiscono e funziona anche in
     sviluppo locale senza Docker (dove build/icons non esiste). --}}
<link rel="icon" type="image/svg+xml" href="{{ asset('icon.svg') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('build/icons/favicon-32x32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('build/icons/favicon-16x16.png') }}">
<link rel="shortcut icon" href="{{ asset('build/icons/favicon.ico') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('build/icons/apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('build/icons/site.webmanifest') }}">
