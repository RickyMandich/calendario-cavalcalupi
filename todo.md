# TODO List - Calendario Cavalcalupi

- [x] **Fase 1: Setup Progetto & Pacchetti**
  - [x] Installare `spatie/laravel-permission` e pubblicare le migrazioni.
  - [x] Installare `opcodesio/log-viewer:^3.24`.
  - [x] Installare `spatie/icalendar-generator`.
  - [x] Installare e configurare BladewindUI e Tailwind CSS.

- [x] **Fase 2: Migrazioni & Database**
  - [x] Modificare la migrazione `users` aggiungendo la colonna `ics_token`.
  - [x] Creare la migrazione per `groups` (`id`, `name`, `is_active`, `timestamps`).
  - [x] Creare la migrazione pivot `group_user`.
  - [x] Creare la migrazione per `events` (`title`, `description`, `start_time`, `end_time`, `type`, `group_id`, `created_by`, `timestamps`).
  - [x] Creare la migrazione per `event_logs` (`event_id`, `user_id`, `action`, `old_data`, `new_data`, `timestamps`).
  - [x] Creare Seeder per Ruoli Spatie (`ADMIN`, `CD`, `Membro`).

- [x] **Fase 3: Modelli & Logica di Business**
  - [x] Configurare il modello `User` (relazioni, ruoli Spatie, generazione automatica di `ics_token` all'evento `creating`).
  - [x] Configurare il modello `Group` e gli scope per i gruppi attivi.
  - [x] Configurare il modello `Event` includendo la validazione (Sessione deve avere `group_id` attivo, altri eventi no).
  - [x] Configurare il modello `EventLog`.
  - [x] Implementare un Observer o Listener Eloquent per salvare in `event_logs` le modifiche alle sessioni.

- [x] **Fase 4: Scheduler & Disattivazione Gruppi**
  - [x] Creare il comando Artisan `app:deactivate-groups`.
  - [x] Configurare lo scheduler in `routes/console.php` per l'esecuzione il 15 agosto alle 00:00 (timezone Europe/Rome).

- [x] **Fase 5: Autenticazione & Permessi**
  - [x] Definire Policy e Gate per il controllo accessi (gestione gruppi per CD/ADMIN, modifica sessioni solo ai membri del gruppo o CD/ADMIN).
  - [x] Proteggere la rotta `/log-viewer` per il solo ruolo `ADMIN`.

- [x] **Fase 6: Feed ICS Personalizzato**
  - [x] Creare la rotta ed il controller `IcsCalendarController`.
  - [x] Implementare la logica di generazione `.ics` filtrata per utente (sessioni dei propri gruppi attivi + assemblee/eventi).

- [x] **Fase 7: Frontend & Interfaccia Utente (BladewindUI)**
  - [x] Creare la vista del Calendario principale con toggle per il filtro (Tutti gli eventi / Solo i miei gruppi).
  - [x] Inserire la modale/pulsante per ottenere e copiare il link ICS personale.
  - [x] Creare l'interfaccia di gestione Gruppi e Assegnazione Utenti (riservata a CD e ADMIN).
  - [x] Creare le form di creazione/modifica eventi e sessioni.
  - [x] Creare il componente per visualizzare lo storico/log delle modifiche di una sessione.

- [x] **Fase 8: Palette verde e bianco**
  - [x] Definire la scala `brand` (verde) in `@theme` e i componenti `.panel`, `.field`, `.field-label`, `.btn-primary`, `.btn-secondary` in `resources/css/app.css`.
  - [x] Migrare `layouts/app.blade.php` al tema chiaro verde/bianco (header bianco, footer verde, `theme-color`).
  - [x] Migrare le viste `calendar/index`, `events/*`, `admin/groups/index`, `auth/*` alla nuova palette.
  - [x] Documentare palette e componenti nel README.

- [x] **Fase 9: Icone del sito generate da SVG (Docker)**
  - [x] Creare l'SVG sorgente `public/icon.svg`.
  - [x] Creare `docker/icons/generate-icons.sh` (favicon, apple-touch, android-chrome, `site.webmanifest`).
  - [x] Aggiungere lo stage `icon-builder` al `Dockerfile` e consegnare le icone in `/opt/build-seed/icons` (-> `/build/icons/*`).
  - [x] Servire `/favicon.ico` da nginx con la favicon generata.
  - [x] Creare `layouts/favicons.blade.php` e includerlo in `layouts/app.blade.php`; usare `icon.svg` come logo.
  - [x] Documentare le icone nel README.
  - [ ] Verificare dopo il primo build Docker: `/build/icons/favicon.ico`, `/build/icons/site.webmanifest` e `/icon.svg` rispondono 200.

- [x] **Fase 10: Allineamento Docker Compose a unlimiteddb**
  - [x] Allineare `docker-compose.yml` (immagine `calendario-cavalcalupi:prod` condivisa da app e worker, asset su `public/build` in nginx).
  - [x] Creare `docker-compose.dev.yml` e `docker/mysql/init.dev.sql` (subnet `172.25.0.0/24`, nginx su 8000, DB su 3307).
  - [x] Allineare `Dockerfile` e `docker/entrypoint.sh` (`/opt/build-assets`, copia anche in `public/build`).
  - [x] Allineare `docker/nginx/default.conf` (rimossa location `/build/`, favicon con `try_files`).
  - [x] Aggiungere `schedule:work` a `docker/supervisor/worker.conf` (necessario per `app:deactivate-groups`).
  - [x] Aggiornare `.dockerignore` (`public/build`, `public/hot`) e README (sezione Docker).
  - [ ] Provare `docker compose -f docker-compose.dev.yml up -d --build` e verificare `http://localhost:8000`.

- [ ] **Fase 11: Feed ICS non sincronizzato su Google Calendar**
  - [x] Causa individuata: il link ICS nel modale era `http://` (Laravel dietro Traefik/Cloudflare senza proxy fidati).
  - [x] Dichiarare `trustProxies(at: '*')` in `bootstrap/app.php` e documentarlo nel README.
  - [ ] Dopo il deploy: verificare che il link nel modale inizi con `https://`, rimuovere il calendario da Google Calendar e riaggiungerlo.
  - [ ] Se ancora vuoto: controllare i log nginx (`grep calendar/ics`) e le regole Cloudflare (Bot Fight Mode / WAF) su `/calendar/ics/*`.

- [ ] **Fase 12: Orari del feed ICS sfasati di 2 ore su Google Calendar**
  - [x] `IcsCalendarController`: gli orari vengono esportati con fuso esplicito `Europe/Rome` (`shiftTimezone`), indipendente dal fuso con cui Carbon li legge.
  - [x] Test `test_feed_exports_times_in_rome_timezone_regardless_of_php_default_timezone`.
  - [ ] Dopo il deploy: verificare su Google Calendar (anche per le sessioni già create) e controllare `config('app.timezone')` sul server.