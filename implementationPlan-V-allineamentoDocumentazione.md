# Implementation plan - Allineamento della documentazione al codice

**Data:** 2026-11-01 · **Stato del codice di riferimento:** versione `1.0.11` (ultimo commit 2026-10-23, "updated flow to change date")

## Obiettivo
Rendere `README.md`, `todo.md` e i documenti di analisi nella root coerenti con il codice attuale, in modo che chi apre il progetto possa entrare nello sviluppo senza spiegazioni aggiuntive. **Nessuna modifica al comportamento dell'applicazione.**

## Metodo
Confronto tra documentazione e: `routes/web.php`, `AppServiceProvider` (Gate), controller, servizi, modelli, seeder, `composer.json`, `package.json`, `Dockerfile`, compose, nginx, entrypoint, supervisor, `routes/console.php`, `phpunit.xml`, `resources/css/app.css`, cartella `tests/`, `.github/workflows/deploy.yml` e `.git/logs/HEAD` (per sapere cosa è già stato committato).

## Discrepanze trovate

### `README.md`
1. Stack incompleto: mancano `phpoffice/phpspreadsheet`, `resend/resend-php`, FullCalendar, Pest.
2. Sezione "Migrazioni & Dati Demo" dice che i seeder non creano dati di esempio: falso, `DemoSeeder` (solo `APP_ENV=local`) crea 3 utenti, il gruppo «Tavolo Demo» e 3 stanze.
3. Credenziali demo e pulsanti di login rapido presentati come sempre disponibili: esistono solo con `APP_ENV=local`.
4. Tabella ruoli datata: Admin e CD hanno gli stessi poteri (`isStaff()`); mancano utenti, stanze, import, profilo, utenti non verificati.
5. Feed ICS: manca il vincolo "email verificata, altrimenti 403" e la rigenerazione del token dal profilo.
6. Audit trail: mancano il contesto (`swap`, `direct_move`, `waiting_request`) e l'utente `NULL` dopo eliminazione.
7. Scheduler: documentato solo `app:deactivate-groups`; esiste anche `prune-calendar-imports` (giornaliero).
8. Resend: il README dice di eseguire `composer require resend/resend-php`, ma il pacchetto è già in `composer.json` (commit 1.0.9).
9. Titolo "Funzionalità aggiunte (fasi 14-21)" ma il contenuto copre fino alla fase 30.
10. Palette: indicata come solo "tema chiaro" (esiste la modalità scura) e manca `.badge`.
11. Requisiti: manca l'estensione `gd` (richiesta da phpspreadsheet).
12. Mancano del tutto: mappa del codice, elenco rotte, Gate, flusso di deploy (GitHub Actions), convenzioni di lavoro, elenco dei documenti del repository, nota che l'entrypoint **non** esegue `migrate`.

### `todo.md`
1. Riferimenti ai piani senza `V-` (i file sono stati rinominati) per le fasi 13-21.
2. Fase 24: copia del modello in `public/` già fatta (commit 1.0.5); fase 27: commit/push fatti (1.0.7, 1.0.8); fase 28: `composer require` fatto (1.0.9).
3. Fase 21 descrive "Excel/CSV": il codice accetta solo `.xlsx`/`.xls` (riscritta nella fase 22).
4. Fase 23 è fuori ordine (dopo la 28).
5. Fase 18: elemento `MAIL_*` duplicato con la fase 28; fase 18 modificata dalla fase 30 (nota assente).
6. Fase 27: la domanda "la migrazione la lancia entrypoint/deploy?" ha una risposta parziale: l'entrypoint non lancia `migrate`; resta da verificare `deploy.sh` sul server.
7. Manca la voce per questo lavoro (fase 31).

### Altri documenti
- `situazione-di-partenza.md`: regola "ogni membro può modificare" superata dal permesso per gruppo (già segnalato come "da rivedere a mano" nel todo). Il documento è il brief originale dell'utente: non lo riscrivo, aggiungo solo un avviso in testa.
- `analisi-preprogetto.md` e `analisi-finale-modifiche.md`: documenti storici. Aggiungo un avviso di stato in testa (e in quello finale le differenze principali tra piano e implementazione).
- `ChatGPT-Analisi piano modifiche-20261020-1215.md`: export di una chat, non si modifica.
- `.agent/rules/*.md`: la cartella non esiste nel progetto, nessuna regola da rispettare.

### Commenti nel codice (documentazione inline)
- `routes/web.php`: commento "Excel/CSV" sopra il gruppo di rotte import (accetta solo xlsx/xls).
- `app/Models/User.php`: docblock "Determine if the user is staff" duplicato sopra `managedGroups()`.

## Passi di esecuzione
1. Aggiornare `README.md` con modifiche mirate (nessuna riscrittura totale, per non perdere il contenuto esistente).
2. Riscrivere `todo.md` (stessa struttura, stati e riferimenti corretti, nuova fase 31).
3. Aggiungere gli avvisi di stato a `situazione-di-partenza.md`, `analisi-preprogetto.md`, `analisi-finale-modifiche.md`.
4. Correggere i due commenti nel codice.
5. Rileggere le parti modificate e confrontarle con il codice.
6. Rinominare questo file in `implementationPlan-V-allineamentoDocumentazione.md`.

## Cosa NON viene fatto
- Non si marca come completato nulla che richieda di eseguire `php artisan test`, `migrate`, `npm run build` o di provare il sito (non eseguibili da qui): quelle voci restano aperte.
- Non si tocca `vendor/`, `node_modules/`, `composer.lock`, `.env`.
