# Implementation Plan - Fix logo 404 e navbar mobile

## 1. Problemi
1. **Logo 404 in produzione.** Il logo (header, login, registrazione) e il `<link rel="icon">` puntano direttamente a `asset('icon.svg')`. In `docker/nginx/default.conf` la location `= /icon.svg` usa `try_files /public/icon.svg =404;` con `root /var/www/html/public`: il percorso risolto è `/var/www/html/public/public/icon.svg`, che non esiste -> 404.
2. **Navbar assente su telefono in verticale.** In `layouts/app.blade.php` la `<nav>` ha `hidden md:flex` e non esiste alcun pulsante/menu alternativo sotto `md` (768px): i link (Calendario, Gruppi, Stanze, Import, Scambi, Utenti, Log Viewer) non sono raggiungibili.

## 2. Soluzione

### 2.1 Icone dai file generati dal build (come `unlimiteddb`)
- `layouts/favicons.blade.php`: rimuovere il `<link rel="icon" type="image/svg+xml" href="icon.svg">`; restano solo le icone PNG/ICO/manifest in `build/icons/*` (generate da `docker/icons/generate-icons.sh`). Aggiornare il commento.
- Logo in `layouts/app.blade.php`, `auth/login.blade.php`, `auth/register.blade.php`: `asset('build/icons/android-chrome-192x192.png')` (192px: nitido anche su schermi retina alle dimensioni 40/56px) al posto di `asset('icon.svg')`.
- `docker/nginx/default.conf`: rimuovere la location `= /icon.svg` rotta (l'SVG non è più referenziato; la location generica `/` lo serve comunque da `public/` se richiesto).
- `public/icon.svg` resta il **sorgente** da cui lo stage `icon-builder` genera le icone (invariato).

### 2.2 Menu a comparsa su mobile
- Estrarre i link in `layouts/partials/nav-links.blade.php` (parametro `$mobile`) per non duplicare i `@can`/`@role`: desktop = inline come oggi, mobile = voci a tutta larghezza.
- Header: pulsante hamburger `#nav-toggle` (visibile solo sotto `md`, `aria-expanded`, `aria-controls="mobile-menu"`), pannello `#mobile-menu` (`md:hidden`, `hidden` di default) sotto l'header con i link.
- Sotto `sm` l'header è troppo stretto per nome utente / Esci / Registrati: nell'header restano tema + hamburger (+ "Accedi" per gli ospiti); nome, ruolo ed **Esci** (o Accedi/Registrati) vanno in fondo al pannello mobile (`sm:hidden`). Da `sm` in su l'header resta come oggi.
- JS: `resources/js/nav.js` (importato da `app.js`): toggle apertura/chiusura, `aria-expanded`, chiusura con `Esc`, al click su un link e al ridimensionamento oltre `md`.
- Voce attiva evidenziata come nella navbar desktop; supporto tema scuro (usa già le scale rimappate).

### 2.3 Test
- `tests/Feature/NavigationTest.php`: ospite e utente vedono `id="nav-toggle"` e `id="mobile-menu"`; i link riservati compaiono nel menu solo ai ruoli abilitati; il logo punta a `build/icons/android-chrome-192x192.png` e non a `icon.svg` (in header, login, registrazione); `favicons` non contiene `icon.svg`.
- Aggiornare eventuali test esistenti che cercano `icon.svg`.

### 2.4 Documentazione
- `README.md`: sezione Icone (rimuovere "l'SVG è linkato direttamente", spiegare che logo e favicon usano i file generati in `build/icons` e che in locale senza Docker serve `public/build/icons`), sezione UI/navbar mobile, elenco test.
- `todo.md`: nuova fase.

## 3. Verifica
- `php artisan test`.
- Manuale: `/build/icons/android-chrome-192x192.png` risponde 200 e il logo si vede; viewport 375px: hamburger visibile, menu si apre/chiude, link funzionanti, nessun overflow orizzontale dell'header; tema scuro ok.

## 4. Chiusura
Rinominare in `implementationPlan-V-fixLogoENavbarMobile.md`.
