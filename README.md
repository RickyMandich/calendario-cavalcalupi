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
- In produzione l'app sta dietro Cloudflare e Traefik, che terminano il TLS: `bootstrap/app.php` dichiara i proxy fidati (`trustProxies(at: '*')`) così Laravel genera URL `https://` (link del feed ICS, asset). Senza, il link ICS mostrato nel modale era `http://` e Google Calendar non riusciva a sincronizzarlo.

```bash
# sviluppo
docker compose -f docker-compose.dev.yml up -d --build
docker compose -f docker-compose.dev.yml exec app php artisan migrate --seed

# produzione
docker compose up -d --build
```

---

## 🎨 Palette e Componenti UI

Il sito usa una palette **verde e bianco** (tema chiaro). I colori sono definiti in un solo punto, `resources/css/app.css`:

- `@theme` → scala `brand-50 … brand-950` (verde, `brand-600` = `#16a34a`, `brand-700` = `#15803d`). Si usa come qualsiasi colore Tailwind: `bg-brand-600`, `text-brand-800`, `border-brand-100`. **Per cambiare colore al sito basta modificare questa scala.**
- `@layer components` → classi riutilizzate nelle viste: `.panel` (card bianca con bordo verde chiaro), `.field` / `.field-label` (input, select, textarea), `.btn-primary` (azione principale verde), `.btn-secondary` (azione secondaria bianca con bordo verde). Eventuali variazioni locali (es. padding) si aggiungono come utility Tailwind sull'elemento.

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
- La view `resources/views/layouts/favicons.blade.php` (inclusa da `layouts.app`) contiene i `<link>` alle icone; l'SVG è linkato direttamente (`/icon.svg`), quindi l'icona funziona anche in sviluppo locale senza Docker. Lo stesso SVG è usato come logo nell'header e nelle pagine di login/registrazione.
- **Per cambiare icona**: sostituire `public/icon.svg` (quadrato, meglio 512x512) e ricostruire l'immagine (`docker compose build`). Lo stage viene ricostruito solo se cambia l'SVG o lo script.
- Variabili opzionali dello script (`ICON_APP_NAME`, `ICON_THEME`, `ICON_APPLE_BG`, `ICON_BASE_URL`) sono documentate nell'intestazione di `docker/icons/generate-icons.sh`.

---

## 👥 Ruoli e Permessi

| Ruolo | Descrizione & Permessi |
|---|---|
| **`ADMIN`** | Accesso completo: gestione tavoli/gruppi, creazione/modifica di qualsiasi evento, accesso a `/log-viewer`. |
| **`CD`** | Consiglio Direttivo: gestione tavoli (creazione, disattivazione, assegnazione membri), gestione assemblee ed eventi associativi, supervisione sessioni. |
| **`Membro`** | Accesso in consultazione al calendario, generazione link ICS personale; può creare o riprogrammare date e orari delle sessioni dei **propri gruppi attivi**. |

---

## 🧠 Logica di Business Chiave

1. **Tipologie di Eventi:**
   - **Sessione di Gioco (`session`):** Richiede obbligatoriamente l'associazione a un **gruppo attivo** (`is_active = true`).
   - **Assemblea (`assembly`) & Evento Associativo (`association_event`):** Eventi istituzionali (`group_id` impostato a `null`), gestibili unicamente da CD e Admin.
2. **Feed ICS Dinamico & Personalizzato:**
   - Endpoint: `/calendar/ics/{ics_token}`.
   - Ogni utente ha un `ics_token` univoco a 64 caratteri generato automaticamente alla creazione.
   - Il feed include le assemblee, gli eventi associativi e **unicamente le sessioni dei tavoli attivi a cui l'utente appartiene**.
3. **Audit Trail & Storico Modifiche:**
   - Un `EventObserver` Eloquent registra automaticamente ogni modifica in `event_logs` (`created`, `updated`, `rescheduled`), tracciando l'utente, la data/ora e il diff prima/dopo.
4. **Disattivazione Annuale Gruppi (Scheduler):**
   - Comando Artisan: `php artisan app:deactivate-groups`.
   - Disattiva tutti i gruppi creati prima del **15 agosto** dell'anno corrente.
   - Pianificato in `routes/console.php` il 15 agosto alle 00:00 (Timezone `Europe/Rome`).

---

## 🛠️ Requisiti di Sistema
- **PHP:** >= 8.2 (estensioni raccomandate: `pdo_mysql`, `curl`, `mbstring`, `openssl`, `zip`)
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
Esegui le migrazioni e il popolamento con ruoli e utenti di test (nessun gruppo o sessione di default: i seeder non creano dati di esempio, quindi i test creano da soli i gruppi e gli eventi di cui hanno bisogno):
```bash
php artisan migrate:fresh --seed
```

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

Tutti gli account di test utilizzano la password: `password`

| Ruolo | Email | Password | Accesso Diretto |
|---|---|---|---|
| **Admin** | `admin@cavalcalupi.it` | `password` | `/quick-login/admin` |
| **CD** | `cd@cavalcalupi.it` | `password` | `/quick-login/cd` |
| **Membro** | `membro@cavalcalupi.it` | `password` | `/quick-login/membro` |

*(Nella pagina di login `/login` sono inoltre presenti comodi pulsanti per il test rapido con un solo click)*

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
- `SessionSwapTest`: scambio sessioni tra gruppi.
- `CalendarEventsFeedTest`, `ThemeToggleTest`: feed FullCalendar e tema scuro.
- `CalendarImportTest`: import del foglio mensile (richiede `phpoffice/phpspreadsheet`).

Nota: con Docker i comandi artisan vanno eseguiti nel container (`docker compose -f docker-compose.dev.yml exec app php artisan ...`); i test usano SQLite in memoria e girano anche in locale.

---

## 🧩 Funzionalità aggiunte (fasi 14-21)

- **Registrazione e quick-login**: la registrazione pubblica crea solo `Membro` senza gruppi; il quick-login esiste solo con `APP_ENV=local`; login/registrazione hanno throttle. `DemoSeeder` (utenti, gruppo e stanze demo) gira solo in locale; in produzione usare `php artisan db:seed --class=RoleSeeder --force` e `php artisan app:make-admin <email>` per il primo Admin.
- **Ruoli** (`/admin/users`): Admin e CD hanno gli stessi poteri (`User::isStaff()`); l'Admin ha in più il Log Viewer. L'ultimo Admin non può essere declassato.
- **Permesso gestione sessioni**: colonna `group_user.can_manage_sessions`, assegnata da CD/Admin in `/admin/groups`. Gate `update-session`, `manage-group-sessions`, `delete-event`.
- **Stanze e capienza** (`/admin/rooms`): tabelle `rooms` e `room_weekday`. I posti di un giorno = somma delle capienze delle stanze attive utilizzabili quel giorno. Le regole stanno in `App\Services\CalendarScheduler` (non nel modello `Event`): sessione = 1 posto, giorno pieno bloccato, assemblea blocca per sovrapposizione oraria, eventi associativi non occupano posti. Endpoint pubblico `GET /calendar/availability`.
- **Scambio sessioni** (`App\Services\SessionSwapService`, tabella `session_swap_requests`): ogni gruppo invia una richiesta dalla pagina della sessione; quando le due richieste combaciano a specchio i giorni si scambiano (orari e durate invariati), con log `_context` e email `SessionSwapped` ai due richiedenti. Una richiesta pendente per sessione (`pending_flag` + unique). Le richieste si annullano se cambia il giorno della sessione. Elenco in `/swap-requests`.
- **Modalità scura**: classe `dark` su `<html>`; le scale `brand`, `gray`, ecc. sono rimappate in `resources/css/app.css`; toggle sistema/chiaro/scuro in `resources/js/theme.js` con script anti-lampo nel layout.
- **Calendario visuale**: FullCalendar (`resources/js/calendar.js`, entry Vite separata), feed JSON pubblico `GET /calendar/events?start&end&filter`, badge capienza/assemblea per giorno; `/calendar?view=list` mostra l'elenco a card.
- **Import calendario** (`/admin/import/calendar`): legge il **foglio mensile reale** dell'associazione (esempio in `example/esempio-di-excel.jpg`, piano in `implementationPlan-V-importazioneCalendarioFoglioMensile.md`). Titolo del mese (anche più mesi nello stesso foglio, o nome del foglio), righe `Giovedì 3` / `Venerdì 4`, una cella per tavolo: riga 1 = sistema di gioco (va nella descrizione `Sistema: …`), riga 2 = campagna = gruppo (una sola riga = nome del gruppo, es. SCUOLE MASTER). Celle unite su 3+ colonne = bande (festività, eventi, assemblee, «Gioco libero»), anche su due giorni. Anno = stagione (settembre–dicembre anno di stagione, gennaio–agosto quello dopo; il `Venerdì 1` dopo il 31 è del mese successivo) con controllo giorno della settimana ↔ data. Orari di default 21:30–00:00 (fine = mezzanotte del giorno dopo), modificabili nel modulo. Nell'anteprima: select dei gruppi attivi per ogni nome (con «Crea nuovo gruppo», valido per tutte le sessioni con quel nome) e select per ogni banda (evento associativo / assemblea / ignora; «Gioco libero» è ignorato di default). Regole di stanze/capienza come nel form, conferma tutto-o-niente con rivalidazione. Solo `.xlsx`/`.xls`, max 2 MB. Codice in `App\Services\CalendarImport\*` (`CalendarImportParser` legge il foglio, `CalendarImportService` valida e importa). **Da verificare sul file reale**: come sono scritti sistema e campagna nella stessa cella e se le bande sono celle unite. Richiede `phpoffice/phpspreadsheet` (che richiede `ext-gd` e `ext-zip`: il `Dockerfile` li installa anche nello stage composer; in locale senza gd usare `composer require phpoffice/phpspreadsheet --ignore-platform-req=ext-gd`). I file temporanei in `storage/app/private/imports` vengono eliminati dopo 24 ore dallo scheduler.
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
