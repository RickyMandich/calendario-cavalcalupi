# Implementation Plan - Cambio giorno immediato e annullamento sessione (Fase 30)

**Riferimento:** `implementationPlan-V-spostamentoDirettoESwap.md` (Fase 18), che questo piano modifica.

## 1. Obiettivo
Il flusso normale per cambiare quando si gioca diventa solo due azioni, dalla pagina della sessione:
1. **Richiedi cambio giorno** (pulsante).
2. **Annulla sessione**.

Le persone non modificano a mano il giorno della sessione se non in casi eccezionali (solo CD/Admin).

Regole del cambio giorno:
- Se nel giorno richiesto c'è uno **slot libero** la sessione si sposta **subito** (stesso orario e durata), senza attendere nessuno.
- Se il giorno è **pieno** la richiesta resta in attesa; si risolve quando:
  - l'altro gruppo invia la richiesta speculare (scambio, come prima), oppure
  - **si libera un posto** in quel giorno (sessione annullata o spostata altrove): la richiesta più vecchia viene eseguita in automatico e il richiedente riceve un'email.

## 2. Decisioni (da confermare)
- Modifica manuale di data/ora: solo staff (CD/Admin). Chi gestisce le sessioni di un gruppo può ancora modificare **titolo e descrizione**.
- "Annulla sessione" = eliminazione già esistente (`delete-event`), con testo dedicato; libera il posto.
- Giorno senza stanze: la richiesta viene rifiutata subito (prima restava in attesa all'infinito).
- Assemblea sovrapposta all'orario mantenuto: rifiutata subito per lo spostamento immediato; in attesa saltata (la richiesta resta pendente).
- Spostamento immediato: nessuna email (l'utente vede l'esito); email `SessionMoved` solo quando la richiesta è eseguita in differita.
- Il rilascio di posti dovuto a modifiche delle stanze non attiva le richieste in attesa (si riprendono al prossimo posto liberato).

## 3. Passi
1. `SessionSwapRequest::markCompleted(?self $other = null)`: abbinamento opzionale.
2. `SessionSwapService`:
   - `request()`: dopo le validazioni, rifiuta giorno senza stanze; se `freeSlotsOn(target) > 0` → `moveDirectly()` (stato `moved`, log `_context.reason = direct_move`); altrimenti richiesta pendente + `tryMatch()`. Dopo il commit, `fulfillWaiting(vecchio giorno)`.
   - `fulfillWaiting(day)`: sotto lock, coda di giorni liberati; per ciascuno esegue le richieste pendenti con `target_date` = giorno, ordine `id`, finché ci sono slot; salta richieste non valide o in conflitto con assemblee; ogni spostamento libera il giorno di partenza (cascata). Email `SessionMoved` dopo il commit.
3. `Notifications/SessionMoved`.
4. `EventController`: `update()` data/ora solo staff, poi `fulfillWaiting(vecchio giorno)` se il giorno cambia; `destroy()` sotto lock + `fulfillWaiting`; messaggio "Sessione annullata".
5. Viste: `events/show` (sezione "Cambio giorno", pulsante "Annulla sessione", modifica eccezionale solo staff, righe log per i nuovi contesti), `events/edit` (date solo staff), `swap-requests/index` (testi).
6. Test: aggiornare `SessionSwapTest`; nuovi test per spostamento immediato, attesa, rilascio posto, cascata, permessi di modifica.
7. README e `todo.md`.

## 4. Criteri di accettazione
- Richiesta su giorno libero: sessione spostata subito, orario invariato, nessuna richiesta pendente, log `rescheduled`.
- Richiesta su giorno pieno: pendente; annullando/spostando la sessione che occupa il posto, la sessione in attesa si sposta e il richiedente riceve l'email.
- Un membro responsabile non può cambiare data/ora dal form di modifica.

## 5. Chiusura
Rinominare in `implementationPlan-V-cambioGiornoImmediato.md`.
