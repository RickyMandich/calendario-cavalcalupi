# Implementation Plan - Importazione del calendario da Excel/CSV (Fase 21)

**Riferimento:** `analisi-finale-modifiche.md` sez. 12.
**Dipendenze:** `stanzeERegoleCalendario` (`CalendarScheduler` con parametro `$extra`), `permessoGestioneSessioni` (staff), consigliato dopo `spostamentoDirettoESwap`.

## 1. Obiettivo
Permettere a CD/Admin di caricare in blocco il **calendario** (sessioni ed eventuali altri eventi) da un file Excel o CSV. I **gruppi devono esistere già**; **nessun utente** viene creato. Flusso a due passi: caricamento → anteprima con errori → conferma; inserimento **tutto o niente** in una sola transazione, con le stesse regole del form (capienza, stanze, assemblee).

## 2. Stato attuale (da codice)
- Nessun import. Gli eventi si creano solo da `EventController::store`.
- `Dockerfile` (PHP 8.2-fpm-alpine) installa già `gd`, `zip`, `xml`, `mbstring`: bastano per PhpSpreadsheet (verificare `composer require` nell'immagine di sviluppo).
- `EventObserver::created` registra il log `created` usando `Auth::id()`.
- `routes/console.php` contiene lo scheduler (`schedule:work` già in esecuzione nel worker).
- `CalendarScheduler` (piano stanze) espone `validate(..., iterable $extra)` e `createEvent`/`withLock`.

## 3. Formato del file
Prima riga = intestazioni (non sensibili a maiuscole e spazi), primo foglio; massimo **500 righe** e **2 MB**.

| Colonna | Obbligatoria | Valori |
|---|---|---|
| `gruppo` | sì per le sessioni | nome di un gruppo **attivo** (confronto senza maiuscole/spazi doppi) |
| `titolo` | sì | testo ≤ 255 |
| `data` | sì | cella data Excel, oppure `gg/mm/aaaa` o `aaaa-mm-gg` |
| `ora_inizio` | sì | `HH:MM` o cella ora |
| `ora_fine` | sì | `HH:MM`, successiva a `ora_inizio` (niente eventi oltre la mezzanotte) |
| `descrizione` | no | testo |
| `tipo` | no | `sessione` (predefinito), `assemblea`, `evento associativo` (accettati anche `session`, `assembly`, `association_event`) |

Per assemblee ed eventi associativi la colonna `gruppo` si ignora. Fuso: `Europe/Rome`. Un modello scaricabile (`.xlsx` con intestazioni e una riga d'esempio) è generato dall'applicazione.

## 4. Passi

### 4.1 Dipendenza
1. `composer require phpoffice/phpspreadsheet` (versione compatibile con PHP ≥ 8.2; non modificare `vendor/` a mano). Ricostruire l'immagine (`docker compose ... build`) e controllare che `ext-gd`, `ext-zip`, `ext-xml` risultino soddisfatte.

### 4.2 Classi (`app/Services/CalendarImport/`)
2. `ImportRow` (DTO): `number` (riga del file), `raw` (array dei valori), `attributes` (array normalizzato: `type`, `group_id`, `title`, `description`, `start_time`, `end_time`), `errors` (array di stringhe).
3. `CalendarImportParser::parse(string $path): Collection<ImportRow>`:
   - `IOFactory::load($path)` in sola lettura (`setReadDataOnly(true)`), primo foglio, intestazioni normalizzate (`trim`, minuscolo, spazi→`_`); intestazioni obbligatorie mancanti → eccezione con messaggio chiaro;
   - date: se la cella è numerica usare `Date::excelToDateTimeObject`; se testo, provare `d/m/Y` e `Y-m-d`; ore: frazione del giorno o testo `H:i`;
   - ignora righe completamente vuote; oltre 500 righe → errore globale.
4. `CalendarImportService`:
   - `preview(string $path, User $by): ImportPreview` → per ogni riga: validazione di formato (campi obbligatori, tipo noto, orari), risoluzione del gruppo (non trovato / più gruppi con lo stesso nome / gruppo non attivo → errore), **duplicati** (stesso tipo, titolo e `start_time` già presenti nel DB o già visti nel file → errore "già presente"), poi regole di calendario con `CalendarScheduler::validate($attributes, null, $extra)` dove `$extra` accumula le righe **valide precedenti** del file (così due sessioni nello stesso giorno con un solo posto: la seconda è in errore). Cattura `SchedulingException` e la associa alla riga;
   - `commit(string $path, User $by): int`: dentro `CalendarScheduler::withLock`, **ripete la stessa validazione** (lo stato del DB può essere cambiato dopo l'anteprima) e, se **tutte** le righe sono valide, crea gli eventi con `createEvent`; se anche una sola riga è errata → `ImportFailedException` con il riepilogo e **nessuna** scrittura (rollback). Ritorna il numero di eventi creati.
   - `ImportPreview`: `rows`, `validCount`, `errorCount`, `canCommit` (= nessun errore e almeno una riga).

### 4.3 Controller, rotte, viste
5. Gate `import-calendar` = `isStaff()`.
6. `CalendarImportController`:
   - `create()`: pagina di caricamento con istruzioni sul formato e link al modello;
   - `store(Request $request)`: valida `file` (`required|file|mimes:xlsx,csv,txt|max:2048`); salva in `storage/app/imports/{uuid}.{ext}` (disco `local`, privato); esegue `preview`; mostra la pagina di anteprima passando l'`uuid`; se il parser fallisce (intestazioni mancanti) torna al form con l'errore;
   - `confirm(Request $request)`: valida `token` (`regex:/^[0-9a-f-]{36}$/`), risolve il percorso **solo** dentro `storage/app/imports/` (mai un percorso preso dalla richiesta), 404 se il file manca; `commit`; elimina il file; redirect a `/calendar` con "N eventi importati"; in caso di `ImportFailedException` torna all'anteprima aggiornata con gli errori;
   - `cancel(Request $request)`: elimina il file temporaneo;
   - `template()`: genera e scarica `modello-calendario.xlsx` (`Spreadsheet` con intestazioni, riga d'esempio e un foglio "Istruzioni").
7. Rotte (`auth`, prefisso `admin`, `can:import-calendar`): `GET /admin/import/calendar` (`admin.import.create`), `POST /admin/import/calendar` (`admin.import.store`), `POST /admin/import/calendar/confirm` (`admin.import.confirm`), `DELETE /admin/import/calendar` (`admin.import.cancel`), `GET /admin/import/calendar/template` (`admin.import.template`).
8. Vista `admin/import/calendar.blade.php`: due stati. **Caricamento**: dropzone/`<input type="file">`, istruzioni con tabella colonne, link al modello. **Anteprima**: riepilogo (righe valide/con errori), tabella con n° riga, gruppo, titolo, data, orari, tipo ed esito (✅ / ❌ con messaggi); pulsante "Conferma importazione" attivo solo se `canCommit`; pulsante "Annulla"; testo "Importazione tutto-o-niente: correggi il file e ricaricalo". Tutto l'output con `{{ }}` (escape). Usare solo classi componente (compatibile con il tema scuro).
9. `layouts/app.blade.php`: link "📥 Import" sotto `@can('import-calendar')`.

### 4.4 Pulizia file temporanei
10. `routes/console.php`: `Schedule::call(fn () => /* elimina i file in storage/app/imports più vecchi di 24 ore */)->daily()->name('prune-calendar-imports')` (o comando `app:prune-imports`).

## 5. Test (`tests/Feature/CalendarImportTest.php`)
Helper che genera un `.xlsx` in un file temporaneo con PhpSpreadsheet e lo carica con `UploadedFile`. Si usa `CreatesRooms`.
- Permessi: ospite → login; `Membro` → 403 su tutte le rotte; CD/Admin → 200.
- File valido (più sessioni su giorni con posto, un'assemblea, un evento associativo in giorno senza stanze): anteprima senza errori; `confirm` crea tutti gli eventi con `created_by` dell'utente, log `created` per ciascuno, il file temporaneo viene rimosso.
- Errori per riga: intestazione mancante (errore globale), gruppo inesistente, gruppo inattivo, nome gruppo ambiguo, data non valida, `ora_fine ≤ ora_inizio`, tipo sconosciuto, titolo vuoto.
- Regole di calendario: sessione in giorno senza stanze; giorno pieno per eventi già a DB; **capienza superata dalle righe dello stesso file** (3 sessioni nello stesso giorno con capienza 2 → la terza in errore); sovrapposizione con assemblea a DB; assemblea sopra una sessione a DB o nello stesso file.
- Duplicati: riga già presente a DB e riga ripetuta nel file → errore; ricaricare lo stesso file dopo l'importazione → tutte le righe in errore, nessuna creazione.
- Tutto-o-niente: un file con 9 righe valide e 1 errata → `canCommit` falso, `confirm` non crea nulla.
- Ri-validazione al commit: dopo l'anteprima si riempie il giorno (evento creato a mano), poi `confirm` → fallisce senza scrivere nulla.
- Sicurezza: `token` non valido → 422/404; un `token` che tenta path traversal non esce da `imports/`; file con estensione non ammessa → errore di validazione; oltre 500 righe → errore.
- CSV: stesso scenario con `.csv` (separatore `,` e `;` se gestito).
- Modello scaricabile: `GET /admin/import/calendar/template` restituisce un `.xlsx` valido con le intestazioni attese.

## 6. Documentazione
- `README.md`: sezione "Importazione del calendario" (formato, flusso, regole, tutto-o-niente, pulizia file), dipendenza `phpspreadsheet`, test inclusi.
- `todo.md`: Fase 21.

## 7. Criteri di accettazione
- Un CD carica il calendario della stagione, vede l'anteprima con gli errori riga per riga e, correggendo il file, importa tutto in un colpo.
- Nessuna riga può violare capienza, giorni senza stanze o assemblee; nessuna importazione parziale.
- Nessun utente né gruppo creato dall'import; gli eventi risultano nello storico come creati dall'utente che importa.
- Test verdi.

## 8. Rischi
- File grandi o malformati: limiti di righe/dimensione e `setReadDataOnly`.
- Dati inseriti nelle celle (formule): non vengono mai valutate (sola lettura dei valori) e l'output è sempre escapato.
- Nomi gruppo ambigui: il file va corretto con il nome esatto; l'anteprima lo segnala.

## 9. Chiusura
Rinominare in `implementationPlan-V-importazioneCalendarioExcel.md`.
