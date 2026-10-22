{{-- Icone del sito: file generati dal Dockerfile (stage icon-builder) a partire da public/icon.svg,
     consegnati in public/build/icons (serviti da nginx su /build/icons/*). Vedi docker/icons/generate-icons.sh.
     Non si referenzia direttamente l'SVG sorgente: si usano sempre i file generati dal build. --}}
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('build/icons/favicon-32x32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('build/icons/favicon-16x16.png') }}">
<link rel="shortcut icon" href="{{ asset('build/icons/favicon.ico') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('build/icons/apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('build/icons/site.webmanifest') }}">
