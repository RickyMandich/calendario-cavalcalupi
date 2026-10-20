# Analisi finale delle modifiche - Calendario Cavalcalupi

**Data:** 20 ottobre 2026 (aggiornata dopo le risposte ai punti aperti)
**Fonte:** export della chat ChatGPT "Analisi piano modifiche" (README, piani già eseguiti, `todo.md`, `situazione-di-partenza.md`) + tutte le correzioni e decisioni date da Riccardo. Dove l'analisi automatica di ChatGPT contraddice le correzioni, **valgono le correzioni**. Il file `result.json` (chat Telegram con Vlad) non è stato riletto: le posizioni di Vlad sono quelle riportate da Riccardo.

> Nota: questa analisi è basata sulla documentazione, non sul codice. Nomi di file, classi e colonne esistenti vanno verificati sul repository prima di scrivere le migrazioni (fase 0).

---

## 1. Decisioni finali

| Tema | Decisione |
|---|---|
| Registrazione | **Pubblica, resta attiva.** Chi si registra diventa `Membro`, senza gruppi né altri permessi. |
| Ruoli | Il primo Admin si assegna da shell (ssh); da lì **Admin e CD assegnano i ruoli agli altri utenti da interfaccia grafica**. Admin e CD hanno **gli stessi permessi**: l'unica differenza è che l'Admin ha in più l'accesso alla pagina dei log (`/log-viewer`). |
| Assegnazione ai gruppi | Solo CD/Admin. |
| Account vecchi | **Nessuna cancellazione** e nessuna funzione di pulizia. |
| Chi modifica le sessioni | **Permesso per gruppo** ("può gestire le sessioni"). CD e Admin lo hanno sempre e lo assegnano a una o più persone **membri di quel gruppo**; non è obbligatorio, lo si assegna quando il gruppo parte. |
| Responsabile / master | Il sito **non gestisce il master**. "Responsabile" = persona con quel permesso, valido **solo nel gruppo** per cui è assegnato. |
| Spazi | **Stanze configurabili** (giorni utilizzabili + quanti gruppi contengono), solo per contare gli slot giornalieri. **Nessuna assegnazione stanza ↔ sessione.** |
| Giorni senza stanze | Non possono avere **né sessioni né assemblee**. Gli eventi associativi sì (possono essere fuori sede). |
| Data piena | Non si può creare/spostare una sessione in un giorno senza slot liberi. |
| Assemblee | Durante un'assemblea (**sovrapposizione di orario**) non ci possono essere altri eventi. |
| Eventi associativi | **Non contano sulla capienza e non bloccano** nulla. |
| Scambio | Accordo tra gruppi su Telegram; nel sito ciascun responsabile invia **una richiesta di scambio**; quando le due richieste combaciano lo scambio avviene. Ogni sessione **cambia solo giorno, l'orario resta**. |
| Richieste | **Una pendente per sessione** (non per gruppo), senza scadenza, finché abbinate o annullate. Non occupano slot né modificano il calendario. |
| Notifiche | Alla conferma dello scambio, **email ai due utenti che hanno inviato le richieste**. Telegram e preferenze per utente: dopo. |
| Tema | **Modalità scura** da aggiungere: stessa identità verde/bianco, colori principali invertiti. |
| Calendario visuale | **FullCalendar** (approvato, in assenza di alternative migliori). |
| Pagina pubblica | **Già pubblica**, mostra tutte le info. Nessuna nuova pagina settimanale. |
| Import Excel | Solo il **calendario**. Gruppi creati prima; utenti esclusi (si registrano da soli). |
| Telegram | Messo da parte. |

### Cosa è stato superato rispetto alle analisi precedenti
- ~~Registrazione solo da CD~~ → registrazione pubblica.
- ~~Ogni membro del gruppo può modificare le sessioni~~ → serve il permesso per gruppo (**cambia la regola di `situazione-di-partenza.md`**).
- ~~Capienza per giorno della settimana (`weekday_capacities`)~~ → stanze.
- ~~Data prevista / effettiva / richiesta~~ → non servono: lo scambio si abbina "a specchio", `start_time`/`end_time` restano la data effettiva e lo storico sta in `event_logs`.
- ~~Una richiesta pendente per gruppo~~ → una per sessione.
- ~~Disponibilità allo scambio dichiarata nel sito~~ → l'accordo è su Telegram.
- ~~Pagina pubblica settimanale, pulizia account, bot Telegram~~ → fuori ambito.

---

## 2. Stato attuale da preservare

Già implementato e da non riscrivere: Laravel 12 + MariaDB + Tailwind v4 + BladewindUI + Vite; ruoli Spatie `ADMIN` / `CD` / `Membro`; gruppi con `is_active` e disattivazione al 15 agosto (`app:deactivate-groups` + `schedule:work` nel worker); eventi `session` / `assembly` / `association_event`; `EventObserver` → `event_logs`; feed ICS per token; calendario con filtro "tutti / miei gruppi"; palette verde/bianco con scala `brand`, icone da SVG; Docker prod/dev allineato a unlimiteddb; log-viewer solo Admin.

Verifiche aperte da `todo.md`: avvio `docker-compose.dev.yml` su `localhost:8000` e risposta 200 di `/build/icons/favicon.ico`, `/build/icons/site.webmanifest`, `/icon.svg`.

---

## 3. Sicurezza: da sistemare subito (P0)

Con la registrazione pubblica diventa critico un dettaglio del README: esistono **utenti demo con password `password`** (admin, cd, membro) e le rotte `/quick-login/{admin|cd|membro}` con i pulsanti in `/login`.
- In produzione chiunque potrebbe entrare come Admin. Le rotte quick-login vanno **rimosse o abilitate solo con `APP_ENV=local`**; il seeder demo non deve girare in produzione e gli account demo non devono esistere nel DB di produzione.
- La registrazione deve creare l'utente **solo come `Membro`**, senza gruppi; campi non mass-assignable (in particolare ruoli e `ics_token`).
- Valutare un throttle sulla rotta di registrazione (anti-spam).
- La pagina pubblica non deve esporre `ics_token`, email né lo storico `event_logs`.

---

## 4. Ruoli e permessi

### 4.1 Gestione ruoli da interfaccia (nuovo)
- **Bootstrap:** il primo Admin si crea da shell. Per non dipendere da tinker a mano, comando Artisan `app:make-admin {email}` (assegna il ruolo `ADMIN` a un utente esistente), da lanciare via ssh/`docker compose exec`.
- **Pagina "Utenti"** (area amministrativa, `role:ADMIN|CD`): elenco utenti con ricerca, ruolo attuale, cambio ruolo (`Membro` / `CD` / `ADMIN`).
- Chi può assegnare ruoli: **Admin e CD, con gli stessi poteri**. Differenza unica tra i due ruoli: `/log-viewer` resta riservato all'Admin.
- Protezioni: deve restare sempre almeno un utente `ADMIN` (non si può rimuovere l'ultimo); ogni cambio ruolo va registrato (chi, a chi, da/a) nel log applicativo.

### 4.2 Permesso "gestione sessioni" per gruppo
**Modello:** colonna booleana `can_manage_sessions` (default `false`) sulla pivot `group_user`. Non è un permesso Spatie globale perché deve valere solo per un gruppo; Spatie resta per i ruoli.

Conseguenze:
- vale solo per quel gruppo (chi è in due gruppi ma responsabile di uno solo gestisce solo quello);
- può esserci più di una persona per gruppo;
- uscendo dal gruppo il permesso sparisce (riga pivot rimossa);
- i gruppi cambiano ogni anno e a fine stagione sono inattivi: nessuna ereditarietà tra stagioni.

**Regole (Policy/Gate, sempre lato server):**
- CD e Admin: sempre autorizzati.
- Altri: autorizzati solo con `can_manage_sessions = true` **sul gruppo della sessione** e gruppo **attivo**.
- Impostabile solo su membri del gruppo e solo da CD/Admin.
- Si applica a: creare/modificare/spostare sessioni, inviare e annullare richieste di scambio delle sessioni del proprio gruppo.
- Tutti i membri continuano a vedere calendario e ICS.

**UI:** in `admin/groups/index`, accanto a ogni membro, interruttore "Può gestire le sessioni" (solo CD/Admin); nella scheda gruppo si vede chi sono i responsabili.

**Migrazione:** default `false`. Dopo il deploy nessun membro non-CD può modificare sessioni finché CD/Admin non assegna il permesso (i dati attuali sono di test/pochi: nessun trasferimento automatico).

**Impatti:** Gate `update-session` e test `AuthorizationGatesTest`, `EventAndGroupLogicTest`, `CalendarAndWebRoutesTest`; il seeder demo deve dare il permesso al membro demo in almeno un gruppo; README (tabella ruoli) e `situazione-di-partenza.md`.

---

## 5. Stanze e capienza giornaliera

**Dati:**
- `rooms`: `id`, `name`, `capacity` (gruppi contenibili, intero ≥ 1, default 1), `is_active` (default `true`), `timestamps`.
- `room_weekday`: `room_id`, `weekday` (1-7 ISO, 1 = lunedì), unique `(room_id, weekday)`.

**Capienza di una data** = somma di `capacity` delle stanze **attive** utilizzabili nel giorno della settimana (fuso `Europe/Rome`). Esempio: A (gio, ven, 1) + B (gio, 2) + C (ven, 1) → giovedì 3, venerdì 2.

**Slot occupati** = numero di eventi `session` con `start_time` in quella data, escludendo l'evento che si sta modificando. Una richiesta di scambio pendente non occupa nulla.

**Regole per tipo di evento:**

| Tipo | Serve un giorno con stanze? | Consuma slot? | Blocca altri eventi? |
|---|---|---|---|
| Sessione | Sì | Sì | No |
| Assemblea | Sì | No | Sì: nessun altro evento in sovrapposizione oraria |
| Evento associativo | No (può essere fuori sede) | No | No |

- Una sessione può stare in una data solo se `occupati < capienza`. Vale per **creazione, modifica, spostamento, scambio e import**.
- Giorno senza stanze utilizzabili = capienza 0 = niente sessioni né assemblee.
- Se la configurazione stanze cambia, le sessioni già esistenti non vengono toccate; un giorno che risulta sovraffollato va segnalato visivamente nel calendario.

**UI (solo CD/Admin):** CRUD stanze con nome, capienza, attiva, giorni della settimana. Lo stesso calcolo alimenta il calendario ("3/3 posti" per giorno).

---

## 6. Regola delle assemblee

Durante un'assemblea non può esserci nessun altro evento. Bidirezionale e per **sovrapposizione oraria** (`start < altro.end` e `end > altro.start`):
- non si crea/sposta una sessione (o un altro evento) su un'assemblea esistente;
- non si crea/sposta un'assemblea dove esistono già altri eventi;
- un'assemblea richiede un giorno con almeno una stanza utilizzabile (sez. 5).

Applicata in ogni percorso di scrittura (form, spostamento, scambio, import). Il fatto che gli eventi associativi "non blocchino" significa che non impediscono sessioni o assemblee; il divieto di sovrapposizione con un'assemblea resta valido anche per loro (assunzione, vedi sez. 14).

---

## 7. Servizio unico per le regole del calendario

Un solo servizio (es. `App\Services\CalendarScheduler`) usato da form, scambio e import:
- `capacityFor(date)`, `occupiedSlots(date, ignoreEvent)`, `hasFreeSlot(...)`;
- `assertCanSchedule(...)` → capienza, stanze del giorno, assemblee, gruppo attivo, coerenza `type`/`group_id`;
- `move(event, newStart, newEnd, user)` e `swap(eventA, eventB, ...)`.

**Concorrenza:** ogni operazione che verifica e scrive avviene in una transazione che per prima cosa fa `Room::lockForUpdate()` (mutex globale: il traffico è minimo). Dentro il lock la disponibilità si **ricalcola** prima di scrivere. Due richieste per l'ultimo slot: una sola riesce, l'altra riceve "data ora piena". Controlli sempre lato server; l'interfaccia li replica solo per comodità.

---

## 8. Spostamento diretto

Se il giorno di destinazione ha uno slot libero e nessun conflitto con assemblee: spostamento **immediato, senza approvazioni**. Se è pieno: errore chiaro con invito a usare la richiesta di scambio. Non si spostano sessioni nel passato. L'`EventObserver` registra l'azione come `rescheduled`.

---

## 9. Scambio tra due sessioni

### Flusso
1. Gruppo A (giovedì) e gruppo B (venerdì) si accordano fuori dal sito.
2. Il responsabile di A invia la richiesta: "la mia sessione di gio X → ven Y".
3. Il responsabile di B invia quella speculare: "la mia sessione di ven Y → gio X".
4. Alla seconda richiesta il sistema le abbina e fa lo scambio in un'unica transazione.
5. Email ai due utenti che hanno inviato le richieste: gruppo, giorno precedente, giorno nuovo, altro gruppo.

Finché manca la richiesta speculare nulla cambia; la richiesta resta pendente senza scadenza.

### Abbinamento "a specchio"
Due richieste **pendenti** R_A e R_B sono abbinabili se:
- gruppi diversi, entrambi attivi;
- la data attuale della sessione di A coincide con la data di destinazione di B e viceversa;
- le date `from` salvate corrispondono ancora alla data corrente delle sessioni (altrimenti la richiesta è obsoleta);
- nessun conflitto con assemblee nelle nuove date, **considerando l'orario che ciascuna sessione mantiene**.

Se ci sono più candidati si sceglie la richiesta più vecchia. La capienza non cambia (un posto liberato e uno occupato per giorno) ma lo stato va comunque ricontrollato nella transazione. Ogni sessione cambia **solo la data**; orario e durata restano.

### Dati: `session_swap_requests`
`id`, `event_id`, `group_id`, `requested_by`, `from_date`, `target_date`, `status` (`pending` / `completed` / `cancelled`), `matched_request_id` (nullable), `completed_at`, `cancelled_at`, `cancelled_by`, `cancel_reason` (nullable), `pending_flag`, `timestamps`.

**Una sola richiesta pendente per sessione** (evita che la stessa sessione sia richiesta verso due giorni diversi con conflitti nell'abbinamento). MariaDB non ha indici parziali: `pending_flag` vale 1 se pendente e `NULL` altrimenti, con unique `(event_id, pending_flag)`, più il controllo dentro la transazione con lock. Un gruppo può invece avere **più richieste pendenti su sessioni diverse** (es. questa settimana e la prossima).

### Regole
- Invio/annullo solo con il permesso sul gruppo (o CD/Admin); il permesso su A non dà nulla su B.
- Per cambiare destinazione di una sessione si annulla la richiesta pendente e se ne invia una nuova.
- Annullamento: dal richiedente o da CD/Admin; una richiesta annullata non è più abbinabile.
- Se la sessione viene spostata/modificata/cancellata mentre la richiesta è pendente, la richiesta diventa non valida (annullata con motivo `session_changed`).
- Se il giorno di destinazione si libera, la richiesta resta pendente: si può annullare e fare uno spostamento diretto.
- In caso di errore nella transazione entrambe le sessioni restano com'erano.

### Log
Lo scambio produce due righe `rescheduled` (una per sessione). Per non alterare l'enum `action` (alter di enum su MariaDB), il contesto va nel JSON `new_data`: `swap_with_event_id`, `swap_request_ids`, `reason: "swap"`. Verificare come l'Observer può ricevere il contesto (attributo transitorio sul modello o parametro dal servizio). Creazione, annullamento e completamento delle richieste sono tracciati sulla tabella richieste (stato e date) e/o con log dedicati.

### Notifiche email
- Classe `Notification` Laravel con `via()` = `['mail']`: Telegram e preferenze per utente si aggiungeranno come canali senza toccare il flusso.
- In coda (worker già presente), **dopo il commit**.
- Destinatari: i due utenti che hanno inviato le richieste. Nessuna email alla prima richiesta, solo alla conferma.
- Serve configurare `MAIL_*` in produzione (non previsto nei piani fin qui): da scegliere il provider SMTP.

### UI
Dalla scheda sessione: "Sposta" (diretto, se c'è posto) oppure "Richiedi scambio" (se pieno); sezione "Richieste di scambio" con stato, destinazione e annulla; nel calendario le sessioni con richiesta pendente sono evidenziate.

---

## 10. Calendario visuale

FullCalendar via npm (non CDN) con Vite: viste mese/settimana/agenda, colori per tipo evento, filtro "tutti / miei gruppi" mantenuto, indicatore posti per giorno e giorni occupati da assemblee, basato su `start_time`/`end_time`. Dopo le regole di capienza (mostra dati veri) e **dopo la modalità scura**, così nasce già con entrambi i temi. Pubblico e in sola lettura per chi non è autorizzato.

---

## 11. Modalità scura (nuovo)

**Obiettivo:** tema scuro con la stessa identità verde/bianco, colori principali invertiti: sfondi verde scuro, testi chiari.

**Approccio (Tailwind v4):**
- Variante `dark` basata su classe: `@custom-variant dark (&:where(.dark, .dark *));` in `resources/css/app.css`.
- Preferenza in 3 stati: **chiaro / scuro / sistema** (default: sistema via `prefers-color-scheme`), salvata in `localStorage` (per browser; nessuna colonna DB). Piccolo script inline nel `<head>` di `layouts.app` (e delle pagine auth) che applica la classe `dark` prima del render, per evitare il flash bianco.
- Pulsante di cambio tema nell'header, visibile anche in login/registrazione.
- `theme-color` e `color-scheme` aggiornati dinamicamente.

**Mappatura colori (proposta):**

| Elemento | Chiaro | Scuro |
|---|---|---|
| Sfondo pagina | bianco | `brand-950` |
| Pannelli (`.panel`) | bianco, bordo `brand-100` | `brand-900`, bordo `brand-800` |
| Sezioni secondarie / hover | `brand-50` / `brand-100` | `brand-900` / `brand-800` |
| Titoli | `brand-950` | `brand-50` |
| Testo / secondario | `gray-900` / `gray-500` | `gray-100` / `gray-400` |
| Azione primaria (`.btn-primary`) | `brand-600` (hover 700) | `brand-500` (hover 400), testo scuro o bianco secondo contrasto |
| Azione secondaria (`.btn-secondary`) | bianco, bordo verde | trasparente/`brand-900`, bordo `brand-700` |
| Input (`.field`) | bianco, bordo verde chiaro | `brand-950`, bordo `brand-700` |
| Header / footer | bianco / `brand-800` | `brand-950` / `brand-900` |

Tipi evento: sessione = verde, assemblea = rose, evento associativo = amber restano distinti, con tonalità dedicate per il tema scuro (verificare contrasto).

**Implementazione:** le varianti `dark:` vanno nelle classi di `@layer components` (`.panel`, `.field`, `.field-label`, `.btn-primary`, `.btn-secondary`), così le viste che le usano si adeguano da sole; le utility rimaste inline nelle viste (`calendar/index`, `events/*`, `admin/*`, `auth/*`, partial) vanno riviste una per una.

**Da verificare:** il supporto dark dei componenti BladewindUI (override CSS se non seguono la classe `dark`); stile di FullCalendar (variabili CSS dedicate); icona SVG e favicon invariate (verde pieno leggibile su entrambi i temi).

**Verifica:** controllo visivo di tutte le pagine nei due temi, contrasto testo/sfondo (WCAG AA), nessun flash al caricamento, persistenza della scelta, comportamento con "sistema". README: tabella palette con colonna scura e come cambiare tema.

---

## 12. Import Excel (solo calendario)

- Input `.xlsx` / `.csv`; i **gruppi devono già esistere** (abbinamento per nome ai gruppi attivi); **nessun utente** viene creato.
- Colonne proposte: gruppo, titolo, data, ora inizio, ora fine, descrizione (opz.), tipo (opz., default `session`).
- Flusso: upload → validazione completa → **anteprima** valide/errori → conferma → inserimento in **un'unica transazione** (tutto o niente) → riepilogo.
- Stesso servizio dei form (sez. 7): capienza, stanze del giorno, assemblee, gruppo attivo; conta anche le righe dello stesso file tra loro.
- Solo CD/Admin. Libreria probabile `phpoffice/phpspreadsheet`: verificare le estensioni PHP richieste nel `Dockerfile`. Dopo le fasi principali.

---

## 13. Riepilogo database e test

| Tabella | Modifica |
|---|---|
| `group_user` | + `can_manage_sessions` boolean default `false` |
| `rooms` | nuova |
| `room_weekday` | nuova |
| `session_swap_requests` | nuova (unique `(event_id, pending_flag)`) |
| `events`, `event_logs`, `users`, `groups` | nessuna modifica di schema |

Migrazioni solo additive, mai `migrate:fresh` in produzione.

| Area | Test |
|---|---|
| Registrazione | nuovo utente solo `Membro`, senza gruppi; quick-login assente fuori da `local` |
| Ruoli | Admin e CD cambiano i ruoli, i `Membro` no; non si toglie l'ultimo Admin; `app:make-admin`; `/log-viewer` accessibile solo all'Admin (CD escluso) |
| Permesso | autorizzato solo nel proprio gruppo; non nell'altro gruppo; più autorizzati; gruppo inattivo → negato; non membro → non assegnabile; CD/Admin sempre |
| Stanze | capienza = somma stanze attive del giorno; stanza non attiva/giorno non configurato non conta |
| Capienza | giorno pieno → rifiutato; ultimo slot → accettato; la sessione modificata non conta due volte; giorno senza stanze → no sessioni né assemblee; eventi associativi ammessi |
| Assemblee | blocco bidirezionale con sovrapposizione parziale; assemblee e eventi associativi non consumano slot |
| Concorrenza | due richieste per l'ultimo slot → una sola riesce |
| Scambio | una richiesta → nulla cambia; due speculari → scambio atomico con orari invariati; non speculari → nessun abbinamento; annullata → non abbinabile; sessione cambiata → obsoleta; una pendente per sessione ma due su sessioni diverse dello stesso gruppo; permessi incrociati negati; conflitto assemblea dopo lo scambio → rifiutato |
| Log/email | due `rescheduled` con contesto; rollback → nessuna riga; email solo alla conferma, dopo il commit, ai due richiedenti |
| ICS e pagina pubblica | date aggiornate; nessun dato riservato |
| Tema scuro | preferenza persistita, nessuna regressione nel tema chiaro (controllo visivo) |
| Import | gruppi inesistenti/inattivi, capienza, assemblee, tutto-o-niente |

---

## 14. Piano di lavoro e implementation plan

Ogni punto = un `implementationPlan-{nome}.md`, rinominato `implementationPlan-V-...` a lavoro finito, con `todo.md` e README aggiornati.

0. **verificaStatoAttuale** - leggere modelli, migrazioni, policy, observer, rotte; chiudere le verifiche Docker/icone aperte.
1. **sicurezzaRegistrazioneEQuickLogin** (P0) - sez. 3.
2. **gestioneRuoliUtenti** - sez. 4.1 (comando `app:make-admin` + pagina Utenti).
3. **permessoGestioneSessioni** - sez. 4.2.
4. **stanzeERegoleCalendario** - sez. 5, 6, 7 (stanze, assemblee e servizio `CalendarScheduler` insieme).
5. **spostamentoDirettoESwap** - sez. 8, 9 (dipende da 3 e 4), incluse email e configurazione `MAIL_*`.
6. **modalitaScura** - sez. 11 (indipendente: può andare in parallelo a 3-5, ma prima del calendario visuale).
7. **calendarioVisuale** - sez. 10.
8. **importazioneCalendarioExcel** - sez. 12 (dipende da 4).

---

## 15. Assunzioni residue (confermabili in qualsiasi momento)

1. **Eventi associativi e assemblee:** un evento associativo che si sovrappone a un'assemblea viene comunque impedito (l'assemblea esclude "qualsiasi altro evento"); gli eventi associativi non bloccano sessioni né assemblee.
2. **Ruoli:** Admin e CD hanno gli stessi permessi (gruppi, stanze, permessi per gruppo, assegnazione ruoli, eventi, import); all'Admin si aggiunge solo l'accesso ai log. Ovunque nel documento "CD/Admin" indica quindi lo stesso livello di autorizzazione.
3. **Tema scuro:** preferenza salvata nel browser (non per utente nel DB); default "sistema".
4. **Sessioni nel passato:** non spostabili/scambiabili dai membri.

## 16. Fuori ambito (rinviato o scartato)

Notifiche Telegram e preferenze di canale per utente (le notifiche sono già predisposte come canali Laravel); cancellazione/pulizia account inattivi; chiusure o eccezioni per singola data; assegnazione stanza ↔ sessione; approvazioni del CD; pagina settimanale pubblica dedicata.
