# Analisi di Progetto: Sistema Calendario Cavalcalupi

## 1. Obiettivo del Progetto

Realizzare un sistema centralizzato per la gestione del calendario dell'associazione ludica **Cavalcalupi**. Il sistema permette di tenere traccia in tempo reale di quali gruppi di gioco occupano la sede (risolvendo i problemi di spazio che costringono i gruppi a turnazioni il giovedì e il venerdì), gestendo inoltre assemblee ed eventi associativi.

---

## 2. Requisiti Funzionali e Regole di Business

### A. Utenti e Ruoli (Spatie Laravel Permission)

* **Utente Standard (Membro):**
* Fa parte di uno o più gruppi attivi.


* Può visualizzare il calendario (tutti gli eventi o filtrando solo quelli dei propri gruppi).


* Può modificare le date/orari delle sessioni dei propri gruppi.


* Ha accesso al proprio feed `.ics` personalizzato.




* **Ruolo CD (Consiglio Direttivo):**
* Interfaccia grafica gestionale per creare gruppi, assegnare persone ai gruppi, creare/modificare assemblee ed eventi associativi, e intervenire sulle sessioni di qualsiasi gruppo.




* **Ruolo Admin:**
* Oltre ai poteri del CD, ha accesso alle pagine tecniche (es. visualizzatore log tecnici) non necessarie agli utenti senza competenze informatiche.



### B. Ciclo di Vita dei Gruppi e Anno Associativo

* L'anno associativo cambia fissato al **15 agosto** (es. il 14 agosto 2026 appartiene al 2025/2026, dal 15 agosto 2026 si passa al 2026/2027).
* I gruppi vengono creati di default come **attivi** (booleano `true`).
* Un comando schedulato di Laravel (*Scheduled Command*), impostato tassativamente per il **15 agosto alle 00:00**, imposta lo stato di tutti i gruppi esistenti a non attivi (`false`), preparando l'associazione per la nuova stagione.
* Non è consentito assegnare un utente a un gruppo non attivo (creato prima del 15 agosto dell'anno in corso).

### C. Tipologie di Eventi

1. **Sessioni di Gioco:** Associate a uno specifico gruppo. Possono essere create o modificate dai membri di quel gruppo o da un membro del CD.


2. **Assemblee / Eventi Associativi:** Riguardano tutta l'associazione. Possono essere creati e modificati **esclusivamente** dai membri del CD.



### D. Tracciamento Modifiche (Log a Database)

* Ogni modifica, creazione o spostamento di una sessione di gioco viene registrata in una tabella dedicata (`event_logs`) legata al database.


* Conserva lo storico per facilitare eventuali recap o verifiche sulle modifiche effettuate dai membri.

### E. Feed ICS Personalizzato

* Ogni utente dispone di un link `.ics` univoco e personale.


* Il feed include automaticamente:
* Le sessioni dei gruppi a cui l'utente partecipa.


* Tutte le assemblee e gli eventi associativi.




* Vengono filtrate ed escluse le sessioni degli altri gruppi, evitando di intasare i calendari personali dei giocatori e garantendo la longevità del link anno dopo anno.



---

## 3. Stack Tecnologico Confermato

* **Framework Backend:** Laravel
* **Frontend:** Tailwind CSS
* **Libreria Componenti UI:** BladewindUI
* **Visualizzatore Log Tecnici (per Admin):** `opcodesio/log-viewer` (versione 3.24 via Composer)
* **Database:** MariaDB

---

## 4. Struttura del Database (Architettura Logica)

* **`users`**: Anagrafica utenti (gestita con Spatie Laravel Permission per ruoli CD e Admin).
* **`groups`**: id, name, `is_active` (boolean, default `true`), `created_at`.
* **`group_user` (Pivot)**: user_id, group_id (relazione Many-to-Many).
* **`events`**: id, title, description, start_time, end_time, type (`session`, `assembly`, `association_event`), group_id (nullable, obbligatorio se `type == session`), created_by.
* **`event_logs`**: id, event_id, user_id, action_type, old_data, new_data, created_at (per lo storico delle modifiche alle sessioni).
