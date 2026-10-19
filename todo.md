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