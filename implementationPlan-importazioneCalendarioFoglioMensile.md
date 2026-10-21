# Piano: importazione del calendario dal foglio mensile reale

Sostituisce il formato "una riga per sessione" di `implementationPlan-V-importazioneCalendarioExcel.md`.
Sorgente: `example/esempio-di-excel.jpg` (foglio mensile). Il file Excel reale non c'è ancora: il parser è tollerante e va verificato sul file vero.

## Formato sorgente (da screenshot)
- Titolo del mese (`OTTOBRE`, `DICEMBRE`) in una riga unita; possibili più mesi nello stesso foglio (o un foglio per mese: vale anche il nome del foglio).
- Riga di serata: etichetta `Giovedì 3` / `Venerdì 4` nella prima cella, poi una cella per tavolo.
- Cella tavolo: riga 1 = sistema di gioco, riga 2 = campagna (= gruppo). Una sola riga (`SCUOLE MASTER`) = nome del gruppo, nessun sistema.
- Banda unita su 3+ colonne (anche su due righe: `Giovedì 24` + `Venerdì 25`) = festività/evento/assemblea, oppure `GIOCO LIBERO` (nessun tavolo).
- Riga vuota/grigia = nessuna serata. Il `Venerdì 1` in coda a dicembre è di gennaio (cambio mese quando il giorno "torna indietro").

## Decisioni (risposte dell'utente)
1. Gruppo = campagna (riga 2); sistema di gioco nella descrizione (`Sistema: D&D 5.5`). `SCUOLE MASTER` è un gruppo come gli altri.
2. Orari: sessioni 21:30–00:00 (fine = mezzanotte del giorno dopo), modificabili nel modulo di caricamento. Stessa coppia di default per le bande, modificabile.
3. Anno: stagione corrente (da agosto in poi = anno corrente, altrimenti precedente); settembre–dicembre = anno di stagione, gennaio–agosto = anno dopo. Modificabile nel modulo. Controllo di coerenza giorno della settimana ↔ data.
4. Bande: per ciascun testo distinto un select: evento associativo (predefinito), assemblea, ignora (predefinito per `GIOCO LIBERO`).
5. Gruppi: per ciascun nome distinto un select con i gruppi attivi (preselezionato se il nome coincide) e "Crea nuovo gruppo «nome»". La scelta vale per tutte le sessioni con quel nome (nessun refresh da fare). Tutto-o-niente: i gruppi nuovi si creano solo alla conferma.
6. Descrizione = `Sistema: …`.

## Passi
1. Parser (`CalendarImportParser`): legge celle e celle unite, riconosce mesi, etichette serata, tavoli e bande; solo `.xlsx`/`.xls`.
2. Servizio (`CalendarImportService` + `ImportOptions`): date, controllo giorno della settimana, regole `CalendarScheduler` (capienza, assemblee) con eventi del file, duplicati, risoluzione gruppi, commit in `withLock` con rivalidazione.
3. Controller/vista: modulo (file, anno stagione, orari), anteprima con select gruppi/bande ed errori per riga, conferma. Rimosso il modello scaricabile.
4. `CalendarController`: nel feed non ripetere il nome se titolo = nome gruppo.
5. Test, README, todo.md.

## Fuori ambito / da verificare sul file reale
- Come sono scritti davvero sistema e campagna nella cella (a capo? celle separate?).
- Eventuali bande non unite.
- Tipo "chiusura sede": oggi le festività sono eventi associativi (non bloccano le sessioni).
