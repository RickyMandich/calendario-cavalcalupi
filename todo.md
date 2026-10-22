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
  - [x] Verificare dopo il primo build Docker: `/build/icons/favicon.ico`, `/build/icons/site.webmanifest` e `/icon.svg` rispondono 200.

- [x] **Fase 10: Allineamento Docker Compose a unlimiteddb**
  - [x] Allineare `docker-compose.yml` (immagine `calendario-cavalcalupi:prod` condivisa da app e worker, asset su `public/build` in nginx).
  - [x] Creare `docker-compose.dev.yml` e `docker/mysql/init.dev.sql` (subnet `172.25.0.0/24`, nginx su 8000, DB su 3307).
  - [x] Allineare `Dockerfile` e `docker/entrypoint.sh` (`/opt/build-assets`, copia anche in `public/build`).
  - [x] Allineare `docker/nginx/default.conf` (rimossa location `/build/`, favicon con `try_files`).
  - [x] Aggiungere `schedule:work` a `docker/supervisor/worker.conf` (necessario per `app:deactivate-groups`).
  - [x] Aggiornare `.dockerignore` (`public/build`, `public/hot`) e README (sezione Docker).
  - [x] Provare `docker compose -f docker-compose.dev.yml up -d --build` e verificare `http://localhost:8000`.

- [x] **Fase 11: Feed ICS non sincronizzato su Google Calendar**
  - [x] Causa individuata: il link ICS nel modale era `http://` (Laravel dietro Traefik/Cloudflare senza proxy fidati).
  - [x] Dichiarare `trustProxies(at: '*')` in `bootstrap/app.php` e documentarlo nel README.
  - [x] Dopo il deploy: verificare che il link nel modale inizi con `https://`, rimuovere il calendario da Google Calendar e riaggiungerlo.
  - [x] Se ancora vuoto: controllare i log nginx (`grep calendar/ics`) e le regole Cloudflare (Bot Fight Mode / WAF) su `/calendar/ics/*`.

- [x] **Fase 12: Orari del feed ICS sfasati di 2 ore su Google Calendar**
  - [x] `IcsCalendarController`: gli orari vengono esportati con fuso esplicito `Europe/Rome` (`shiftTimezone`), indipendente dal fuso con cui Carbon li legge.
  - [x] Test `test_feed_exports_times_in_rome_timezone_regardless_of_php_default_timezone`.
  - [x] Dopo il deploy: verificare su Google Calendar (anche per le sessioni già create) e controllare `config('app.timezone')` sul server.

> Piani di implementazione per le modifiche di `analisi-finale-modifiche.md` (ordine consigliato). Ogni piano, a lavoro finito, va rinominato `implementationPlan-V-*.md`.
> Le fasi 14-21 sono state verificate/completate nel codice il 2026-10-21; restano le verifiche manuali indicate (Docker, MariaDB, browser).

- [x] **Fase 13: Verifica stato attuale** (`implementationPlan-verificaStatoAttuale.md`)
  - [x] Baseline test e git pulito; verifiche Docker/icone (fasi 9-10) e post-deploy (fasi 11-12); controllo account demo e `MAIL_*` in produzione.

- [ ] **Fase 14: Sicurezza registrazione e quick-login (P0)** (`implementationPlan-sicurezzaRegistrazioneEQuickLogin.md`)
  - [x] Separare `RoleSeeder` (solo ruoli) da `DemoSeeder` (solo locale); `DatabaseSeeder` aggiornato.
  - [x] Quick-login registrato solo con `APP_ENV=local`; pulsanti demo nascosti altrove.
  - [x] Throttle su login/registrazione; `ics_token` fuori da `$fillable`; test di non-regressione (`RegistrationSecurityTest`).
  - [ ] Pulizia account demo in produzione (senza perdere eventi: cascade) e README (operazione manuale in produzione).

- [x] **Fase 15: Gestione ruoli da interfaccia** (`implementationPlan-gestioneRuoliUtenti.md`)
  - [x] `User::isStaff()`, Gate `manage-users`, comando `app:make-admin`.
  - [x] Pagina Utenti (ricerca, cambio ruolo, ultimo Admin protetto, log) + link nel layout; test (`UserRolesTest`, `MakeAdminCommandTest`).
  - [x] README aggiornato.

- [x] **Fase 16: Permesso gestione sessioni per gruppo** (`implementationPlan-permessoGestioneSessioni.md`)
  - [x] Migrazione `group_user.can_manage_sessions`; relazioni e metodi su `User`/`Group`.
  - [x] Gate `update-session`, `manage-group-sessions`, `delete-event`; `EventController` riscritto (niente più eliminazione per `created_by`).
  - [x] Interruttore "Responsabile" in `admin/groups`; test (`SessionManagementPermissionTest`).
  - [x] README aggiornato (`situazione-di-partenza.md` da rivedere a mano).

- [ ] **Fase 17: Stanze, capienza e regole del calendario** (`implementationPlan-stanzeERegoleCalendario.md`)
  - [x] Migrazioni e modelli `rooms`/`room_weekday`; pagina Stanze (`RoomController`); `SchedulingException`.
  - [x] `CalendarScheduler` (capienza, slot, assemblee per sovrapposizione oraria, lock) e integrazione in `EventController`.
  - [x] Endpoint pubblico `/calendar/availability`; suggerimento disponibilità nei form; test (`SchedulerRulesTest`, `RoomsAndAvailabilityTest`).
  - [x] README.
  - [ ] Prova di concorrenza su MariaDB (manuale).

- [ ] **Fase 18: Spostamento diretto e scambio sessioni** (`implementationPlan-spostamentoDirettoESwap.md`)
  - [x] Tabella `session_swap_requests` (una pendente per sessione); `SessionSwapService` (richiesta, abbinamento a specchio, annullamento).
  - [x] Scambio atomico con orari invariati, log con `_context`, notifica email `SessionSwapped`.
  - [x] Viste (show, elenco richieste), regola "passato", test (`SessionSwapTest`).
  - [x] README.
  - [ ] Configurazione `MAIL_*` in produzione.

- [ ] **Fase 19: Modalità scura** (`implementationPlan-modalitaScura.md`)
  - [x] Rimappatura scale colore sotto `.dark`, variabili semantiche, script anti-lampo e toggle sistema/chiaro/scuro.
  - [x] Overlay modali e footer su variabili semantiche; test `ThemeToggleTest`.
  - [x] README.
  - [ ] Checklist visiva e contrasto nel browser (viste auth, eventi, utenti, input date).

- [ ] **Fase 20: Calendario visuale (FullCalendar)** (`implementationPlan-calendarioVisuale.md`)
  - [x] Dipendenze npm + entry Vite; feed JSON `/calendar/events`; vista elenco `?view=list`.
  - [x] Badge capienza/assemblea per giorno, modale di dettaglio, stile adattivo al tema; test (`CalendarEventsFeedTest`).
  - [x] README.
  - [ ] Verifica visiva nel browser (mobile incluso).

- [ ] **Fase 21: Importazione calendario da Excel/CSV** (`implementationPlan-importazioneCalendarioExcel.md`)
  - [x] Parser, servizio con anteprima + commit tutto-o-niente (stesse regole del form), controller e viste, modello scaricabile, pulizia file temporanei.
  - [x] Test scritti (`CalendarImportTest`); `Dockerfile` (stage composer) con `gd` e `zip`.
  - [x] Installare `phpoffice/phpspreadsheet` (richiede `ext-gd`; in locale `--ignore-platform-req=ext-gd`) e far passare i test (97 test verdi, build Docker ok, migrazione `session_swap_requests` applicata).
  - [x] README.
  - [ ] Prova manuale dell'import nel browser con un file reale.

- [ ] **Fase 22: Import dal foglio mensile reale** (`implementationPlan-V-importazioneCalendarioFoglioMensile.md`)
  - [x] Parser su layout a griglia (mese, etichette `Giovedì 3`, tavoli a 2 righe, bande su celle unite anche su due giorni, cambio mese).
  - [x] Servizio: anno di stagione, controllo giorno della settimana, orari 21:30–00:00, sistema nella descrizione, select gruppi (anche «crea nuovo») e bande (associativo/assemblea/ignora), commit tutto-o-niente.
  - [x] Controller, vista e test riscritti; rimosso il modello scaricabile; titolo del feed senza ripetizione del nome gruppo.
  - [x] Pagina Utenti: riquadri arrotondati con bordino, `.badge` definita.
  - [x] `php artisan test`: 103 test verdi (394 asserzioni).
  - [x] Nginx: asset serviti da `/var/www/build` (fix `read-only file system` in produzione).
  - [ ] Ricostruire/riavviare in dev e produzione (`dok.dev.up`, `git pull` + `docker compose up -d --build` sul server) e verificare `/build/...`, `/favicon.ico`.
  - [ ] Provare con il **file Excel reale** (formato cella sistema/campagna, bande unite).

- [ ] **Fase 24: modello Excel scaricabile nella pagina di import**
  - [x] Creare il modello generico (ottobre 2026 + foglio "Istruzioni") e linkarlo in `admin/import/calendar.blade.php` con `asset('modello-importazione-calendario.xlsx')`.
  - [x] Documentare nel README (sezione Import calendario).
  - [x] Modello rivisto: righe vuote tra le settimane, sistema e gruppo in due celle sovrapposte, max 3 tavoli giovedì e 5 venerdì, "Gioco libero" solo sul giorno saltato.
  - [x] `CalendarImportParser`: legge anche sistema + gruppo su due righe (riga sotto la serata); test `test_system_and_group_in_two_stacked_cells_are_read_like_one_cell`.
  - [ ] Lanciare `php artisan test --filter=CalendarImportTest` (il test nuovo non è stato eseguito).
  - [ ] Copiare il file in `public/modello-importazione-calendario.xlsx` (il link dà 404 finché il file non c'è) e fare commit.
  - [ ] Provare: scaricare il modello dalla pagina, ricaricarlo nell'import (anno stagione 2026) e controllare che l'anteprima lo legga (gruppi di esempio, bande, errori solo per stanze/capienza).

- [ ] **Fase 25: Fix logo 404 e navbar mobile** (`implementationPlan-V-fixLogoENavbarMobile.md`)
  - [x] Causa del 404: location nginx `= /icon.svg` con `try_files /public/icon.svg` (percorso doppio `public/public`); rimossa.
  - [x] Logo (header, login, registrazione) e favicon dai file generati in `build/icons` (niente più `icon.svg` diretto).
  - [x] Menu a comparsa mobile (hamburger, `nav.js`), link in `layouts/partials/nav-links.blade.php`, account in fondo al menu sotto `sm`.
  - [x] Test `NavigationTest`, README.
  - [ ] Lanciare `php artisan test`, `npm run build` e provare nel browser a 375px; dopo il deploy verificare `/build/icons/android-chrome-192x192.png` (200).

- [ ] **Fase 26: Verifica email e restrizioni per utenti non verificati** (`implementationPlan-V-verificaEmail.md`)
  - [x] `User implements MustVerifyEmail`, `EmailVerificationController`, rotte `verification.*`, email in italiano, invio alla registrazione (in `try/catch`).
  - [x] Non verificato: solo ruolo Membro (`UserController`), niente gruppi (`Group::addUser`, elenco in `admin/groups`), niente feed ICS (403 + modale nascosto).
  - [x] Banner nel layout, pagina `auth/verify-email`, badge nella pagina Utenti, `app:make-admin` e `DemoSeeder` verificano l'email.
  - [x] Migrazione backfill (staff e membri di gruppi esistenti = verificati).
  - [x] Test `EmailVerificationTest`; `AuthorizationGatesTest`, `EventAndGroupLogicTest`, `IcsCalendarControllerTest` passati a `User::factory()`; README.
  - [ ] Lanciare `php artisan test` (non eseguito) e `php artisan migrate`.
  - [ ] Configurare `MAIL_*` in produzione e provare registrazione + link di verifica; dopo il deploy controllare quali utenti esistenti risultano non verificati.

- [ ] **Fase 27: Deploy fallito per permessi su `storage/app/private/imports`**
  - [x] Causa: la build Docker sul server non legge `storage/app/private/imports` (creata dal container con permessi 0700) -> `permission denied` nel build context.
  - [x] `.dockerignore`: esclusi `storage/app/private`, `storage/app/public`, `storage/logs`, `storage/framework/{cache,sessions,views}`; README.
  - [x] Secondo errore causato dal `.dockerignore`: `composer dump-autoload` falliva (`Please provide a valid cache path`) perché `storage/framework/views` non esisteva nell'immagine; il `Dockerfile` ora ricrea le cartelle di `storage/` con `mkdir -p`.
  - [ ] Commit e push, poi verificare che il deploy (GitHub Actions) passi e che il sito mostri logo, hamburger e calendario senza errori in console.
  - [ ] Dopo il deploy: `php artisan migrate` (lo fa l'entrypoint/deploy? controllare che la migrazione `2026_10_22_120000_...` sia stata applicata).

- [ ] **Fase 28: Mailer Resend in produzione (`Class "Resend" not found`)**
  - [x] Causa: `MAIL_MAILER=resend` ma il pacchetto `resend/resend-php` non è in `composer.json`/`composer.lock` (la registrazione non dà errore grazie al `try/catch`, l'email non parte).
  - [x] README e `.env.example` documentano Resend.
  - [ ] Lanciare `composer require resend/resend-php` (in locale senza gd: `--ignore-platform-req=ext-gd`), committare `composer.json` + `composer.lock`, deploy.
  - [ ] Verificare in produzione: `RESEND_API_KEY` nel `.env`, `MAIL_FROM_ADDRESS` su dominio verificato in Resend, registrazione di prova e link di verifica.

- [ ] **Fase 23: `.env-overrides` e versionamento (allineato a unlimiteddb)**
  - [x] Creare `.env-overrides` (tracciato in Git) con `APP_VERSION_TYPE/PRIMARY/SECONDARY/TERTIARY` e `APP_VERSION` (parte da `1.0.0`).
  - [x] Caricarlo in `bootstrap/app.php` con `Dotenv::createMutable(...)->safeLoad()` prima di `Application::configure(...)`.
  - [x] Aggiungere `bash/all.sh` (incremento versione) e `bash/cmt.sh` (commit con versione + push).
  - [x] Documentare nel README (sezione Configurazione Environment).
  - [x] Verificato in locale: `env('APP_VERSION')` e `bash/all.sh` funzionano.
  - [x] Esporre la versione in `config('app.version')` (`config/app.php`) e mostrarla nel footer di `layouts/app.blade.php`.
  - [ ] Dopo il primo deploy: controllare la versione nel footer in produzione.

- [ ] **Fase 29: Profilo utente e gestione utenti** (`implementationPlan-V-profiloEGestioneUtenti.md`)
  - [x] Migrazione `2026_10_22_200000_make_user_references_nullable_on_user_delete`: `events.created_by`, `event_logs.user_id`, `session_swap_requests.requested_by` da `cascadeOnDelete` a `nullOnDelete` (eliminare un utente non cancella più le sue sessioni).
  - [x] `ProfileController` + vista `profile/edit` + rotte `profile.*`: nome, email (password attuale, verifica riparte), password, rigenerazione token ICS; link al profilo dal nome nell'header e nel menu mobile.
  - [x] `UserController`: `setVerified` (checkbox "Confermato"), `resendVerification` ("Reinvia mail"), `destroy` ("Elimina": non sé stessi, non l'ultimo Admin, scambi pendenti annullati, sessioni DB eliminate); vista `admin/users/index` aggiornata.
  - [x] Test `ProfileTest` e `UserManagementActionsTest`; README.
  - [ ] Lanciare `php artisan migrate` (dev e produzione) e `php artisan test` (non eseguiti: scritti senza poter lanciare PHP).
  - [ ] Provare nel browser: modifica profilo, cambio email con mail reale (Resend), rigenerazione token + re-iscrizione calendario, checkbox Confermato, Reinvia mail, eliminazione di un utente di prova con sessioni create.
