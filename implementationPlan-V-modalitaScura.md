# Implementation Plan - Modalità scura (Fase 19)

**Riferimento:** `analisi-finale-modifiche.md` sez. 11.
**Dipendenze:** nessuna tecnica (può andare in parallelo ai piani 16-18), ma va fatta **prima di `calendarioVisuale`**. Conviene farla dopo che le viste nuove (Utenti, Stanze, Scambi) esistono, per rivederle tutte insieme.

## 1. Obiettivo
Tema scuro con la stessa identità **verde e bianco** del chiaro, colori principali **invertiti** (sfondi verde scuro, testi chiari). Preferenza `chiaro / scuro / sistema` (default `sistema`), salvata nel browser, con pulsante nell'header (visibile anche in login/registrazione) e nessun "lampo" bianco al caricamento.

## 2. Stato attuale (da codice)
- `resources/css/app.css` (Tailwind v4): scala `brand-50…950` in `@theme`; componenti `.panel .field-label .field .btn-primary .btn-secondary` con `@apply`.
- `layouts/app.blade.php`: `<html class="h-full bg-white text-gray-900">`, `<meta name="theme-color" content="#15803d">`, header `bg-white/90`, footer `bg-brand-800 text-brand-100`, flash `bg-brand-50`/`bg-rose-50`; carica Bladewind (CSS + `helpers.js` + `<x-bladewind::notification />`).
- Viste con utility fisse: `white`, `gray-*`, `brand-*`, `rose/amber/sky/red-*`; due modali con overlay `bg-brand-950/50` (`calendar/index`, `admin/groups/index`).
- `resources/js/app.js` importa solo `./bootstrap`.

## 3. Strategia: rimappare le scale sotto `.dark`
Invece di aggiungere `dark:` a ogni elemento di ogni vista, in modalità scura si **ridefiniscono le variabili CSS delle scale di colore** che Tailwind v4 usa nelle utility (`bg-brand-50` → `var(--color-brand-50)`). Le viste esistenti si "invertono" da sole; si correggono a mano solo le eccezioni elencate in 4.4.

Mappatura (specchio della scala): `50↔950, 100↔900, 200↔800, 300↔700, 400↔600, 500` invariato. Conseguenze volute: `bg-brand-600` (pulsante primario) diventa il verde chiaro `#4ade80`; `text-white` sui pulsanti diventa scuro, quindi resta leggibile; `text-brand-950` (titoli) diventa quasi bianco.

**Verifica preliminare (passo 1):** dopo `npm run build`, aprire `public/build/assets/app-*.css` e controllare che le utility siano compilate come `background-color: var(--color-brand-600)` e non come valore letterale. Se Tailwind inlinasse i colori, ripiegare su `@theme static` oppure sulla variante esplicita `dark:` (vedi sez. 8).

## 4. Passi

### 4.1 CSS (`resources/css/app.css`)
1. Dopo `@import 'tailwindcss';` aggiungere `@custom-variant dark (&:where(.dark, .dark *));` e `:root { color-scheme: light; } .dark { color-scheme: dark; }`.
2. Variabili semantiche per ciò che non è una scala:
   ```css
   :root { --surface: #ffffff; --surface-raised: #ffffff; --footer-bg: #166534; --footer-fg: #dcfce7; --overlay: rgb(5 46 22 / 0.5); }
   .dark { --surface: #052e16; --surface-raised: #0a3b1f; --footer-bg: #031c0d; --footer-fg: #86efac; --overlay: rgb(0 0 0 / 0.6); }
   ```
3. Blocco `.dark { ... }` con le scale rimappate (valori esadecimali: scala Tailwind classica, accettabili anche con Tailwind v4):
   - `--color-white: var(--surface);` (sfondo pagina e `bg-white`)
   - **brand**: `50 #052e16, 100 #14532d, 200 #166534, 300 #15803d, 400 #16a34a, 500 #22c55e, 600 #4ade80, 700 #86efac, 800 #bbf7d0, 900 #dcfce7, 950 #f0fdf4`
   - **gray**: `50 #030712, 100 #111827, 200 #1f2937, 300 #374151, 400 #4b5563, 500 #6b7280, 600 #9ca3af, 700 #d1d5db, 800 #e5e7eb, 900 #f3f4f6, 950 #f9fafb`
   - **rose** (badge assemblea/errori): `50 #4c0519, 100 #881337, 200 #9f1239, 600 #fb7185, 700 #fda4af, 800 #fecdd3`
   - **amber** (evento associativo/CD): `50 #451a03, 100 #78350f, 200 #92400e, 600 #fbbf24, 700 #fcd34d, 800 #fde68a`
   - **sky** (badge "Riprogrammata"): `50 #082f49, 100 #0c4a6e, 200 #075985, 600 #38bdf8, 700 #7dd3fc, 800 #bae6fd`
   - **red** (ruolo Admin, pulsanti demo): `50 #450a0a, 100 #7f1d1d, 200 #991b1b, 600 #f87171, 700 #fca5a5, 800 #fecaca`
   Se in fase di prova emergono altre famiglie/sfumature usate dalle viste, aggiungerle con lo stesso criterio (specchio).
4. Componenti (`@layer components`): `.panel` → `background-color: var(--surface-raised)` al posto di `bg-white` (bordo `brand-100` si inverte da solo); aggiungere `.site-footer { background-color: var(--footer-bg); color: var(--footer-fg); }` e `.modal-overlay { background-color: var(--overlay); }`. `.field`: lo sfondo `bg-white` diventa `var(--surface)` in dark (si inverte da solo con `--color-white`); verificare il colore del placeholder (`placeholder-gray-400` → in dark resta leggibile) e il bordo (`border-gray-300` → `#374151`). `.btn-primary`/`.btn-secondary` non richiedono modifiche grazie all'inversione; verificare `hover`.

### 4.2 Script anti-lampo e toggle
5. `layouts/app.blade.php`, nel `<head>` **prima dei CSS**:
   ```html
   <meta name="color-scheme" content="light dark">
   <script>
     (function () {
       try {
         var t = localStorage.getItem('theme') || 'system';
         var dark = t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
         document.documentElement.classList.toggle('dark', dark);
       } catch (e) {}
     })();
   </script>
   ```
6. `resources/js/theme.js` (importato da `app.js`): `getTheme()`, `applyTheme(mode)` (classe `dark`, `localStorage`, aggiorna `<meta name="theme-color">` a `#15803d` / `#052e16`, aggiorna icona e `aria-label`/`title` del pulsante), `cycleTheme()` (sistema → chiaro → scuro), listener su `matchMedia('(prefers-color-scheme: dark)')` attivo solo in modalità `system`. Tutto in `try/catch` per `localStorage` non disponibile.
7. Header: `<button type="button" id="theme-toggle" ...>` (icona 🖥️ / ☀️ / 🌙, `title="Tema: sistema/chiaro/scuro"`) accanto ai pulsanti utente; visibile per ospiti e autenticati (anche nel menu mobile se presente). Stile con le classi esistenti (`.btn-secondary` compatto).

### 4.3 Dettagli di layout
8. `<html class="h-full bg-white text-gray-900">` e `<body ...>` restano: `bg-white`/`text-gray-900` si rimappano. Footer: sostituire `bg-brand-800 ... text-brand-100` con la classe `site-footer`.

### 4.4 Eccezioni da correggere nelle viste esistenti e nuove
9. Sostituire `bg-brand-950/50` con `modal-overlay` in `calendar/index.blade.php` (modale ICS) e `admin/groups/index.blade.php` (modale nuovo gruppo) e in ogni modale creata dai piani successivi.
10. Controllare (e se serve correggere con `dark:`): ombre colorate `shadow-brand-600/20`; blob decorativo `bg-brand-200/50` del banner; gradiente `from-white to-brand-50`; badge con `border-*-200`; testo `text-white` su pulsanti rosa/ambra (se presenti); l'etichetta ruoli nel layout (`text-red-600`, `text-amber-600`); input `type="date"`/`datetime-local` (icona del calendario nativa: `color-scheme: dark` la rende chiara); checkbox con `text-brand-600`.
11. Bladewind: `<x-bladewind::notification />` è l'unico componente usato; verificare il suo aspetto in scuro e, se necessario, sovrascriverne le classi in `app.css` sotto `.dark`.
12. Il logo `icon.svg` e le favicon non cambiano (verde pieno, leggibile su entrambi i temi).

## 5. Test e verifica
**Automatici** (`tests/Feature/ThemeToggleTest.php`):
- ospite e utente autenticato: `GET /calendar` e `GET /login` contengono `id="theme-toggle"` e lo script con `localStorage.getItem('theme')`;
- `GET /login`, `GET /register` rispondono 200 con il toggle;
- nessuna regressione di `CalendarAndWebRoutesTest`.
- `npm run build` termina senza errori (anche nello stage del `Dockerfile`).

**Manuali (checklist da annotare nel piano a fine lavoro):**
- Pagine nei due temi: calendario (banner, filtri, card), dettaglio evento + storico log, modifica/creazione, gruppi + modali, utenti, stanze, scambi, login, registrazione, messaggi flash e errori, paginazione.
- Contrasto testo/sfondo ≥ WCAG AA (Lighthouse/axe sulle pagine principali nei due temi).
- Persistenza: scelta mantenuta dopo ricarica e dopo il logout/login; in modalità `sistema` il cambio del tema del sistema operativo si riflette senza ricaricare.
- Nessun lampo bianco con ricarica forzata (Ctrl+F5) in tema scuro.
- Emulazione `prefers-color-scheme: dark` nei DevTools con `localStorage` vuoto → scuro.
- Smartphone (viewport stretto): il pulsante è raggiungibile.

## 6. Documentazione
- `README.md`: sezione "Palette e Componenti UI": aggiungere la colonna "Scuro" alla tabella, spiegare la rimappatura sotto `.dark`, il toggle a tre stati e come ritoccare i colori (un solo blocco `.dark` in `app.css`); aggiungere il nuovo test all'elenco.
- `todo.md`: Fase 19.

## 7. Criteri di accettazione
- Tutte le pagine sono leggibili e coerenti (verde scuro + testi chiari) in scuro e invariate in chiaro.
- Il pulsante cicla sistema/chiaro/scuro e la scelta persiste; nessun lampo; `theme-color` coerente.
- Nessuna modifica a `vendor/` né ai file di Bladewind pubblicati in `public/vendor`.
- Test verdi, build ok.

## 8. Rischi e alternativa
- Se la rimappatura delle variabili non funzionasse con la versione di Tailwind installata (passo di verifica 1), l'alternativa è aggiungere `dark:` esplicito nei componenti (`.panel`, `.field`, `.btn-*`) e nelle viste (più lungo ma equivalente), mantenendo comunque il toggle, lo script e le variabili semantiche.
- I colori semantici dei tipi evento (rose/amber) devono restare distinguibili dal verde anche in scuro: controllare nel calendario e nella legenda.

## 9. Chiusura
Rinominare in `implementationPlan-V-modalitaScura.md`.
