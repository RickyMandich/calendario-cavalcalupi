# Implementation Plan - Gestione Calendario Cavalcalupi

## 1. Architettura & Stack Tecnologico
- **Framework Backend:** Laravel (versione più recente compatibile PHP 8.2+)
- **Database:** MariaDB
- **Frontend / UI:** Tailwind CSS + BladewindUI per i componenti UI blade
- **Pacchetti Composer:**
  - `spatie/laravel-permission` (Gestione ruoli e permessi)
  - `opcodesio/log-viewer:^3.24` (Interfaccia per consultazione log di sistema, riservata ad Admin)
  - `spatie/icalendar-generator` (Generazione e formattazione feed ICS)

---

## 2. Schemi Database e Migrazioni

### 2.1 Tabella `users`
- Modello predefinito Laravel integrato con il trait `HasRoles` di Spatie.
- `id` (bigIncrements)
- `name` (string)
- `email` (string, unique)
- `password` (string)
- `ics_token` (string, 64 char, unique, nullable - generato alla creazione dell'utente per l'URL del feed ICS)
- `timestamps`

### 2.2 Tabella `groups`
- `id` (bigIncrements)
- `name` (string)
- `is_active` (boolean, default: `true`)
- `timestamps`

### 2.3 Tabella Pivot `group_user`
- `id` (bigIncrements)
- `user_id` (foreignId -> `users.id` cascadeOnDelete)
- `group_id` (foreignId -> `groups.id` cascadeOnDelete)
- `timestamps`
- Unique constraint su `(user_id, group_id)`

### 2.4 Tabella `events`
- `id` (bigIncrements)
- `title` (string)
- `description` (text, nullable)
- `start_time` (dateTime)
- `end_time` (dateTime)
- `type` (enum: `'session'`, `'assembly'`, `'association_event'`)
- `group_id` (foreignId -> `groups.id`, nullable, cascadeOnDelete)
- `created_by` (foreignId -> `users.id`, cascadeOnDelete)
- `timestamps`

### 2.5 Tabella `event_logs`
- `id` (bigIncrements)
- `event_id` (foreignId -> `events.id` cascadeOnDelete)
- `user_id` (foreignId -> `users.id` cascadeOnDelete)
- `action` (enum: `'created'`, `'updated'`, `'rescheduled'`)
- `old_data` (json, nullable)
- `new_data` (json, nullable)
- `timestamps`

---

## 3. Validazioni e Business Logic Models

### 3.1 Model `Group`
- Scope `scopeActive($query)`: filtra per `is_active = true`.
- Relazione `users()`: `belongsToMany(User::class)`.

### 3.2 Model `Event`
- Relazione `group()`: `belongsTo(Group::class)`.
- Relazione `creator()`: `belongsTo(User::class, 'created_by')`.
- Relazione `logs()`: `hasMany(EventLog::class)`.
- **Validazione Personalizzata / Observer:**
  - Se `type === 'session'`, `group_id` **deve** essere presente e referente a un gruppo con `is_active == true`.
  - Se `type !== 'session'`, `group_id` **deve** essere `null`.

### 3.3 Gestione Assegnazione Utenti a Gruppo
- Impedire l'assegnazione di un utente a un gruppo con `is_active == false`.

---

## 4. Scheduler & Comando Artisan

### 4.1 Comando Artisan
Crea il comando `app:deactivate-groups`:
```php
// logic in Command handle()
$cutoffDate = Carbon::create(now()->year, 8, 15, 0, 0, 0, 'Europe/Rome');

Group::where('is_active', true)
    ->where('created_at', '<', $cutoffDate)
    ->update(['is_active' => false]);
```

### 4.2 Scheduler Config
In `routes/console.php` (o `app/Console/Kernel.php` a seconda della versione Laravel):
```php
Schedule::command('app:deactivate-groups')
    ->yearlyOn(8, 15, '00:00')
    ->timezone('Europe/Rome');
```

---

## 5. Autenticazione, Ruoli e Middleware

### 5.1 Definizione Ruoli (Spatie)
- **`ADMIN`**: Accesso completo a tutte le risorse, inclusa la rotta `/log-viewer`.
- **`CD`**: Accesso alla gestione gruppi (creazione/disattivazione), assegnazione membri, creazione/modifica assemblee ed eventi associativi.
- **`Membro`**: Accesso in sola lettura al calendario, modifica/spostamento orari e date delle sessioni dei **propri** gruppi di gioco attivi.

### 5.2 Mappatura Middleware Rotte
- `/log-viewer` -> `middleware(['auth', 'role:ADMIN'])`
- `/admin/groups/*`, `/admin/users/*` -> `middleware(['auth', 'role:ADMIN|CD'])`
- `/events/assembly/*`, `/events/association/*` -> `middleware(['auth', 'role:ADMIN|CD'])`
- `/events/session/{event}/edit` -> `middleware(['auth', 'can:update-session,event'])`

---

## 6. Feed ICS Dinamico / Personalizzato

### 6.1 Rotta Pubblica ICS
- `GET /calendar/ics/{token}` (Senza middleware `auth` di sessione, validato tramite `ics_token`).

### 6.2 Logica del Controller (`IcsCalendarController`)
1. Trova l'utente tramite `ics_token` (se non esiste -> `404`).
2. Recupera gli ID dei gruppi attivi di cui l'utente fa parte.
3. Query eventi:
   ```php
   Event::where(function($q) use ($userGroupIds) {
       $q->where('type', 'session')->whereIn('group_id', $userGroupIds);
   })->orWhereIn('type', ['assembly', 'association_event'])->get();
   ```
4. Formatta ogni evento con la libreria `spatie/icalendar-generator`:
   - Configura la Timezone `Europe/Rome`.
   - Imposta Start Date e End Date.
   - Title e Description.
5. Restituisce la risposta HTTP con header `Content-Type: text/calendar; charset=utf-8` e `Content-Disposition: attachment; filename="cavalcalupi-calendar.ics"`.

---

## 7. Interfaccia Grafica e UI (BladewindUI + Tailwind)

### 7.1 Dashboard e Vista Calendario (`/calendar`)
- **Filtro Interfaccia:** Switch/Toggle con state AlpineJS o parametro query string `?filter=my-groups|all`.
  - `all`: Mostra tutte le sessioni, assemblee ed eventi associativi.
  - `my-groups`: Mostra solo le sessioni dei propri gruppi e le assemblee/eventi associativi.
- **Pulsante Feed ICS:**
  - Bottone per copiare negli appunti il link personalizzato `.ics` dell'utente (`route('calendar.ics', Auth::user()->ics_token)`).

### 7.2 Storico e Log Modifiche Sessione
- All'interno del dettaglio/modale di una sessione di gioco, sezione **"Storico Modifiche"**.
- Tabella/Lista stilizzata con BladewindUI mostrando:
  - Data/Ora modifica
  - Utente che ha effettuato la modifica
  - Tipo azione (Creata, Modificata, Riprogrammata)
  - Diff orari o dati precedente/nuovo.