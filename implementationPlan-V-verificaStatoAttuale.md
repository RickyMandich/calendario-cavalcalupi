# Implementation Plan - Verifica dello stato attuale (Fase 13)

**Riferimento:** `analisi-finale-modifiche.md` (sez. 14, punto 0).
**Dipendenze:** nessuna. È il primo piano da eseguire.
**Piani successivi (ordine consigliato):** `sicurezzaRegistrazioneEQuickLogin` → `gestioneRuoliUtenti` → `permessoGestioneSessioni` → `stanzeERegoleCalendario` → `spostamentoDirettoESwap` → `modalitaScura` → `calendarioVisuale` → `importazioneCalendarioExcel`.

## 1. Obiettivo
1. Chiudere le verifiche ancora aperte in `todo.md` (fasi 9-12) prima di costruire sopra.
2. Fissare una baseline (test verdi, git pulito) per poter distinguere i problemi preesistenti da quelli introdotti dai nuovi piani.
3. Registrare in questo file i risultati del controllo del codice fatto in fase di analisi (sez. 2), che i piani successivi danno per assodati.

Questo piano non modifica codice applicativo.

## 2. Esito dell'analisi del codice (già eseguita)

Letti: `routes/web.php`, `AppServiceProvider`, modelli `Event`/`Group`/`User`, `EventObserver`, i controller, `RoleSeeder`/`DatabaseSeeder`, migrazioni di `group_user`/`events`/`event_logs`, viste (`layouts/app`, `calendar/index`, `events/*`, `admin/groups/index`, `auth/login`), `app.css`, `package.json`, `vite.config.js`, `Dockerfile`, `docker-compose.yml`, `worker.conf`, `phpunit.xml`, test esistenti. Non esiste `.agent/rules/`.

| # | Evidenza | Impatto |
|---|---|---|
| F1 | `/quick-login/{role}` è registrata nel gruppo `guest` senza condizioni di ambiente; `login.blade.php` mostra i pulsanti demo e la password `password`. | **Falla critica** con registrazione pubblica → piano `sicurezzaRegistrazioneEQuickLogin`. |
| F2 | `RoleSeeder` crea i ruoli **e** i tre utenti demo con password `password`; `DatabaseSeeder` lo richiama sempre. Un `db:seed`/`migrate --seed` in produzione crea un Admin con password nota. | Separare ruoli e utenti demo (stesso piano). |
| F3 | `AuthController::register` assegna già `Membro` e fa login. `User::$fillable` contiene `ics_token`. | Registrazione ok; togliere `ics_token` da `$fillable` dopo verifica dei test. |
| F4 | `EventController::store/edit/update` autorizzano per appartenenza al gruppo; `destroy` permette l'eliminazione anche al solo **creatore** (`created_by`); `show.blade.php` replica la stessa regola. | Da sostituire con il permesso per gruppo → `permessoGestioneSessioni`. |
| F5 | `EventObserver` logga in `updating` (prima del salvataggio) con `Auth::id() ?? created_by`; azioni `created`/`updated`/`rescheduled` (enum). | Il contesto dello scambio va nel JSON `new_data` (chiave `_context`), senza toccare l'enum. |
| F6 | `Event::saving` lancia `InvalidArgumentException` per sessione senza gruppo attivo e azzera `group_id` per i non-sessione. | Le nuove regole usano un'eccezione dedicata (`SchedulingException`) convertita in errori di validazione dal controller. |
| F7 | `CalendarController::index` e `IcsCalendarController` usano `where(...)->orWhereIn(...)` non raggruppato: funziona solo perché non ci sono altri `where`. | Raggruppare con closure in ogni nuova query con filtri aggiuntivi (feed FullCalendar). |
| F8 | `/calendar` è pubblica; `events.show` (con storico log) richiede login. Con registrazione pubblica, qualunque utente registrato vede lo storico. | Accettato (dati solo organizzativi); coperto da test di non-regressione. |
| F9 | Fuso `Europe/Rome` (`config/app.php`, default `APP_TIMEZONE`); `start_time`/`end_time` sono `DATETIME` senza fuso interpretati nel fuso app. | Il giorno della settimana e la "data" di un evento si calcolano nel fuso app. |
| F10 | `phpunit.xml`: SQLite in-memory, `MAIL_MAILER=array`, `QUEUE_CONNECTION=sync`. `lockForUpdate()` è un no-op su SQLite. | I test non provano la vera concorrenza: serve una prova manuale su MariaDB (piano stanze). |
| F11 | `Dockerfile` installa già `gd`, `zip`, `xml`, `mbstring`, `pdo_mysql`; worker con 2 `queue:work` + `schedule:work`; `QUEUE_CONNECTION=database` e tabella `jobs` presente. | Import Excel e notifiche in coda non richiedono modifiche all'infrastruttura. |
| F12 | `.env.example` ha `MAIL_MAILER=log`. | Le email di scambio richiedono SMTP reale in produzione. |
| F13 | Layout: `bladewind` caricato solo per CSS/`helpers.js` e `<x-bladewind::notification />`. | Impatto minimo sul tema scuro. |
| F14 | `app.css` (Tailwind v4): scala `brand` in `@theme` + componenti `.panel .field .field-label .btn-primary .btn-secondary`. Le viste usano molte utility inline (`bg-white`, `gray-*`, `brand-*`, `rose/amber/sky/red-*`). | Strategia tema scuro: rimappare le scale sotto `.dark` (piano `modalitaScura`). |
| F15 | `todo.md` ha già le fasi 11 e 12 (trustProxies, fuso ICS) con verifiche post-deploy aperte. | Le nuove fasi partono dalla **13**. |

## 3. Convenzioni comuni a tutti i piani
- Flusso: piano scritto → eseguito → rinominato `implementationPlan-V-{nome}.md`. Aggiornare `todo.md` e `README.md` a ogni piano. Non toccare `vendor/` né `node_modules/`.
- Test: classi PHPUnit in `tests/Feature` con `RefreshDatabase` (stile esistente). Eseguire `php artisan test` a fine di ogni passo significativo.
- Messaggi utente e commenti di dominio in italiano; nomi di classi/metodi in inglese (come ora).
- Migrazioni: solo additive (`php artisan make:migration ...`); mai `migrate:fresh` in produzione.
- Autorizzazione sempre lato server (Gate/middleware); l'interfaccia nasconde i pulsanti solo per comodità.
- Dopo ogni piano: `npm run build` (se toccati CSS/JS/viste) e controllo `docker compose -f docker-compose.dev.yml up -d --build` almeno ai piani 14, 17, 19, 20.

## 4. Passi
1. **Stato git**: `git status`, `git log -5`, verificare che il lavoro attuale sia committato (il repository risulta su `main`). Creare un commit/tag di partenza (es. `pre-modifiche-2026-10`) per poter tornare indietro.
2. **Baseline test**: `php artisan test` (o `docker compose -f docker-compose.dev.yml exec app php artisan test`). Annotare qui l'esito e gli eventuali test già rossi.
3. **Verifiche Docker (todo fasi 9-10)**:
   - `docker compose -f docker-compose.dev.yml up -d --build`, poi `docker compose -f docker-compose.dev.yml exec app php artisan migrate --seed`;
   - `curl -I http://localhost:8000/` → 200 o redirect a `/calendar`;
   - `curl -I http://localhost:8000/build/icons/favicon.ico`, `/build/icons/site.webmanifest`, `/icon.svg`, `/favicon.ico` → 200;
   - `docker compose -f docker-compose.dev.yml exec app php artisan schedule:list` mostra `app:deactivate-groups`.
4. **Verifiche post-deploy (todo fasi 11-12)** su `calendario-cavalcalupi.mandich.dev`: il link ICS nel modale inizia con `https://`; ri-aggiungere il calendario a Google Calendar; controllare che gli orari non siano sfasati di 2 ore; controllare `config('app.timezone')` sul server (`php artisan tinker --execute="echo config('app.timezone');"`).
5. **Configurazione di produzione** (solo lettura, non committare): `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, `MAIL_*` (oggi presumibilmente `log`), `QUEUE_CONNECTION=database`. Annotare cosa manca per le email (piano `spostamentoDirettoESwap`).
6. **Account demo in produzione**: verificare se esistono `admin@cavalcalupi.it`, `cd@cavalcalupi.it`, `membro@cavalcalupi.it` nel DB di produzione (`User::whereIn('email', [...])->get(['id','email'])` da tinker). Annotare l'esito: decide l'urgenza del piano 14.
7. **Ispezione rotte e migrazioni**: `php artisan route:list --except-vendor` e `php artisan migrate:status` in produzione e in dev; annotare differenze.
8. **Aggiornare `todo.md`** spuntando le verifiche eseguite (fasi 9-12) e aggiungendo la Fase 13 con l'esito.

## 5. Criteri di accettazione
- Test baseline eseguiti e risultato annotato.
- Tutte le voci aperte di `todo.md` fasi 9-12 sono chiuse o trasformate in problemi tracciati con una causa.
- Sezione 6 compilata.

## 6. Esito (da compilare in esecuzione)
- Baseline test: _da compilare_
- Docker dev: _da compilare_
- Icone / manifest: _da compilare_
- Produzione (https ICS, fuso, MAIL, account demo presenti?): _da compilare_

## 7. Chiusura
Rinominare in `implementationPlan-V-verificaStatoAttuale.md`.
