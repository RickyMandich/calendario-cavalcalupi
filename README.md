# Calendario Cavalcalupi

Applicazione web per la gestione degli eventi associativi, assemblee e sessioni di gioco di ruolo per l'**Associazione Culturale e Ludica Cavalcalupi**, con sincronizzazione automatica tramite **feed ICS personalizzato**.

---

## 🚀 Stack Tecnologico
- **Backend:** Laravel 12 (PHP 8.2+)
- **Database:** MariaDB
- **Frontend / UI:** Tailwind CSS v4 + BladewindUI + Vite
- **Pacchetti Chiave:**
  - `spatie/laravel-permission`: Controllo accessi basato su ruoli (RBAC).
  - `spatie/icalendar-generator`: Generazione di feed ICS dinamici compatibili con Google Calendar, Apple Calendar, Thunderbird, Outlook.
  - `phpoffice/phpspreadsheet`: Lettura del foglio Excel mensile per l'import del calendario (richiede `ext-gd` e `ext-zip`).
  - `resend/resend-php`: Mailer Resend usato in produzione per le email (verifica indirizzo, spostamenti e scambi di sessioni).
  - FullCalendar (`@fullcalendar/*`, via npm + Vite): calendario visuale.
  - Pest / PHPUnit: suite di test.
  - `opcodesio/log-viewer`: Interfaccia avanzata di consultazione log (`/log-viewer`), riservata all'Amministratore.

---

## 🐳 Infrastruttura Docker

L'infra segue lo stesso schema del progetto `unlimiteddb` (source of truth: i suoi `docker-compose.yml` e `docker-compose.dev.yml`). Un'unica immagine (`Dockerfile` multi-stage: composer, Vite, icone, PHP-FPM) usata da `app` e `worker`.

| | Produzione (`docker-compose.yml`) | Sviluppo (`docker-compose.dev.yml`) |
|---|---|---|
| Immagine | `calendario-cavalcalupi:prod` | `calendario-cavalcalupi:dev` |
| Accesso | Traefik, `calendario-cavalcalupi.mandich.dev` (rete esterna `proxy`) | `http://localhost:8000` |
| MariaDB | solo rete interna | esposta su `localhost:3307` |
| Utente DB | `calendario_cavalcalupi` senza password, subnet `172.24.0.0/24` | idem, subnet `172.25.0.0/24`, password da `DB_PASSWORD` nel `.env` (può essere vuota) |
| Asset Vite/icone | volume `build_assets` su `public/build` | bind mount `./public/build` |
| Worker | supervisor: 2 `queue:work` + `schedule:work` | solo `queue:work` |

Note:
- Il servizio `worker` di produzione esegue anche `schedule:work`: è quello che lancia `app:deactivate-groups` il 15 agosto.
- Ad ogni avvio l'entrypoint (`docker/entrypoint.sh`) ricopia gli asset compilati dall'immagine (`/opt/build-assets`) in `public/build`, così il mount in dev non serve mai asset vecchi.
- Le subnet dei due progetti sono diverse (`unlimiteddb` dev usa `172.30.0.0/24`) e così la porta del DB di sviluppo (3307), per poterli avviare insieme.
- nginx serve gli asset da `/var/www/build` (volume `build_assets` in produzione, bind `./public/build` in dev) con `location /build/ { alias ... }`. Non va montato `build_assets` dentro `/var/www/html/public/build`: `./public` è montato in sola lettura e `public/build` è in `.gitignore`, quindi sul server non esiste e Docker non può creare il mountpoint (nginx non parte con `read-only file system`).
- `.dockerignore` esclude i dati di runtime di `storage/` (`app/private`, `app/public`, `logs`, `framework/*`): sono montati come volume e i file creati dal container (`www-data`) possono avere permessi `0700`, per cui `docker compose build` sul server falliva con `error from sender: open .../storage/app/private/imports: permission denied` (deploy bloccato, sito fermo alla versione precedente). Se compare un errore simile su un'altra cartella, aggiungerla al `.dockerignore`. Poiché le cartelle escluse non entrano nell'immagine, lo stage `composer-builder` del `Dockerfile` le ricrea con `mkdir -p` prima di `composer dump-autoload`: senza `storage/framework/views` lo script `artisan package:discover` fallisce con `Please provide a valid cache path`.
- In produzione l'app sta dietro Cloudflare e Traefik, che terminano il TLS: `bootstrap/app.php` dichiara i proxy fidati (`trustProxies(at: '*')`) così Laravel genera URL `https://` (link del feed ICS, asset). Senza, il link ICS mostrato nel modale era `http://` e Google Calendar non riusciva a sincronizzarlo.

**Deploy.** Il push su `main` lancia `.github/workflows/deploy.yml` (GitHub Actions, secret `SSH_HOST`, `SSH_USER`, `SSH_PRIVATE_KEY`): via SSH esegue sul server `/home/ubuntu/scripts/deploy.sh calendario-cavalcalupi` (timeout 30 minuti; lo script vive sul server, non nel repository). Lo script (nell'ordine) fa `git pull`, `docker compose build`, `up -d` e **`exec -T app php artisan migrate --force`**, quindi le migrazioni vengono applicate a ogni deploy (l'`entrypoint.sh` del container, invece, non le esegue: vanno lanciate a mano solo se si avvia lo stack senza passare dal deploy). L'immagine di produzione non contiene `composer` ed `npm`: per controllare un pacchetto usare `ls vendor/<vendor>` nel container. La versione mostrata nel footer viene da `.env-overrides` (vedi «Configurazione Environment»). Ogni release sale di versione con `bash/all.sh`.

```bash
# sviluppo
docker compose -f docker-compose.dev.yml up -d --build
docker compose -f docker-compose.dev.yml exec app php artisan migrate --seed

# produzione
docker compose up -d --build
docker compose exec app php artisan migrate --force
```

---

## 🎨 Palette e Componenti UI

Il sito usa una palette **verde e bianco** con tema chiaro e **tema scuro** (le scale di colore sono specchiate sotto `.dark`, vedi «Modalità scura»). I colori sono definiti in un solo punto, `resources/css/app.css`:

- `@theme` → scala `brand-50 … brand-950` (verde, `brand-600` = `#16a34a`, `brand-700` = `#15803d`). Si usa come qualsiasi colore Tailwind: `bg-brand-600`, `text-brand-800`, `border-brand-100`. **Per cambiare colore al sito basta modificare questa scala.**
- `@layer components` → classi riutilizzate nelle viste: `.panel` (card bianca con bordo verde chiaro), `.field` / `.field-label` (input, select, textarea), `.btn-primary` (azione principale verde), `.btn-secondary` (azione secondaria bianca con bordo verde), `.badge` (etichetta arrotondata con bordino: ruoli e stati), più `.site-footer` e `.modal-overlay`, che leggono le variabili semantiche (`--surface`, `--footer-bg`, `--overlay`) così seguono il tema. Eventuali variazioni locali (es. padding) si aggiungono come utility Tailwind sull'elemento.

| Elemento | Colore |
|---|---|
| Sfondo pagina, header, pannelli | bianco |
| Sezioni secondarie, hover | `brand-50` / `brand-100` |
| Titoli | `brand-950` |
| Testo / testo secondario | `gray-900` / `gray-500` |
| Footer, `theme-color`, sfondo icona | `brand-800` / `brand-700` |
| Sessione di gioco | verde (`brand`) |
| Assemblea | `rose` |
| Evento associativo | `amber` |
| Ruoli: Admin / CD / Membro | `red` / `amber` / verde |

I colori dei tipi di evento (rose, amber) restano volutamente diversi dal verde per distinguere a colpo d'occhio le categorie nel calendario.

---

## 🖼️ Icone del Sito

Le icone (favicon, `apple-touch-icon`, icone Android, `site.webmanifest`) sono **generate automaticamente dal Dockerfile** a partire da un unico sorgente: `public/icon.svg`.

- Stage `icon-builder` del `Dockerfile` (debian + `librsvg2-bin` + `imagemagick`) esegue `docker/icons/generate-icons.sh`, che produce in `/out/icons`: `favicon-16x16.png`, `favicon-32x32.png`, `favicon.ico` (16/32/48), `apple-touch-icon.png` (180, sfondo pieno perché iOS non gestisce la trasparenza), `android-chrome-192x192.png`, `android-chrome-512x512.png`, `site.webmanifest`.
- Le icone finiscono in `/opt/build-assets/icons` (e in `public/build/icons`) insieme agli asset Vite: l'entrypoint le copia in `public/build` a ogni avvio (volume `build_assets` in produzione) e nginx le serve su `/build/icons/*`. `/favicon.ico` è servito da nginx con la stessa icona. `docker-compose.yml` non richiede configurazioni dedicate.
- La view `resources/views/layouts/favicons.blade.php` (inclusa da `layouts.app`) contiene i `<link>` alle icone; l'SVG sorgente **non** è mai referenziato direttamente: favicon e logo (header, login, registrazione: `build/icons/android-chrome-192x192.png`) usano solo i file generati dal build, serviti da nginx su `/build/icons/*` (come in `unlimiteddb`). In locale senza Docker serve quindi `public/build/icons` (lo crea il build Docker; in alternativa copiare la cartella da un'immagine già costruita). Per lo stesso motivo nginx non ha una location per `/icon.svg`.
- **Per cambiare icona**: sostituire `public/icon.svg` (quadrato, meglio 512x512) e ricostruire l'immagine (`docker compose build`). Lo stage viene ricostruito solo se cambia l'SVG o lo script.
- Variabili opzionali dello script (`ICON_APP_NAME`, `ICON_THEME`, `ICON_APPLE_BG`, `ICON_BASE_URL`) sono documentate nell'intestazione di `docker/icons/generate-icons.sh`.

---

## 👥 Ruoli e Permessi

| Ruolo | Descrizione & Permessi |
|---|---|
| **`ADMIN`** | Stessi poteri del CD (vedi sotto) **più** l'accesso a `/log-viewer`. Il primo Admin si crea da shell con `php artisan app:make-admin <email>`; l'ultimo Admin non può essere declassato né eliminato. |
| **`CD`** | Consiglio Direttivo (`User::isStaff()`: ADMIN e CD sono equivalenti per tutti i Gate tranne il Log Viewer). Gestisce: gruppi e assegnazione membri (anche il permesso «Responsabile»), utenti e ruoli (`/admin/users`), stanze e capienza (`/admin/rooms`), import del calendario (`/admin/import/calendar`), assemblee ed eventi associativi, qualsiasi sessione (anche data e orario a mano, nei casi eccezionali). |
| **`Membro`** | Accesso in consultazione al calendario, generazione link ICS personale; chi ha il permesso di gestione sessioni può creare sessioni, **richiedere il cambio giorno** e **annullare** le sessioni dei **propri gruppi attivi** (data e orario non si modificano a mano: solo CD/Admin, nei casi eccezionali). |

---

## 🧠 Logica di Business Chiave

1. **Tipologie di Eventi:**
   - **Sessione di Gioco (`session`):** Richiede obbligatoriamente l'associazione a un **gruppo attivo** (`is_active = true`).
   - **Assemblea (`assembly`) & Evento Associativo (`association_event`):** Eventi istituzionali (`group_id` impostato a `null`), gestibili unicamente da CD e Admin.
2. **Feed ICS Dinamico & Personalizzato:**
   - Endpoint: `/calendar/ics/{ics_token}`.
   - Ogni utente ha un `ics_token` univoco a 64 caratteri generato automaticamente alla creazione.
   - Il feed include le assemblee, gli eventi associativi e **unicamente le sessioni dei tavoli attivi a cui l'utente appartiene** (e solo se l'email dell'utente è verificata: altrimenti il feed risponde 403). Il token si può rigenerare dal profilo (`/profile`): il vecchio link smette di funzionare.
3. **Audit Trail & Storico Modifiche:**
   - Un `EventObserver` Eloquent registra automaticamente ogni modifica in `event_logs` (`created`, `updated`, `rescheduled`), tracciando l'utente, la data/ora e il diff prima/dopo. Per cambi giorno e scambi il motivo è in `new_data._context` (`swap`, `direct_move`, `waiting_request`); se l'utente viene eliminato il log resta con `user_id = NULL` (mostrato come «Utente Sconosciuto»).
4. **Disattivazione Annuale Gruppi (Scheduler):**
   - Comando Artisan: `php artisan app:deactivate-groups`.
   - Disattiva tutti i gruppi creati prima del **15 agosto** dell'anno corrente.
   - Pianificato in `routes/console.php` il 15 agosto alle 00:00 (Timezone `Europe/Rome`).
5. **Stanze e capienza:** i posti di un giorno sono la somma delle capienze delle stanze attive utilizzabili in quel giorno della settimana; una sessione occupa 1 posto, un giorno pieno o senza stanze non accetta sessioni, un'assemblea blocca ogni altro evento nella stessa fascia oraria, gli eventi associativi non occupano posti. Tutte le regole stanno in `App\Services\CalendarScheduler` (vedi «Funzionalità aggiunte»).
6. **Cambio giorno delle sessioni:** spostamento immediato se il giorno ha un posto libero, altrimenti richiesta in attesa (scambio a specchio o posto liberato); orario e durata non cambiano. Dettagli in `App\Services\SessionSwapService`.
7. **Email verificata:** chi non ha confermato l'email è solo `Membro`, non entra nei gruppi e non ha il feed ICS.

---

## 🛠️ Requisiti di Sistema
- **PHP:** >= 8.2 (estensioni raccomandate: `pdo_mysql`, `curl`, `mbstring`, `openssl`, `zip`, `gd`: le ultime due servono a `phpoffice/phpspreadsheet`; l'immagine Docker le installa già)
- **Composer:** >= 2.0
- **Node.js:** >= 20 e **npm**
- **MariaDB / MySQL Server:** attivo su porta 3306

---

## 📦 Installazione e Avvio Rapido

### 1. Clonare il repository e installare le dipendenze
```bash
git clone <repository-url>
cd calendario-cavalcalupi

composer install
npm install
```

### 2. Configurazione Environment
Copia il file `.env.example` in `.env` e configura i parametri di connessione al database:
```env
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=calendario_cavalcalupi
DB_USERNAME=calendario_cavalcalupi
DB_PASSWORD=
```

Per le email (verifica dell'indirizzo, notifiche di spostamento/scambio) in locale basta `MAIL_MAILER=log` (le email finiscono in `storage/logs`); in produzione si usa `MAIL_MAILER=resend` con `RESEND_API_KEY` e `MAIL_FROM_ADDRESS` su un dominio verificato in Resend (vedi «Verifica email»). `APP_TIMEZONE` deve restare `Europe/Rome`.

Genera la chiave applicativa:
```bash
php artisan key:generate
```

#### Variabili non sensibili e versione dell'app (`.env-overrides`)
Oltre al `.env` (non tracciato, con i segreti) esiste `.env-overrides`, **tracciato in Git**, che contiene solo valori non sensibili: oggi la versione dell'app (`APP_VERSION_TYPE`, `APP_VERSION_PRIMARY`, `APP_VERSION_SECONDARY`, `APP_VERSION_TERTIARY` e `APP_VERSION`, composta dalle precedenti). Stesso sistema di `unlimiteddb`.

- `bootstrap/app.php` lo carica con `Dotenv::createMutable(...)->safeLoad()` **prima** di `Application::configure(...)`: il repository con cui Laravel legge il `.env` è immutabile e non sovrascrive variabili già presenti in `$_ENV`/`$_SERVER`, quindi i valori di `.env-overrides` vincono su eventuali duplicati nel `.env`. `safeLoad()` non dà errore se il file manca.
- Nel container Docker arriva con il resto del codice (`COPY . .` nel `Dockerfile`; `.dockerignore` esclude solo `.env`), non serve montarlo.
- Si legge con `config('app.version')` (definita in `config/app.php` come `env('APP_VERSION', 'unknown')`; nelle viste e nel codice usare la config, non `env()`, che con la config in cache restituirebbe `null`). La versione è mostrata nel footer (`layouts/app.blade.php`).
- Aggiungere qui solo valori **non sensibili**; i segreti restano nel `.env`.

Versionamento e commit con gli script in `bash/` (da bash/WSL, dalla radice del progetto):

| Script | Cosa fa |
|---|---|
| `bash/all.sh` | incrementa la versione in `.env-overrides` e chiama `cmt.sh`. Senza opzioni incrementa la terza cifra (`TERTIARY`); `-p` incrementa la seconda e azzera la terza; `-v` incrementa la prima e azzera le altre; `-n` non fa `git add .`; `-m "msg"` aggiunge un messaggio al commit |
| `bash/cmt.sh` | `git add .` + commit `aggiornamento <data> [<versione>]` + `git push` (`-n` e `-m` come sopra) |

### 3. Migrazioni & Dati Demo (Seeders)
Esegui le migrazioni e il popolamento con ruoli e utenti di test (`DatabaseSeeder` crea sempre i ruoli con `RoleSeeder`; **solo con `APP_ENV=local`** lancia anche `DemoSeeder`: i tre utenti della tabella sotto con email già verificata, il gruppo «Tavolo Demo» con il membro demo come responsabile delle sessioni e tre stanze (A = 1 posto gio+ven, B = 2 posti gio, C = 1 posto ven, cioè 3 posti il giovedì e 2 il venerdì). Nessuna sessione di esempio. I test non usano il seeder: creano da soli ruoli, gruppi, stanze ed eventi):
```bash
php artisan migrate:fresh --seed
```

In **produzione** non si usa mai `migrate:fresh` e non esistono utenti demo: `php artisan migrate --force`, poi `php artisan db:seed --class=RoleSeeder --force` (solo ruoli) e `php artisan app:make-admin <email>` per il primo Admin (l'utente deve prima registrarsi dal sito; il comando ne verifica anche l'email).

### 4. Compilazione Asset Frontend
```bash
# Per sviluppo con hot-reload
npm run dev

# Oppure build di produzione
npm run build
```

### 5. Avvio del Server
```bash
php artisan serve
```
L'applicazione sarà accessibile all'indirizzo [http://localhost:8000](http://localhost:8000).

---

## 🔑 Credenziali Utenti Demo Preconfigurate

Questi account esistono **solo in locale** (`APP_ENV=local`, creati da `DemoSeeder`; mai in produzione) e usano tutti la password: `password`

| Ruolo | Email | Password | Accesso Diretto |
|---|---|---|---|
| **Admin** | `admin@cavalcalupi.it` | `password` | `/quick-login/admin` |
| **CD** | `cd@cavalcalupi.it` | `password` | `/quick-login/cd` |
| **Membro** | `membro@cavalcalupi.it` | `password` | `/quick-login/membro` |

*(Solo con `APP_ENV=local` la pagina `/login` mostra i pulsanti di accesso rapido e la rotta `/quick-login/{ruolo}` esiste; con qualsiasi altro ambiente la rotta non è nemmeno registrata.)*

---

## 🧪 Esecuzione della Suite di Test
La suite di test automatizzati (Pest / PHPUnit) copre tutte le funzionalità e regole di dominio:
```bash
php artisan test
```

Test inclusi:
- `Tests\Feature\AuthorizationGatesTest`: Verifica permessi e Gate per ruoli Admin, CD e Membro.
- `Tests\Feature\EventAndGroupLogicTest`: Validazione regole di gruppo attivo e observer per i log.
- `Tests\Feature\IcsCalendarControllerTest`: Download, sicurezza token e filtraggio eventi feed `.ics`.
- `Tests\Feature\DeactivateGroupsCommandTest`: Verifica disattivazione corretta gruppi pre-15 agosto.
- `Tests\Feature\CalendarAndWebRoutesTest`: Flusso completo web, creazione, riprogrammazione e visualizzazione storico.
- `RegistrationSecurityTest`, `MakeAdminCommandTest`, `UserRolesTest`: registrazione sicura, comando `app:make-admin`, gestione ruoli.
- `SessionManagementPermissionTest`: permesso "gestione sessioni" per gruppo.
- `SchedulerRulesTest`, `RoomsAndAvailabilityTest`: stanze, capienza, assemblee, disponibilità.
- `SessionSwapTest`: scambio sessioni tra gruppi (richieste a specchio, annullamento, conflitti con assemblee, atomicità).
- `DayChangeRequestTest`: cambio giorno immediato se c'è posto, attesa se il giorno è pieno, esecuzione automatica quando si libera un posto (annullamento o spostamento), cascata, permessi del form di modifica.
- `CalendarEventsFeedTest`, `ThemeToggleTest`: feed FullCalendar e tema scuro.
- `EmailVerificationTest`: verifica email, restrizioni per utenti non verificati (gruppi, ruoli, feed ICS).
- `NavigationTest`: menu a comparsa mobile (`#nav-toggle` / `#mobile-menu`), link per ruolo, logo e favicon dai file generati dal build.
- `CalendarImportTest`: import del foglio mensile (richiede `phpoffice/phpspreadsheet`).
- `ProfileTest`: pagina profilo (nome, email con password attuale e verifica, password, rigenerazione del token ICS).
- `UserManagementActionsTest`: conferma manuale dell'email, reinvio della mail di conferma, eliminazione utenti (eventi/log conservati, scambi pendenti annullati).

I test creano gli utenti con `User::factory()` (email verificata di default, `->unverified()` per il caso contrario) e stanze, ruoli e sessioni con il trait `Tests\Concerns\CreatesRooms` (`createRoom`, `makeUser`, `makeSession`, `makeEvent`, `nextWeekday`). I test che passano da controller o `CalendarScheduler` devono prima creare una stanza utilizzabile nel giorno della sessione: senza stanze il giorno ha capienza 0 e la sessione viene rifiutata.

Nota: con Docker i comandi artisan vanno eseguiti nel container (`docker compose -f docker-compose.dev.yml exec app php artisan ...`); i test usano SQLite in memoria e girano anche in locale.

---

## 🧩 Funzionalità aggiunte (fasi 14-30)

- **Registrazione e quick-login**: la registrazione pubblica crea solo `Membro` senza gruppi; il quick-login esiste solo con `APP_ENV=local`; login/registrazione hanno throttle. `DemoSeeder` (utenti, gruppo e stanze demo) gira solo in locale; in produzione usare `php artisan db:seed --class=RoleSeeder --force` e `php artisan app:make-admin <email>` per il primo Admin.
- **Ruoli** (`/admin/users`): Admin e CD hanno gli stessi poteri (`User::isStaff()`); l'Admin ha in più il Log Viewer. L'ultimo Admin non può essere declassato.
- **Permesso gestione sessioni**: colonna `group_user.can_manage_sessions`, assegnata da CD/Admin in `/admin/groups`. Gate `update-session`, `manage-group-sessions`, `delete-event`.
- **Stanze e capienza** (`/admin/rooms`): tabelle `rooms` e `room_weekday`. I posti di un giorno = somma delle capienze delle stanze attive utilizzabili quel giorno. Le regole stanno in `App\Services\CalendarScheduler` (non nel modello `Event`): sessione = 1 posto, giorno pieno bloccato, assemblea blocca per sovrapposizione oraria, eventi associativi non occupano posti. Endpoint pubblico `GET /calendar/availability`.
- **Cambio giorno e annullamento sessione** (`App\Services\SessionSwapService`, tabella `session_swap_requests`; piano in `implementationPlan-V-cambioGiornoImmediato.md`): il flusso normale per cambiare quando si gioca è composto da due sole azioni, dalla pagina della sessione: **«Richiedi cambio giorno»** e **«Annulla sessione»**. Data e orario **non si modificano a mano** (il form di modifica permette ai responsabili solo titolo e descrizione; giorno/ora restano modificabili solo da CD/Admin nei casi eccezionali). Regole del cambio giorno (orario e durata della sessione restano sempre invariati):
  - **giorno con posto libero** → la sessione si sposta **subito**, senza attendere nessuno (nessuna richiesta pendente, log `rescheduled` con `_context.reason = direct_move`, nessuna email: l'utente vede l'esito);
  - **giorno pieno** → la richiesta resta **in attesa** (una pendente per sessione: `pending_flag` + unique, senza scadenza) e si esegue quando **(a)** l'altro gruppo invia la richiesta speculare (scambio atomico, log `swap`, email `SessionSwapped` ai due richiedenti) oppure **(b)** si libera un posto in quel giorno (sessione annullata o spostata altrove): `SessionSwapService::fulfillWaiting()` esegue la richiesta più vecchia (per `id`), con log `waiting_request` ed email `SessionMoved` al richiedente. Ogni spostamento libera a sua volta il giorno di partenza, che viene riesaminato (cascata);
  - **giorno senza stanze** → richiesta rifiutata subito (`no_rooms`); **assemblea sovrapposta** all'orario mantenuto → spostamento immediato rifiutato, richiesta in attesa saltata (resta pendente);
  - i posti liberati da modifiche delle **stanze** (non da sessioni) non attivano le richieste in attesa: si riprendono al prossimo posto liberato.
  - **Annulla sessione** = eliminazione (`EventController::destroy`, gate `delete-event`), che libera il posto e fa partire `fulfillWaiting()`. Le richieste pendenti si annullano anche se il giorno della sessione cambia per altra via. Elenco delle richieste in attesa in `/swap-requests`.
- **Modalità scura**: classe `dark` su `<html>`; le scale `brand`, `gray`, ecc. sono rimappate in `resources/css/app.css`; toggle sistema/chiaro/scuro in `resources/js/theme.js` con script anti-lampo nel layout.
- **Calendario visuale**: FullCalendar (`resources/js/calendar.js`, entry Vite separata), feed JSON pubblico `GET /calendar/events?start&end&filter`, badge capienza/assemblea per giorno; `/calendar?view=list` mostra l'elenco a card.
- **Import calendario** (`/admin/import/calendar`): legge il **foglio mensile reale** dell'associazione (esempio in `example/esempio-di-excel.jpg`, piano in `implementationPlan-V-importazioneCalendarioFoglioMensile.md`). Titolo del mese (anche più mesi nello stesso foglio, o nome del foglio), righe `Giovedì 3` / `Venerdì 4`, un tavolo per colonna fatto di **due celle sovrapposte**: la prima (riga della serata) = sistema di gioco (va nella descrizione `Sistema: …`), la seconda (riga subito sotto) = campagna = gruppo; la seconda riga è letta solo se non è a sua volta una serata o un titolo di mese. Funziona anche con una sola cella con due righe (a capo) o con la cella del sistema vuota (solo nome del gruppo, es. SCUOLE MASTER). Celle unite su 3+ colonne = bande (festività, eventi, assemblee, «Gioco libero»), anche su due giorni. Anno = stagione (settembre–dicembre anno di stagione, gennaio–agosto quello dopo; il `Venerdì 1` dopo il 31 è del mese successivo) con controllo giorno della settimana ↔ data. Orari di default 21:30–00:00 (fine = mezzanotte del giorno dopo), modificabili nel modulo. Nell'anteprima: select dei gruppi attivi per ogni nome (con «Crea nuovo gruppo», valido per tutte le sessioni con quel nome) e select per ogni banda (evento associativo / assemblea / ignora; «Gioco libero» è ignorato di default). Regole di stanze/capienza come nel form, conferma tutto-o-niente con rivalidazione. Solo `.xlsx`/`.xls`, max 2 MB. **Modello scaricabile**: `public/modello-importazione-calendario.xlsx`, linkato in cima alla pagina di import ("Scarica il modello Excel"); è un file statico con dati generici (ottobre 2026: una settimana = giovedì con max 3 tavoli + venerdì con max 5, righe vuote tra una settimana e l'altra, un tavolo con solo gruppo, una banda su due serate, "Gioco libero" sul venerdì saltato) più un foglio "Istruzioni" che il parser ignora. Le etichette ("Giovedì 1", ...) sono quelle di ottobre 2026: devono coincidere con l'anno di stagione scelto nel modulo; **se si cambia il formato letto dal parser va rigenerato/aggiornato anche il modello**. Codice in `App\Services\CalendarImport\*` (`CalendarImportParser` legge il foglio, `CalendarImportService` valida e importa). **Da verificare sul file reale**: come sono scritti sistema e campagna nella stessa cella e se le bande sono celle unite. Richiede `phpoffice/phpspreadsheet` (che richiede `ext-gd` e `ext-zip`: il `Dockerfile` li installa anche nello stage composer; in locale senza gd usare `composer require phpoffice/phpspreadsheet --ignore-platform-req=ext-gd`). I file temporanei in `storage/app/private/imports` vengono eliminati dopo 24 ore dallo scheduler.
- **Verifica email** (`App\Http\Controllers\EmailVerificationController`, `User implements MustVerifyEmail`): alla registrazione parte un'email con link firmato (testo italiano in `AppServiceProvider`). Un utente con email **non verificata** può accedere e consultare il calendario, ma: ha solo il ruolo `Membro` (`UserController::updateRole` rifiuta CD/ADMIN; la pagina Utenti mostra il badge "Email non verificata" e disabilita le opzioni), **non può far parte di gruppi** (`Group::addUser` lancia `DomainException`; l'elenco "Aggiungi utente" in `/admin/groups` mostra solo i verificati) e **non ha il feed ICS** (`IcsCalendarController` risponde 403; il modale con l'URL personale non viene renderizzato). Nel layout un banner ambra invita a verificare con pulsante di reinvio; rotte `verification.notice` / `verification.verify` / `verification.send` (throttle 6/min) nel gruppo `auth` ma **senza** middleware `verified`. L'invio dell'email è in `try/catch`: con SMTP non configurato la registrazione non dà errore (l'utente può reinviare). `app:make-admin` segna anche l'email come verificata (serve per il primo Admin). `DemoSeeder` crea utenti demo già verificati. La migrazione `2026_10_22_120000_verify_email_of_existing_staff_and_group_members` considera verificati gli utenti esistenti con ruolo ADMIN/CD o già in un gruppo; gli altri verificano dal banner. **Richiede `MAIL_*` funzionanti in produzione** (`MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`). **Mailer Resend** (usato in produzione con `MAIL_MAILER=resend`): oltre a `RESEND_API_KEY` serve il pacchetto `resend/resend-php`, **già presente in `composer.json`** (aggiunto con la versione 1.0.9; il `Dockerfile` installa dal `composer.lock`, quindi dopo ogni `composer require` vanno committati sia `composer.json` che `composer.lock`, e in locale senza gd si aggiunge `--ignore-platform-req=ext-gd`) e `MAIL_FROM_ADDRESS` deve essere su un dominio verificato in Resend. Senza il pacchetto (situazione precedente alla 1.0.9) l'invio falliva con `Class "Resend" not found` (la registrazione non si rompe: l'errore finisce nei log e l'utente può reinviare). Nei test usare `User::factory()` (verificato di default, `->unverified()` per il caso contrario) e non `User::create()` se l'utente deve entrare in un gruppo o avere il feed.
- **Profilo utente** (`/profile`, `App\Http\Controllers\ProfileController`, vista `profile/edit.blade.php`; piano in `implementationPlan-V-profiloEGestioneUtenti.md`): raggiungibile cliccando il proprio nome nell'header (o nel menu mobile). Quattro moduli indipendenti, ognuno con il suo *error bag* (`profile`, `email`, `password`) perché `current_password` compare in più moduli: **nome reale**; **email** (richiede la password attuale; se cambia, `email_verified_at` torna `null` e parte la mail di verifica in `try/catch`; **ruoli e gruppi non vengono tolti**, ma finché il nuovo indirizzo non è confermato il feed ICS risponde 403 e compare il banner); **password** (attuale + nuova min 8 + conferma; l'hash lo fa il cast `hashed` di `User`); **feed ICS** (mostra l'URL solo a chi ha l'email verificata e `POST /profile/ics-token` genera un nuovo `ics_token` a 64 caratteri: il vecchio link dà 404 e va riaggiornato nei calendari in cui era stato aggiunto). Le rotte stanno nel gruppo `auth` ma **senza** `verified`, così chi non ha confermato l'email può comunque correggerla; cambio email/password e rigenerazione token hanno throttle 6/min.
- **Gestione utenti: conferma, reinvio, eliminazione** (`/admin/users`, `UserController`, Admin e CD): colonna **Confermato** con checkbox (`PUT admin.users.verified`: spuntare conferma l'email a mano; la conferma si può togliere **solo a un Membro senza gruppi**, altrimenti si violerebbe l'invariante "non verificato = solo Membro, nessun gruppo"; non modificabile sul proprio account) e pulsante **Reinvia mail** per i non verificati (`POST admin.users.verification`, throttle 6/min, errore leggibile se l'SMTP non risponde); pulsante **Elimina** (`DELETE admin.users.destroy`, con conferma; non su se stessi, non l'ultimo Admin). **Cosa succede all'eliminazione**: le sessioni/eventi creati dall'utente **restano** nel calendario (`events.created_by` → `NULL`), i log delle modifiche restano (`event_logs.user_id` → `NULL`, mostrati come "Utente Sconosciuto"), le richieste di scambio **pendenti** dell'utente vengono annullate (`cancel_reason = user_deleted`) mentre quelle storiche restano (`session_swap_requests.requested_by` → `NULL`); appartenenze ai gruppi e ruolo spariscono con l'utente e, con `SESSION_DRIVER=database`, le sue righe in `sessions` vengono eliminate (logout immediato). Questo comportamento richiede la migrazione `2026_10_22_200000_make_user_references_nullable_on_user_delete` (prima le tre FK erano `cascadeOnDelete` e cancellare un utente avrebbe cancellato le sue sessioni): **va eseguita con `php artisan migrate` prima di usare il pulsante Elimina**. Nelle viste/codice che leggono `creator` o `user` di un evento/log ricordare che ora possono essere `null`.
- **Navbar responsive** (`layouts/app.blade.php`, voci in `layouts/partials/nav-links.blade.php`, JS in `resources/js/nav.js`): da `md` (768px) i link stanno nella barra; sotto `md` compare il pulsante hamburger che apre un menu a comparsa (si chiude con Esc, al click su una voce e passando a `md`). Sotto `sm` nome utente, ruolo ed **Esci** (o **Registrati**) si spostano in fondo al menu. Per aggiungere una voce modificare solo il partial (vale per entrambi i menu).
- **Fuso orario**: `APP_TIMEZONE=Europe/Rome` (anche in `phpunit.xml`).

---

## ⏰ Scheduler
Per testare il ciclo di disattivazione gruppi da riga di comando:
```bash
# Esecuzione diretta
php artisan app:deactivate-groups

# Visualizzazione task schedulati
php artisan schedule:list
```

Task pianificati (`routes/console.php`), eseguiti in produzione dal servizio `worker` con `schedule:work`:

| Task | Quando | Cosa fa |
|---|---|---|
| `app:deactivate-groups` | 15 agosto, 00:00 (`Europe/Rome`) | disattiva i gruppi creati prima del 15 agosto |
| `prune-calendar-imports` | ogni giorno | elimina da `storage/app/private/imports` i file temporanei dell'import calendario più vecchi di 24 ore |

---

## 🗺️ Mappa del Codice

| Area | Dove | Note |
|---|---|---|
| Rotte | `routes/web.php`, `routes/console.php` | nessuna API separata: solo web + 2 endpoint JSON pubblici |
| Autorizzazioni | `App\Providers\AppServiceProvider` | tutti i Gate (tabella sotto) e il testo italiano dell'email di verifica |
| Controller | `app/Http/Controllers/` | `AuthController` (login, registrazione, quick-login locale), `CalendarController` (pagina, feed FullCalendar, disponibilità), `EventController`, `GroupController`, `RoomController`, `UserController`, `ProfileController`, `EmailVerificationController`, `SessionSwapController`, `CalendarImportController`, `IcsCalendarController` |
| Regole di dominio | `app/Services/` | `CalendarScheduler` (capienza, stanze, assemblee e lock: lo usano form, cambio giorno/scambi e import), `SessionSwapService` (cambio giorno, scambi, richieste in attesa), `CalendarImport/*` (parser del foglio + servizio anteprima/commit) |
| Modelli | `app/Models/` | `User`, `Group` (pivot `group_user` con `can_manage_sessions`), `Event`, `EventLog`, `Room` + `RoomWeekday`, `SessionSwapRequest` |
| Effetti collaterali | `app/Observers/EventObserver.php`, `app/Notifications/` | log delle modifiche e annullamento delle richieste obsolete; email `SessionSwapped` e `SessionMoved` |
| Eccezioni | `app/Exceptions/SchedulingException.php` | violazione di una regola del calendario (porta `field` e `reason`) |
| Comandi | `app/Console/Commands/` | `app:deactivate-groups`, `app:make-admin {email}` |
| Database | `database/migrations`, `database/seeders` | `RoleSeeder` (sempre), `DemoSeeder` (solo locale) |
| Frontend | `resources/views`, `resources/css/app.css`, `resources/js` | `app.js`/`bootstrap.js` (entry), `calendar.js` (FullCalendar, entry separata), `nav.js` (menu mobile), `theme.js` (tema) |
| Infra | `Dockerfile`, `docker/`, `docker-compose*.yml`, `.github/workflows/deploy.yml`, `bash/` | vedi «Infrastruttura Docker» |
| Test | `tests/Feature`, `tests/Concerns` | vedi «Suite di Test» |

### Gate

| Gate | Chi passa | Usato per |
|---|---|---|
| `viewLogViewer` | solo `ADMIN` | `/log-viewer` |
| `manage-groups`, `manage-assemblies`, `manage-users`, `manage-rooms`, `import-calendar` | staff (`ADMIN` o `CD`) | pagine `/admin/*`, assemblee ed eventi associativi |
| `manage-group-sessions` (gruppo) | staff, oppure responsabile di quel gruppo attivo | creare sessioni di un gruppo |
| `update-session` (evento) | staff, oppure responsabile del gruppo della sessione | modificare, richiedere il cambio giorno |
| `delete-event` (evento) | staff, oppure responsabile del gruppo della sessione | annullare la sessione / eliminare l'evento |

### Rotte

| Accesso | Rotte |
|---|---|
| Pubbliche | `/` → `/calendar`, `/calendar` (`?filter=my-groups`, `?view=list`), `GET /calendar/events`, `GET /calendar/availability`, `GET /calendar/ics/{token}`, `/up` (health check) |
| Ospiti | `/login`, `/register` (throttle), `/quick-login/{ruolo}` (solo `APP_ENV=local`) |
| Utente loggato | `/profile*`, `/email/verify*`, `/events/*` (le azioni sono protette dai Gate), `/swap-requests`, `POST /events/{event}/swap-requests`, `DELETE /swap-requests/{id}`, `POST /logout` |
| Staff | `/admin/groups*`, `/admin/users*`, `/admin/rooms*`, `/admin/import/calendar*` |
| Admin | `/log-viewer` |

---

## 🧭 Flusso di Lavoro e Convenzioni

- **Modifiche non banali**: analisi → piano in `implementationPlan-{cosaSiFa}.md` nella radice → esecuzione → a lavoro finito il file diventa `implementationPlan-V-{cosaSiFa}.md`. Le modifiche molto piccole non richiedono il piano. I piani già eseguiti sono lo storico delle decisioni di ogni fase.
- **`todo.md`** rispecchia ogni modifica (una fase per intervento, voci spuntate solo se verificate); **questo README** deve restare sufficiente per entrare nello sviluppo senza altre spiegazioni, quindi va aggiornato insieme al codice.
- Mai modificare `vendor/` e `node_modules/`. Dopo un `composer require` committare `composer.json` e `composer.lock` (il `Dockerfile` installa dal lock).
- Le regole del calendario (capienza, assemblee, spostamenti) si cambiano **solo** in `CalendarScheduler`/`SessionSwapService`, mai nei controller o nel modello `Event`; le regole di accesso nei Gate di `AppServiceProvider`.
- Nel progetto non esiste (per ora) una cartella `.agent/rules/` con regole specifiche.

### Documenti nel repository

| File | Cos'è | Stato |
|---|---|---|
| `README.md`, `todo.md` | documentazione viva e stato dei lavori | aggiornati con il codice |
| `situazione-di-partenza.md` | brief originale dell'associazione | storico: la regola «ogni membro del gruppo può modificare le sessioni» è stata sostituita dal permesso per gruppo |
| `analisi-preprogetto.md` | analisi iniziale dei requisiti | storico |
| `analisi-finale-modifiche.md` | decisioni che hanno generato le fasi 13-21 | storico (il codice ha poi aggiunto verifica email, profilo, cambio giorno immediato) |
| `ChatGPT-Analisi piano modifiche-*.md` | export della chat di analisi | storico, non modificare |
| `implementationPlan-V-*.md` | piani delle modifiche già eseguite | storico |
| `example/esempio-di-excel.jpg` | foglio mensile reale usato come riferimento dall'import | riferimento |
