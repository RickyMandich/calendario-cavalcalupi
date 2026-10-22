# Implementation Plan - Stanze, capienza e regole del calendario (Fase 17)

**Riferimento:** `analisi-finale-modifiche.md` sez. 5, 6, 7.
**Dipendenze:** `permessoGestioneSessioni` (controller e Gate già riscritti), `gestioneRuoliUtenti` (`isStaff()`).
**Sblocca:** `spostamentoDirettoESwap`, `calendarioVisuale`, `importazioneCalendarioExcel`.

## 1. Obiettivo
1. Stanze configurabili (giorni della settimana utilizzabili + quanti gruppi contengono). Servono solo a contare gli slot per giorno: **nessuna assegnazione stanza ↔ sessione**.
2. Un unico servizio che applica le regole del calendario a ogni scrittura, con lock contro le richieste concorrenti.
3. Regole:

| Tipo | Serve giorno con stanze | Consuma slot | Impedito da |
|---|---|---|---|
| Sessione | sì | sì (1) | giorno pieno; assemblea in sovrapposizione oraria |
| Assemblea | sì | no | qualunque altro evento in sovrapposizione oraria |
| Evento associativo | no | no | solo assemblea in sovrapposizione oraria |

Giorno senza stanze utilizzabili = capienza 0.

## 2. Stato attuale (da codice)
- Nessun concetto di capienza: `EventController::store/update` salvano direttamente con `Event::create/update`.
- `Event::saving` valida solo coerenza `type`/`group_id` e gruppo attivo.
- `start_time`/`end_time`: `DATETIME` nel fuso app `Europe/Rome`.
- Test: SQLite in-memory (nessun lock reale).
- Il layout ha la navigazione admin con "Gestione Gruppi".

## 3. Decisioni di progetto
- **Le regole stanno nel servizio, non nel modello.** `Event::saving` resta com'è: i seeder, i test che usano `Event::create` direttamente e `tinker` non vengono bloccati. Tutti i percorsi applicativi (form, scambio, import) passano dal servizio. Lasciare un commento in `Event.php`.
- Il giorno di un evento = data di `start_time` nel fuso app; un evento a cavallo della mezzanotte conta sul giorno di inizio.
- Il giorno della settimana usa `dayOfWeekIso` (1 = lunedì … 7 = domenica).
- Una modifica che **non cambia** `start_time`/`end_time` non rivalida capienza/assemblee (si può correggere il titolo di una sessione in un giorno diventato sovraffollato dopo un cambio stanze).
- Mutex: dentro una transazione, **prima query** = `Room::lockForUpdate()`. Il lock deve essere la prima istruzione della transazione: con MariaDB `REPEATABLE READ` lo snapshot dei SELECT successivi nasce alla prima lettura non bloccante, quindi leggere altro prima del lock darebbe conteggi vecchi. Limite noto e accettato: senza stanze non c'è riga da bloccare, ma in quel caso sessioni e assemblee sono comunque rifiutate (capienza 0); la sola gara associativo/assemblea è trascurabile.

## 4. Passi

### 4.1 Database e modelli
1. `php artisan make:migration create_rooms_table`: `id`, `name` (string), `capacity` (`unsignedTinyInteger`, default 1), `is_active` (boolean, default true), `timestamps`.
2. `php artisan make:migration create_room_weekday_table`: `id`, `room_id` (`foreignId` → `rooms`, `cascadeOnDelete`), `weekday` (`unsignedTinyInteger`, 1-7), unique `(room_id, weekday)`. Niente timestamps.
3. `app/Models/Room.php`: `$fillable = ['name','capacity','is_active']`, casts (`is_active` boolean, `capacity` integer); `weekdays(): HasMany` verso `RoomWeekday`; `scopeActive`; `scopeUsableOn($q, int $isoWeekday)` = `whereHas('weekdays', fn ($w) => $w->where('weekday', $isoWeekday))`; `syncWeekdays(array $days)`: in transazione cancella e reinserisce i giorni validi (1-7, unici). `app/Models/RoomWeekday.php` (`$table = 'room_weekday'`, `$timestamps = false`, `$fillable = ['room_id','weekday']`).

### 4.2 Eccezione dedicata
4. `app/Exceptions/SchedulingException.php` estende `DomainException`; costruttore `(string $message, public readonly string $field = 'start_time')`. I controller la convertono in `back()->withErrors([$e->field => $e->getMessage()])->withInput()`.

### 4.3 Servizio `app/Services/CalendarScheduler.php`
5. Metodi (tutti nel fuso app; accettano `CarbonInterface`):
   - `capacityOn($date): int` → `(int) Room::active()->usableOn($date->dayOfWeekIso)->sum('capacity')`.
   - `occupiedSlotsOn($date, ?Event $ignore = null, iterable $extra = []): int`: conta `Event::where('type','session')->whereBetween('start_time', [$day->startOfDay(), $day->endOfDay()])` escludendo `$ignore` (`whereKeyNot`), più gli elementi di `$extra` (array con `type`, `start`, `end`) che cadono nello stesso giorno. `$extra` serve all'import per contare le righe dello stesso file.
   - `freeSlotsOn(...)` = capienza − occupati (min 0).
   - `overlapping($start, $end, ?array $types = null, ?Event $ignore = null, iterable $extra = [])`: eventi con `start_time < $end && end_time > $start` (estremi adiacenti **non** si sovrappongono), filtrati per tipo.
   - `validate(array $attrs, ?Event $ignore = null, iterable $extra = []): void` con `$attrs = ['type','start_time','end_time',...]` (Carbon). Regole:
     - `session`: capienza del giorno > 0 ("Nessuna stanza disponibile in questo giorno."), occupati < capienza ("Giorno pieno: X/Y posti occupati."), nessuna assemblea sovrapposta ("Nel giorno e orario scelto c'è un'assemblea.");
     - `assembly`: capienza del giorno > 0, nessun evento di alcun tipo sovrapposto ("Esistono altri eventi nello stesso orario: l'assemblea li esclude.");
     - `association_event`: nessuna assemblea sovrapposta.
   - `withLock(Closure $cb): mixed` = `DB::transaction(function () use ($cb) { Room::query()->lockForUpdate()->get(['id']); return $cb(); })` (il lock è la prima query).
   - `createEvent(array $attrs, User $by): Event`: `withLock` → `validate` → `Event::create([... 'created_by' => $by->id])`.
   - `updateEvent(Event $event, array $attrs): Event`: `withLock` → `$event->fill($attrs)`; se `isDirty(['start_time','end_time'])` → `validate($event->getAttributes()-derivati, $event)`; `save()`.
   - `dayAvailability(CarbonInterface $from, CarbonInterface $to): array`: per ogni giorno `[date, capacity, occupied, free, over_capacity, has_assembly]` (query raggruppate: una per le stanze/giorni settimana, una per le sessioni del periodo, una per le assemblee); è l'API usata da form, JSON e FullCalendar.
6. Registrare come singleton in `AppServiceProvider::register` (o lasciare l'autowiring).

### 4.4 Controller e viste
7. `EventController`:
   - iniettare `CalendarScheduler`;
   - `store`: sostituire `Event::create([...])` con `$scheduler->createEvent([...], $user)` in `try/catch (SchedulingException $e)`;
   - `update`: sostituire `$event->update($validated)` con `$scheduler->updateEvent($event, $validated)` (stesso catch).
8. `RoomController` (`index`, `store`, `update`, `destroy`), Gate nuovo `manage-rooms` = `isStaff()`. Validazione: `name` richiesto, `capacity` intero 1-20, `is_active` boolean, `weekdays` array di interi 1-7. `destroy` elimina la stanza (conferma in UI): ricordare nel messaggio che la capienza dei giorni cala.
9. Rotte (`routes/web.php`, `auth` + `admin`): `GET /admin/rooms` (`admin.rooms.index`), `POST /admin/rooms`, `PUT /admin/rooms/{room}`, `DELETE /admin/rooms/{room}`, sotto `can:manage-rooms`.
10. Rotta **pubblica** `GET /calendar/availability` (`calendar.availability`): parametri `date` oppure `start`+`end` (max 62 giorni), risposta JSON di `dayAvailability`. Riusata da `calendarioVisuale`.
11. Vista `admin/rooms/index.blade.php`: elenco stanze (nome, capienza, attiva, giorni come 7 checkbox Lun…Dom) con form di modifica per riga e modale "Nuova stanza", stile come `admin/groups/index`. Riepilogo in alto: capienza per giorno della settimana (somma delle stanze attive).
12. `layouts/app.blade.php`: link "🏠 Stanze" sotto `@can('manage-rooms')`.
13. `events/create` e `events/edit`: sotto i campi data, un riquadro "Disponibilità" aggiornato via `fetch('/calendar/availability?date=...')` al cambio di `start_time` (testo tipo "Giovedì 12/11: 2/3 posti occupati", "Giorno pieno", "Nessuna stanza in questo giorno", "Assemblea in programma"). È solo un suggerimento: il server rifà i controlli. Per lo staff, se non esiste alcuna stanza attiva, banner "Nessuna stanza configurata: le sessioni non possono essere programmate" con link alla pagina Stanze.
14. `DemoSeeder` (solo locale): tre stanze demo (A giovedì+venerdì capienza 1, B giovedì capienza 2, C venerdì capienza 1), così giovedì = 3 e venerdì = 2 come nell'esempio dell'analisi.

### 4.5 Test helper
15. `tests/Concerns/CreatesRooms.php`: `createRoom(int $capacity = 1, array $weekdays = [1,2,3,4,5,6,7], bool $active = true): Room` che crea la stanza e i giorni. Usarlo nei test che passano dal servizio.

## 5. Test
Nuovi (`tests/Feature`):
- `RoomCapacityTest`: capienza = somma delle capacità delle stanze **attive** del giorno; stanza non attiva o con quel giorno non configurato non conta; nessuna stanza = 0; mappatura giorno della settimana corretta anche per un orario serale (`2026-11-12 23:30` Europe/Rome = giovedì).
- `SchedulerRulesTest`:
  - sessione rifiutata in giorno senza stanze e in giorno pieno; accettata con l'ultimo slot libero;
  - spostando una sessione nello stesso giorno (cambio di orario) non conta due volte (`$ignore`);
  - modifica del solo titolo su giorno sovraffollato → consentita;
  - evento associativo ammesso in giorno senza stanze e che non consuma slot;
  - assemblea rifiutata in giorno senza stanze; accettata altrimenti;
  - **sovrapposizione**: sessione a cavallo dell'orario di un'assemblea → rifiutata (parziale, contenuta, contenente); estremi adiacenti (`fine == inizio`) → ammessi; evento associativo sovrapposto ad assemblea → rifiutato; assemblea sopra una sessione esistente → rifiutata (verso opposto);
  - `$extra`: due sessioni dello stesso batch in un giorno con capienza 1 → la seconda rifiutata.
- `RoomManagementTest`: `Membro` 403, CD/Admin possono creare/modificare/eliminare; validazione (capienza 0, giorno 9 → errore); `syncWeekdays` sostituisce i giorni.
- `AvailabilityEndpointTest`: risposta JSON corretta per `date` e per intervallo; intervallo > 62 giorni → 422; accessibile da ospite.
- `EventControllerSchedulingTest`: `POST /events` in giorno pieno → redirect indietro con errore su `start_time`, nessun evento creato; in giorno libero → creato; `PUT` che sposta in giorno pieno → errore e evento invariato.

Da aggiornare:
- `CalendarAndWebRoutesTest::test_event_creation_and_update_with_logging`: nel `setUp` creare una stanza aperta tutti i giorni (capienza 5) con l'helper, altrimenti la creazione via HTTP viene (correttamente) rifiutata.
- `SessionManagementPermissionTest` (piano precedente): idem.
- Test che usano `Event::create` direttamente: invariati (le regole non sono nel modello).

**Concorrenza:** SQLite ignora `lockForUpdate`. Prova manuale su MariaDB (dev Docker): con capienza 1 e un giorno libero, lanciare due richieste di creazione quasi simultanee (`xargs -P2` con `curl` autenticato, oppure due tab) e verificare che una sola riesca. Annotare l'esito nel file del piano a fine lavoro.

## 6. Documentazione
- `README.md`: nuove sezioni "Stanze e capienza" (come si calcola, tabella per tipo evento, assemblee), pagina Stanze, endpoint `/calendar/availability`, convenzione "le regole passano da `CalendarScheduler`".
- `todo.md`: Fase 17.

## 7. Criteri di accettazione
- CD/Admin configurano le stanze da interfaccia; giovedì/venerdì mostrano le capienze attese.
- Impossibile creare/spostare una sessione in un giorno pieno, senza stanze o durante un'assemblea; impossibile creare un'assemblea sopra altri eventi o in un giorno senza stanze.
- Eventi associativi liberi (tranne durante un'assemblea).
- Test verdi; prova di concorrenza manuale annotata.

## 8. Rischi e deploy
- **Subito dopo il deploy nessuna sessione si potrà creare finché non esistono stanze.** Ordine: deploy → CD/Admin configurano le stanze → annuncio. Il banner del passo 13 lo rende visibile.
- Le sessioni già esistenti non vengono modificate; un giorno già sovraffollato resta com'è (segnalato nel calendario dal piano `calendarioVisuale`).
- Cancellare una stanza riduce la capienza: usare "disattiva" per interventi temporanei.

## 9. Chiusura
Rinominare in `implementationPlan-V-stanzeERegoleCalendario.md`.
