# Implementation plan - Calendario: agenda di default, click verso il dettaglio, elenco per settimana, durata

**Data:** 2026-11-04 · **Versione di partenza:** `1.0.12`

## Richieste
1. Pagina calendario: la vista predefinita deve essere **Agenda** e non Mese.
2. Click su una sessione: **non aprire il popup**, andare direttamente alla vista dettaglio.
3. Vista **Elenco**: raggruppare le sessioni **per settimana**.
4. Vista dettaglio: la **durata è sbagliata** (es. «2.5 ore e 30 min» invece di «2 ore e 30 min»).

## Analisi
1. «Agenda» è l'etichetta italiana di FullCalendar per la vista `listWeek` (`@fullcalendar/core/locales/it`: `list` = «Agenda»). Oggi `initialView` è `listWeek` solo sotto 640px, altrimenti `dayGridMonth`, e `windowResize` riporta a `dayGridMonth` quando lo schermo si allarga.
2. `eventClick` in `resources/js/calendar.js` apre il modale `#eventModal`. Il feed JSON dà `extendedProps.url` (rotta `events.show`) solo agli utenti loggati: `events.show` è dietro `auth`, quindi per gli **ospiti** `url` è `null`. Decisione: se `url` c'è si naviga al dettaglio; **senza `url` (ospiti) si mantiene il modale**, altrimenti un ospite non vedrebbe più descrizione e orari (e verrebbe mandato al login). Vale per tutti i tipi di evento (sessioni, assemblee, eventi associativi), non solo per le sessioni.
3. `CalendarController::index` passa a `calendar.index` una lista piatta `$events`. Si raggruppa in controller per settimana ISO (lunedì-domenica) e la vista stampa un'intestazione per settimana («Settimana dal 3 al 9 novembre 2026», con anno/mese su entrambi gli estremi se la settimana li attraversa, più il badge «Questa settimana») seguita dalla griglia di card esistente. Il contatore del filtro resta `$events->count()`.
4. `events/show.blade.php` usa `diffInHours()` e `diffInMinutes() % 60`. Con Carbon 3 `diffInHours()` restituisce un **float** (2.5), quindi esce «2.5 ore e 30 min». Si calcola invece il totale dei minuti interi e lo si formatta in `Event::durationLabel()`: «2 ore e 30 min», «2 ore», «1 ora», «1 ora e 30 min», «45 min». Un solo punto per il calcolo, riusabile in altre viste.

## File toccati
| File | Modifica |
|---|---|
| `resources/js/calendar.js` | `initialView: 'listWeek'` sempre; toolbar `listWeek,dayGridMonth,timeGridWeek`; `windowResize` forza l'agenda solo sotto 640px, non riporta più a mese; `eventClick` naviga a `extendedProps.url`, altrimenti modale (ospiti) |
| `app/Http/Controllers/CalendarController.php` | per `?view=list` costruisce `$weeks` (inizio, fine, etichetta, eventi) |
| `resources/views/calendar/index.blade.php` | elenco a sezioni per settimana; modale invariato (solo ospiti) |
| `app/Models/Event.php` | `durationLabel()` |
| `resources/views/events/show.blade.php` | usa `durationLabel()` |
| `tests/Feature/EventDurationTest.php` (nuovo) | etichette durata (anche a cavallo di mezzanotte) e resa nella pagina dettaglio |
| `tests/Feature/CalendarEventsFeedTest.php` | raggruppamento per settimana nella vista elenco |
| `README.md`, `todo.md` | documentazione e fase 33 |

## Cosa NON cambia
Feed `/calendar/events`, endpoint disponibilità, permessi, schema DB, modale per gli ospiti, badge capienza (non si caricano nella vista agenda, come già oggi).

## Verifiche
- Test automatici: durata, raggruppamento per settimana, pagina calendario ancora con `id="calendar"`.
- Da provare a mano nel browser (non automatizzabile qui): agenda all'apertura su desktop e mobile, click su evento da loggato (va al dettaglio) e da ospite (si apre il modale), cambio vista a Mese e ritorno.
