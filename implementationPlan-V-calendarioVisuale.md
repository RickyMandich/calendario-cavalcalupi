# Implementation Plan - Calendario visuale con FullCalendar (Fase 20)

**Riferimento:** `analisi-finale-modifiche.md` sez. 10.
**Dipendenze:** `stanzeERegoleCalendario` (endpoint `/calendar/availability`), `modalitaScura` (variabili colore adattive), `spostamentoDirettoESwap` (badge richieste pendenti, opzionale).

## 1. Obiettivo
Sostituire l'elenco a card di `/calendar` con un calendario vero (mese, settimana, agenda) usando FullCalendar via npm, mantenendo: filtro "tutti / miei gruppi", pagina pubblica in sola lettura, colori per tipo evento, link a dettagli/storico e modifica. Mostrare per ogni giorno i **posti occupati/capienza** e i giorni con **assemblea**.

## 2. Stato attuale (da codice)
- `CalendarController::index` carica tutti gli eventi e li passa a `calendar/index.blade.php`, che li stampa come card (con contatore nel filtro e pulsante "Modifica" per chi può).
- `/calendar` è pubblica; `events.show` richiede login.
- Query del filtro `my-groups`: `where(...)->orWhereIn(...)` non raggruppata (corretta solo perché non ci sono altri `where`).
- Vite: `input: ['resources/css/app.css', 'resources/js/app.js']`; il `Dockerfile` esegue `npm run build` nello stage Node.
- Test esistente: `test_calendar_page_is_accessible` si aspetta il testo "Calendario Attività".

## 3. Decisioni di progetto
- **FullCalendar v6** (`@fullcalendar/core`, `daygrid`, `timegrid`, `list`, `interaction`) installato con npm e bundlato da Vite (nessuna CDN). La v6 inietta da sola i suoi stili.
- Viste: `dayGridMonth` (desktop, predefinita), `timeGridWeek`, `listWeek`; sotto i 640 px di larghezza si parte da `listWeek`. Locale `it`, settimana da lunedì.
- Gli eventi arrivano da un **feed JSON** richiesto per intervallo (`start`/`end`), non da una pagina piena di dati.
- L'elenco a card resta disponibile come vista alternativa `?view=list` (conserva "Dettagli e Storico" e "Modifica" già testati); la vista predefinita è il calendario.
- Click su un evento → **modale di dettaglio** (utile anche agli ospiti, che non possono aprire `events.show`) con link "Dettagli e storico" e "Modifica" quando consentiti.
- Il JSON non contiene dati del creatore né email: solo ciò che la pagina pubblica mostra già (titolo, gruppo, tipo, orari, descrizione).

## 4. Passi

### 4.1 Dipendenze e build
1. `npm install @fullcalendar/core @fullcalendar/daygrid @fullcalendar/timegrid @fullcalendar/list @fullcalendar/interaction` (aggiorna `package.json` e `package-lock.json`).
2. `vite.config.js`: aggiungere `'resources/js/calendar.js'` agli `input`. Il `Dockerfile` non cambia (copia già `package*.json`, `vite.config.js`, `resources/`).

### 4.2 Backend
3. `CalendarController::events(Request $request): JsonResponse` (rotta pubblica `GET /calendar/events`, nome `calendar.events`):
   - valida `start` e `end` (`required|date`), `filter` (`in:all,my-groups`), intervallo massimo 100 giorni (422 altrimenti);
   - query `Event::with('group')->where('start_time', '<', $end)->where('end_time', '>', $start)` con filtro **raggruppato in closure**:
     ```php
     ->when($filter === 'my-groups' && $user, fn ($q) => $q->where(fn ($q) => $q
         ->where(fn ($q) => $q->where('type', 'session')->whereIn('group_id', $activeGroupIds))
         ->orWhereIn('type', ['assembly', 'association_event'])))
     ```
   - risposta: array di oggetti `{ id, title, start, end, classNames: ["fc-evt-session"], extendedProps: { type, typeLabel, group, description, url (solo se autenticato), editUrl (solo se `can`), swapPending } }`; `start`/`end` in ISO 8601 con offset `Europe/Rome` (`->toIso8601String()`);
   - titolo mostrato: per le sessioni "Gruppo · Titolo".
4. `CalendarController::index`: se `view=list` carica gli eventi come oggi (card), altrimenti non carica eventi e passa solo `filter`, `user` e le URL dei feed. Il contatore del filtro compare solo nella vista elenco.
5. Rotta di disponibilità: **riuso** di `GET /calendar/availability` del piano stanze (`start`, `end`).

### 4.3 Frontend
6. `resources/js/calendar.js`:
   - `new Calendar(el, { plugins: [...], locale: itLocale, firstDay: 1, initialView, headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listWeek' }, buttonText: {...}, height: 'auto', events: { url: el.dataset.eventsUrl, extraParams: () => ({ filter: el.dataset.filter }) }, eventClick: openEventModal, datesSet: loadAvailability })`;
   - responsive: `windowResize` cambia vista tra `listWeek` e `dayGridMonth` al superamento dei 640 px;
   - `loadAvailability(info)`: `fetch(availabilityUrl + '?start=...&end=...')`, poi per ogni `[data-date="YYYY-MM-DD"]` aggiunge/aggiorna un badge `.fc-day-capacity` ("2/3", classe `is-full` se pieno, `is-over` se sovraffollato) solo se la capienza > 0, e una classe `has-assembly` ai giorni con assemblea; ricalcolo a ogni cambio di intervallo;
   - `openEventModal(info)`: costruisce il contenuto con `textContent` (mai `innerHTML` con dati utente), mostra titolo, tipo, gruppo, data e orario, descrizione, link "Dettagli e storico" (se `url`), "Modifica" (se `editUrl`); chiusura con pulsante, Esc e click sull'overlay (classe `.modal-overlay` del piano tema scuro).
7. `resources/views/calendar/index.blade.php`:
   - mantenere banner, pulsanti ICS/Nuova sessione, barra filtro (link con `filter`) e legenda tipi;
   - sostituire la griglia di card con `<div id="calendar" data-events-url="{{ route('calendar.events') }}" data-availability-url="{{ route('calendar.availability') }}" data-filter="{{ $filter }}">`, un tab "Calendario | Elenco" (`?view=list`), e il modale di dettaglio;
   - caricare lo script con `@vite('resources/js/calendar.js')` dentro `@push('scripts')`, solo nella vista calendario.
8. `resources/css/app.css`: stile FullCalendar tramite variabili `--fc-*` costruite sui token del sito, così seguono la modalità scura:
   ```css
   .fc {
     --fc-border-color: var(--color-brand-100);
     --fc-page-bg-color: var(--color-white);
     --fc-neutral-bg-color: var(--color-brand-50);
     --fc-today-bg-color: color-mix(in oklab, var(--color-brand-100) 60%, transparent);
     --fc-button-bg-color: var(--color-brand-600);
     --fc-button-border-color: var(--color-brand-600);
     --fc-button-hover-bg-color: var(--color-brand-700);
     --fc-button-active-bg-color: var(--color-brand-800);
     --fc-list-event-hover-bg-color: var(--color-brand-50);
   }
   .fc-evt-session { background: var(--color-brand-600); border-color: var(--color-brand-700); }
   .fc-evt-assembly { background: var(--color-rose-500); border-color: var(--color-rose-600); }
   .fc-evt-association { background: var(--color-amber-500); border-color: var(--color-amber-600); }
   .fc-day-capacity { ... piccolo badge in alto a destra; .is-full { rose }, .is-over { rosso bordato } }
   .fc .has-assembly { box-shadow: inset 0 0 0 2px var(--color-rose-500); }
   ```
   Verificare nei due temi il testo degli eventi (bianco su sfondo pieno) e i pulsanti della toolbar.
9. Accessibilità: i badge di capienza hanno `title`/`aria-label` ("2 posti occupati su 3"); i colori non sono l'unico indicatore (testo "Pieno" nel badge, bordo per l'assemblea).

## 5. Test (`tests/Feature/CalendarEventsFeedTest.php`)
- Ospite: `GET /calendar/events?start=...&end=...` → 200 JSON con gli eventi dell'intervallo; eventi fuori intervallo esclusi; eventi a cavallo dell'intervallo inclusi.
- Forma del JSON: chiavi attese, `start` con offset `+01:00`/`+02:00` corretto, nessuna email né dati del creatore, `url`/`editUrl` assenti per gli ospiti e presenti per chi può.
- Filtro `my-groups` (utente autenticato): contiene le sue sessioni attive + assemblee + eventi associativi, **non** le sessioni di altri gruppi; con filtro `all` contiene tutto.
- Parametri mancanti/non validi o intervallo > 100 giorni → 422.
- `GET /calendar` contiene `id="calendar"` e "Calendario Attività"; `GET /calendar?view=list` mostra ancora le card (`Dettagli e Storico`).
- `GET /calendar/availability` coerente con il feed (capienza/occupati per giorno) — già coperto dal piano stanze, qui una verifica di integrazione.
- Build: `npm run build` ok e il file `calendar` compare nel manifest Vite.

## 6. Verifica manuale
- Viste mese/settimana/agenda; passaggio automatico a elenco su schermo stretto.
- Badge capienza corretti su giovedì/venerdì con le stanze demo; giorno pieno e giorno con assemblea ben evidenziati; giorni senza stanze senza badge.
- Modale: ospite non vede il link "Dettagli"; membro con permesso vede "Modifica".
- Entrambi i temi (chiaro/scuro), compresi eventi e toolbar.
- Prestazioni: navigazione tra mesi fluida (una richiesta per feed + una per disponibilità).

## 7. Documentazione
- `README.md`: stack (FullCalendar), struttura del feed JSON, vista elenco, nuovi endpoint e test.
- `todo.md`: Fase 20.

## 8. Criteri di accettazione
- `/calendar` mostra il calendario interattivo, pubblico, con filtro e legenda; l'elenco resta accessibile con `?view=list`.
- I giorni mostrano capienza/occupazione e assemblee; gli eventi usano date effettive (`start_time`/`end_time`).
- Nessun dato sensibile nel JSON; nessun `innerHTML` con dati utente.
- Test verdi, build Docker ok.

## 9. Rischi
- Dimensione del bundle: caricare `calendar.js` solo nella pagina del calendario (già previsto).
- Se il piano scambi non è ancora fatto, `swapPending` resta `false` (campo opzionale).

## 10. Chiusura
Rinominare in `implementationPlan-V-calendarioVisuale.md`.
