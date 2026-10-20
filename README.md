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
- `public/build/` deve esistere sull'host (c'è un `.gitkeep` versionato): in produzione nginx monta `./public` in sola lettura e Docker non può creare lì il mountpoint di `build_assets`; senza la cartella il container nginx non parte (`read-only file system`).

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

### 3. Migrazioni & Dati Demo (Seeders)
Esegui le migrazioni e il popolamento con ruoli, utenti di test e campagne demo:
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

---

## ⏰ Scheduler
Per testare il ciclo di disattivazione gruppi da riga di comando:
```bash
# Esecuzione diretta
php artisan app:deactivate-groups

# Visualizzazione task schedulati
php artisan schedule:list
```
