# TODO List - Calendario Cavalcalupi

- [ ] **Fase 1: Setup Progetto & Pacchetti**
  - [ ] Installare `spatie/laravel-permission` e pubblicare le migrazioni.
  - [ ] Installare `opcodesio/log-viewer:^3.24`.
  - [ ] Installare `spatie/icalendar-generator`.
  - [ ] Installare e configurare BladewindUI e Tailwind CSS.

- [ ] **Fase 2: Migrazioni & Database**
  - [ ] Modificare la migrazione `users` aggiungendo la colonna `ics_token`.
  - [ ] Creare la migrazione per `groups` (`id`, `name`, `is_active`, `timestamps`).
  - [ ] Creare la migrazione pivot `group_user`.
  - [ ] Creare la migrazione per `events` (`title`, `description`, `start_time`, `end_time`, `type`, `group_id`, `created_by`, `timestamps`).
  - [ ] Creare la migrazione per `event_logs` (`event_id`, `user_id`, `action`, `old_data`, `new_data`, `timestamps`).
  - [ ] Creare Seeder per Ruoli Spatie (`ADMIN`, `CD`, `Membro`).

- [ ] **Fase 3: Modelli & Logica di Business**
  - [ ] Configurare il modello `User` (relazioni, ruoli Spatie, generazione automatica di `ics_token` all'evento `creating`).
  - [ ] Configurare il modello `Group` e gli scope per i gruppi attivi.
  - [ ] Configurare il modello `Event` includendo la validazione (Sessione deve avere `group_id` attivo, altri eventi no).
  - [ ] Configurare il modello `EventLog`.
  - [ ] Implementare un Observer o Listener Eloquent per salvare in `event_logs` le modifiche alle sessioni.

- [ ] **Fase 4: Scheduler & Disattivazione Gruppi**
  - [ ] Creare il comando Artisan `app:deactivate-groups`.
  - [ ] Configurare lo scheduler in `routes/console.php` per l'esecuzione il 15 agosto alle 00:00 (timezone Europe/Rome).

- [ ] **Fase 5: Autenticazione & Permessi**
  - [ ] Definire Policy e Gate per il controllo accessi (gestione gruppi per CD/ADMIN, modifica sessioni solo ai membri del gruppo o CD/ADMIN).
  - [ ] Proteggere la rotta `/log-viewer` per il solo ruolo `ADMIN`.

- [ ] **Fase 6: Feed ICS Personalizzato**
  - [ ] Creare la rotta ed il controller `IcsCalendarController`.
  - [ ] Implementare la logica di generazione `.ics` filtrata per utente (sessioni dei propri gruppi attivi + assemblee/eventi).

- [ ] **Fase 7: Frontend & Interfaccia Utente (BladewindUI)**
  - [ ] Creare la vista del Calendario principale con toggle per il filtro (Tutti gli eventi / Solo i miei gruppi).
  - [ ] Inserire la modale/pulsante per ottenere e copiare il link ICS personale.
  - [ ] Creare l'interfaccia di gestione Gruppi e Assegnazione Utenti (riservata a CD e ADMIN).
  - [ ] Creare le form di creazione/modifica eventi e sessioni.
  - [ ] Creare il componente per visualizzare lo storico/log delle modifiche di una sessione.