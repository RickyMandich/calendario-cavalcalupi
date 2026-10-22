# Implementation Plan - Spostamento diretto e scambio di sessioni (Fase 18)

**Riferimento:** `analisi-finale-modifiche.md` sez. 8 e 9.
**Dipendenze:** `permessoGestioneSessioni` (Gate `manage-group-sessions`, `update-session`), `stanzeERegoleCalendario` (`CalendarScheduler`, `withLock`).

## 1. Obiettivo
1. **Spostamento diretto:** chi ha il permesso sposta una sessione del proprio gruppo in un giorno con slot libero, senza approvazioni (già coperto dal form di modifica + servizio; qui si rifinisce).
2. **Scambio:** se il giorno è pieno, i due gruppi si accordano fuori dal sito e ciascun responsabile invia una **richiesta di scambio**; quando le due richieste combaciano a specchio le sessioni cambiano giorno **in un'unica transazione**. Ogni sessione tiene il proprio orario e la propria durata.
3. Richieste pendenti senza scadenza, **una pendente per sessione**; non occupano slot né toccano il calendario.
4. Email di conferma ai due utenti che hanno inviato le richieste (in coda, dopo il commit).

## 2. Stato attuale (da codice)
- Il form `events/edit` modifica `start_time`/`end_time` e, dopo il piano stanze, valida la capienza tramite `CalendarScheduler::updateEvent`.
- `EventObserver::updating` crea il log (`rescheduled` se cambiano le date) con `old_data`/`new_data`; `events/show.blade.php` stampa ogni campo di `new_data` come `campo: vecchio → nuovo`.
- Worker con `queue:work` già in produzione; `QUEUE_CONNECTION=database`; tabella `jobs` presente. `.env.example`: `MAIL_MAILER=log`.
- Nessuna tabella per le richieste, nessuna `Notification`.

## 3. Decisioni di progetto
- **Abbinamento a specchio.** La richiesta R_A (sessione A, `from_date` → `target_date`) si abbina a R_B se: gruppi diversi, entrambe `pending`, `R_B.from_date == R_A.target_date` e `R_B.target_date == R_A.from_date`, e le sessioni stanno ancora alle rispettive `from_date`. Più candidati: la richiesta più vecchia.
- Nello scambio cambia **solo la data**: la nuova `start_time` = data di destinazione + ora di inizio **propria**; la durata resta quella originale. La capienza non cambia (un posto liberato, uno occupato per giorno), quindi non si ricontrolla; si ricontrollano le **assemblee** (con l'orario mantenuto).
- Conflitto con un'assemblea al momento dell'abbinamento: nessuna modifica, **entrambe le richieste restano pendenti**, l'utente che ha inviato la seconda riceve un errore esplicativo.
- Sessioni nel **passato** non spostabili né scambiabili dai non-staff; la data di destinazione deve essere ≥ oggi.
- Una richiesta si può inviare anche se il giorno di destinazione ha posto (serve per formalizzare accordi già presi); l'interfaccia suggerisce lo spostamento diretto in quel caso.
- Se la sessione viene spostata/modificata nella data dopo l'invio, la richiesta pendente si annulla da sola (`cancel_reason = session_changed`).
- Contesto nel log: JSON `new_data['_context']` (nessuna modifica all'enum `action`).

## 4. Passi

### 4.1 Database
1. `php artisan make:migration create_session_swap_requests_table`:
   - `id`; `event_id` (`foreignId` → `events`, `cascadeOnDelete`); `group_id` (→ `groups`, `cascadeOnDelete`); `requested_by` (→ `users`, `cascadeOnDelete`);
   - `from_date` (`date`), `target_date` (`date`);
   - `status` enum `pending|completed|cancelled`, default `pending`;
   - `matched_request_id` (nullable, FK self, `nullOnDelete`);
   - `completed_at`, `cancelled_at` (timestamp nullable); `cancelled_by` (nullable FK `users`, `nullOnDelete`); `cancel_reason` (string nullable);
   - `pending_flag` (`unsignedTinyInteger` nullable): vale `1` se `pending`, `NULL` altrimenti;
   - `timestamps`; unique `(event_id, pending_flag)` (MariaDB e SQLite ammettono più `NULL`: a una sessione può restare una sola pendente); indice su `status`.

### 4.2 Modello
2. `app/Models/SessionSwapRequest.php`: `$fillable`, casts (`from_date`/`target_date` `date`, `completed_at`/`cancelled_at` `datetime`); relazioni `event`, `group`, `requester`, `canceller`, `matchedRequest`; `scopePending` (`status = pending`); metodi `markCompleted(self $other)` e `markCancelled(?User $by, string $reason)` che impostano stato, timestamp e azzerano `pending_flag`.

### 4.3 Contesto nel log
3. `Event`: proprietà pubblica reale `public ?array $logContext = null;` (dichiarata, quindi non passa dal `__set` di Eloquent e non viene salvata).
4. `EventObserver::updating`: se `$event->logContext` non è nullo, aggiungere `$newData['_context'] = $event->logContext`.
5. `EventObserver::updated(Event $event)` (nuovo): se `$event->wasChanged('start_time')`, annullare le richieste pendenti di quell'evento (`SessionSwapRequest::pending()->where('event_id', $event->id)->get()->each->markCancelled(null, 'session_changed')`). Lo scambio marca le richieste `completed` **prima** di aggiornare gli eventi, così questo hook non le trova.
6. `events/show.blade.php`: nel ciclo dello storico saltare la chiave `_context` e, se presente, stampare una riga "🔁 Scambio con «{titolo}» ({gruppo})" usando i campi di contesto.

### 4.4 Regola "passato" nel servizio stanze
7. `CalendarScheduler::updateEvent(Event $event, array $attrs, ?User $actor = null)`: se `$actor` non è staff e cambiano le date, rifiutare con `SchedulingException` ("Non si possono spostare sessioni già iniziate o verso il passato.") quando `start_time` originale o nuova è < `now()`. Il controller passa `Auth::user()`.

### 4.5 Servizio `app/Services/SessionSwapService.php`
8. `request(Event $event, CarbonInterface $targetDate, User $by): array` (ritorna `['request' => ..., 'status' => 'pending|completed|conflict', 'message' => ...]`). Tutto dentro `CalendarScheduler::withLock` (**il lock prima di ogni altra query**):
   - autorizzazione: `Gate::forUser($by)->authorize('update-session', $event)`;
   - validazioni → `SchedulingException`: evento di tipo `session` con gruppo attivo; non nel passato; `target_date` ≥ oggi e diversa dalla data attuale; la sessione non ha già una richiesta pendente ("Annulla prima la richiesta in corso");
   - crea la richiesta (`from_date` = data attuale dell'evento, `pending_flag = 1`);
   - chiama `tryMatch($request)`.
9. `tryMatch(SessionSwapRequest $r)`: cerca i candidati come da sez. 3 (`from_date`/`target_date` incrociate, gruppo diverso, `pending`), li filtra con gli eventi ancora alla loro `from_date` e gruppo attivo, ordina per `id`; se nessuno → stato `pending` ("Richiesta registrata: in attesa che l'altro gruppo invii la richiesta speculare"). Se esiste: `executeSwap($r, $candidate)`.
10. `executeSwap`:
    - ricarica i due eventi con `lockForUpdate()` e riverifica date e gruppi;
    - calcola per ciascuno `newStart = targetDate->copy()->setTime(h, m, s di start_time)` e `newEnd = newStart->copy()->addSeconds(durata)` (durata = differenza assoluta in secondi tra `end_time` e `start_time`);
    - `CalendarScheduler::overlapping(newStart, newEnd, ['assembly'], $eventPropria)` per entrambi; se c'è conflitto → ritorna `conflict` senza modificare nulla;
    - marca **prima** le due richieste `completed` (con `matched_request_id` incrociati);
    - imposta `$a->logContext`/`$b->logContext` (`['reason' => 'swap', 'swap_with_event_id' => ..., 'swap_with_title' => ..., 'swap_with_group' => ..., 'request_ids' => [...]]`) e aggiorna gli eventi con `save()` (gli observer registrano due righe `rescheduled`);
    - dopo il commit invia `SessionSwapped` ai due richiedenti (vedi 4.6).
11. `cancel(SessionSwapRequest $r, User $by)`: dentro `withLock`; solo se `pending`; autorizzazione = staff, richiedente, oppure `manage-group-sessions` sul gruppo; `markCancelled($by, 'manual')`.

### 4.6 Notifica email
12. `php artisan make:notification SessionSwapped`: `implements ShouldQueue`, `use Queueable`, `public bool $afterCommit = true;` costruttore con evento, vecchia data/ora, nuova data/ora, titolo e gruppo dell'altra sessione. `via()` → `['mail']`. `toMail()`: oggetto "Scambio sessione confermato"; righe: "La sessione «X» del gruppo Y passa da {giorno data ora} a {giorno data ora}", "Scambio concordato con il gruppo Z", pulsante verso `route('events.show', $event)`. Testi in italiano, date formattate `d/m/Y H:i`. Destinatario: `$request->requester` di ciascuna richiesta (`->notify(...)`).
13. Produzione: impostare `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` nel `.env` (montato anche dal worker). Finché è `log` le email finiscono nel log applicativo. Decidere il provider SMTP prima del rilascio e annotarlo in `README`.

### 4.7 Controller, rotte, viste
14. `SessionSwapController`: `store(Request $request, Event $event)` (valida `target_date` come `date`, chiama `SessionSwapService::request`, flash di successo per `pending`/`completed`, errore per `conflict`/`SchedulingException`), `destroy(SessionSwapRequest $swapRequest)`, `index()` (elenco richieste pendenti di tutti i gruppi attivi, visibile a ogni utente autenticato: serve ai gruppi per vedere cosa è in attesa; pulsante "Annulla" dove consentito).
15. Rotte `auth`: `POST /events/{event}/swap-requests` (`swap-requests.store`), `DELETE /swap-requests/{swapRequest}` (`swap-requests.destroy`), `GET /swap-requests` (`swap-requests.index`).
16. `events/show.blade.php`: per chi può `update-session` su una sessione futura, sezione "Spostamento": link "Sposta direttamente" (form di modifica), modulo "Richiedi scambio" (`<input type="date">` + pulsante) con testo esplicativo del flusso, e l'eventuale richiesta pendente con "Annulla richiesta". Mostrare anche lo stato "🔁 Scambio richiesto per il {data}" a tutti.
17. `EventController::update`: se la `SchedulingException` indica giorno pieno, aggiungere al messaggio "Puoi richiedere uno scambio dalla pagina dell'evento." (il servizio espone un codice, es. `reason = 'full'`).
18. `layouts/app.blade.php`: link "🔁 Scambi" (utenti autenticati). `calendar/index.blade.php`: badge "🔁" sulle card con richiesta pendente (eager load `swapRequests` pendenti: aggiungere `Event::swapRequests(): HasMany`).

## 5. Test (`tests/Feature/SessionSwapTest.php`, `DirectMoveTest.php`)
Si usano `CreatesRooms` e `Notification::fake()`.
- Richiesta singola: crea `pending`, `pending_flag = 1`, **nessuna** modifica a eventi, log o email.
- Due richieste speculari (A gio→ven, B ven→gio): date scambiate, **orari e durate invariati**, richieste `completed` con `matched_request_id` incrociati, due log `rescheduled` con `new_data._context.reason = swap`, una `SessionSwapped` per ciascun richiedente (e solo allora).
- Richieste non speculari (date diverse) → nessun abbinamento.
- Seconda richiesta sulla stessa sessione → errore; richieste su **sessioni diverse** dello stesso gruppo → ammesse.
- Permessi: membro senza permesso → 403; permesso su A non permette di richiedere per B; CD/Admin sempre; ospite → login.
- Annullamento da richiedente e da staff; non da un estraneo; richiesta annullata non si abbina più; dopo l'annullamento si può inviare una nuova richiesta.
- Sessione spostata/modificata nella data dopo l'invio → richiesta `cancelled` con `session_changed`; sessione eliminata → richieste rimosse (cascade).
- Conflitto con assemblea sull'orario mantenuto → nessuna modifica, richieste ancora `pending`, errore mostrato.
- Sessione nel passato o `target_date` nel passato → rifiutato; gruppo disattivato → rifiutato.
- Più candidati: si abbina la richiesta con `id` minore.
- **Atomicità:** registrare in test un listener `Event::saving` che lancia eccezione per la seconda sessione; dopo `request()` la prima sessione risulta invariata e entrambe le richieste ancora `pending`.
- Spostamento diretto: giorno libero → riuscito con log `rescheduled`; giorno pieno → errore con il suggerimento dello scambio; non-staff nel passato → rifiutato; staff nel passato → consentito.
- ICS: dopo lo scambio il feed di un utente di ciascun gruppo esporta le date aggiornate (estendere `IcsCalendarControllerTest`).

## 6. Documentazione
- `README.md`: sezione "Spostamenti e scambi" con il flusso (accordo su Telegram → due richieste → scambio → email), regole (una pendente per sessione, orario invariato, assemblee), configurazione `MAIL_*`, test inclusi.
- `todo.md`: Fase 18.

## 7. Criteri di accettazione
- Scenario end-to-end in locale (due gruppi, giovedì/venerdì pieni): richiesta A → nulla cambia; richiesta B → scambio, log su entrambe, email (nel log in locale).
- Nessun modo di superare la capienza o di sovrapporsi a un'assemblea tramite scambio.
- Richieste pendenti persistono a tempo indeterminato; annullabili.
- Test verdi.

## 8. Rischi
- Email non configurate in produzione: lo scambio funziona comunque (notifica in coda, in errore nei job falliti): controllare `failed_jobs`.
- Se un giorno si libera, la richiesta pendente non diventa automaticamente uno spostamento: da annullare e rifare direttamente (documentato nell'interfaccia).

## 9. Chiusura
Rinominare in `implementationPlan-V-spostamentoDirettoESwap.md`.
