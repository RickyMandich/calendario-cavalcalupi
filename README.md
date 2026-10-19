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
