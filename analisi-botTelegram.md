# Bot Telegram per il Calendario Cavalcalupi: analisi

> Documento di analisi da condividere con il referente del Consiglio Direttivo. **Nessuna riga di codice è ancora stata scritta.** In fondo (sezione 12) c'è l'elenco dei punti su cui serve un ok o una correzione.
> Data: 21/11/2026. Stato: **proposta in attesa di approvazione**.

---

## 1. Obiettivo

Portare il calendario dove le persone vivono già, cioè su Telegram, senza togliere nulla al sito:

1. **Nel gruppo Telegram di Cavalcalupi (topic «presenza»)** arrivano automaticamente le comunicazioni importanti: sessione creata, annullata, spostata (e lo stesso per assemblee ed eventi associativi).
2. **In chat privata con il bot** i responsabili e il CD possono fare le stesse operazioni sulle sessioni che oggi fanno dal sito. Il gruppo non si riempie di comandi e risposte: ci arriva solo il risultato finale.
3. **Un comando semplice, usabile ovunque**, mostra le sessioni della settimana corrente.

Il bot **non introduce regole nuove**: posti nelle stanze, assemblee che bloccano gli altri eventi, cambio giorno immediato o in attesa, scambi tra gruppi restano identici a quelli del sito. Il bot è solo un secondo modo per usarli.

---

## 2. Come si divide il lavoro tra gruppo e chat privata

| Dove | Cosa succede |
|---|---|
| **Gruppo, topic «presenza»** | Il bot **scrive** le notifiche. Risponde solo a `/settimana` (e ai comandi di configurazione per chi è autorizzato). Non esegue «ordini». |
| **Chat privata col bot** | Qui si danno gli ordini: creare, spostare, annullare sessioni, vedere le richieste in attesa. **Serve essere collegati** al proprio account del sito. |
| **Sito** | Resta completo. Per tutto ciò che il bot non fa (profilo, gruppi, stanze, utenti, import) il bot offre un pulsante che apre la pagina giusta nel browser. |

Il bot lavora **in un solo gruppo Telegram**, quello dell'associazione.

---

## 3. Collegare l'account Telegram all'account del sito

Poiché la maggior parte degli utenti starà più su Telegram che sul sito, il collegamento parte **dal bot**. Si può comunque partire anche dal sito.

### 3.1 Partendo da Telegram (percorso principale)

1. Nella chat privata col bot l'utente scrive `/collega` (o preme «Collega» da `/start`).
2. Il bot risponde con un **link personale monouso**, valido pochi minuti.
3. L'utente apre il link nel browser:
   - **se è già loggato sul sito** → vede «Collegare l'account Telegram di *nome* a *tuo nome*?» e conferma con un tasto;
   - **se non è loggato** → la pagina propone **accedi** oppure **registrati**; terminato il login/registrazione, il collegamento viene completato con lo stesso account appena usato.
4. Il bot scrive in chat «Collegato come *Nome Cognome*» e da quel momento riconosce l'utente.

### 3.2 Partendo dal sito (secondario)

Nella pagina **Profilo** compare «Collega Telegram»: apre il bot già con il codice giusto; basta premere «Avvia».

### 3.3 Regole del collegamento

- Un account Telegram corrisponde a **un solo** account del sito e viceversa.
- Il collegamento è **permanente** finché non lo si toglie (`/scollega` dal bot oppure dalla pagina Profilo).
- I link sono monouso e a scadenza breve; nessuna password passa mai da Telegram.
- **I permessi sono sempre quelli attuali del sito**: se il CD toglie a qualcuno il permesso «gestione sessioni» o cambia il ruolo, il bot si adegua subito, senza ricollegare.
- Se un utente viene eliminato dal sito, il collegamento sparisce con lui.
- Chi si registra attraverso il link ha comunque un account con **email da confermare** come adesso: finché non conferma, resta Membro semplice, senza gruppi e senza feed ICS (regola già esistente del sito).

---

## 4. Notifiche nel gruppo (topic «presenza»)

### 4.1 Cosa viene notificato

| Evento | Notificato? |
|---|---|
| Sessione creata | Sì |
| Sessione annullata | Sì |
| Sessione spostata (spostamento diretto, scambio, richiesta in attesa eseguita, modifica fatta dal CD) | Sì |
| Assemblea o evento associativo creato, annullato, spostato | Sì |
| Richiesta di cambio giorno **in attesa** (giorno pieno) | Sì, perché è il momento in cui i gruppi si mettono d'accordo |
| Import del calendario da Excel | Sì, **un solo messaggio riepilogativo** (non uno per sessione) |
| Modifica di solo titolo/descrizione | **No**, per non fare rumore |

Uno **scambio** tra due gruppi muove due sessioni ma genera **un solo messaggio**.

### 4.2 Esempi di messaggi (bozza)

> 🆕 **Nuova sessione**
> *Titolo* · Gruppo *X*
> giovedì 26 novembre, 21:30–00:00
> [Apri sul sito]

> ❌ **Sessione annullata**
> *Titolo* · Gruppo *X*, giovedì 26 novembre
> Il posto è libero.
> [Proponi una sessione per questo giorno] [Apri il calendario]

> 🔁 **Sessione spostata**
> *Titolo* · Gruppo *X*: da venerdì 27 a giovedì 26 novembre (orario invariato)
> Motivo: *cambio giorno diretto / scambio con il gruppo Y / si è liberato un posto*
> [Apri sessione]

> 🤝 **Richiesta in attesa**
> Il gruppo *X* vorrebbe passare a venerdì 4 dicembre, ma il giorno è pieno. Per sbloccarla serve che un gruppo di quel giorno proponga uno scambio.
> [Offri scambio]

> 📢 **Assemblea** sabato 12 dicembre, 15:00–18:00. [Apri sul sito]

### 4.3 Pulsanti dei messaggi

I pulsanti nel gruppo possono essere premuti da chiunque, quindi **non eseguono azioni nel gruppo**:

- «Apri sul sito» / «Apri il calendario» → aprono il browser.
- «Proponi una sessione per questo giorno», «Offri scambio» → aprono la **chat privata** col bot già impostata sul giorno/richiesta giusti. Se chi preme non è collegato o non ha il permesso, il bot glielo dice (con un messaggio in privato, mai nel gruppo).

«Offri scambio» fa questo: il bot mostra le tue sessioni di quel giorno che potrebbero scambiarsi, tu scegli e confermi, e lo scambio parte con le stesse regole del sito (richiesta speculare → scambio automatico).

### 4.4 Scelta del topic

Telegram non permette ai bot di vedere l'elenco dei topic, quindi si fa così:

1. Un amministratore del gruppo scrive `/imposta_topic` **dentro** il topic «presenza».
2. Il bot risponde lì con il pulsante «Usa questo topic per le notifiche».
3. Premendolo, il bot ricorda il topic. Il comando funziona solo se chi lo usa è **amministratore del gruppo Telegram** e **membro CD/Admin sul sito** (collegato).

Il bot ignora (o abbandona) qualunque altro gruppo in cui venga aggiunto.

---

## 5. Comando per vedere le sessioni della settimana

**`/settimana`** funziona sia nel gruppo (nel topic collegato) sia in privato.

- Mostra le sessioni della **settimana corrente (lunedì-domenica)**, come l'elenco del sito, raggruppate per giorno, con gruppo, titolo/sistema, orario; incluse assemblee ed eventi associativi.
- **Nel gruppo** mostra sempre tutto (il calendario è comunque pubblico sul sito).
- **In privato** c'è un filtro **opzionale** «solo i miei gruppi» (`/settimana mie`); senza filtro mostra tutto, utile ad esempio a un membro del CD.
- Non esiste un promemoria automatico: chi vuole controllare lancia il comando quando serve.
- Messaggi molto lunghi vengono divisi automaticamente (Telegram ha un limite di lunghezza).

---

## 6. Azioni in chat privata

Il bot guida con **pulsanti** passo passo (non bisogna imparare sintassi). Prima di ogni azione che modifica qualcosa compare un riepilogo con «Conferma» / «Annulla».

### 6.1 Azioni sulle sessioni (come sul sito)

| Comando | Cosa fa | Chi può |
|---|---|---|
| `/nuova` | Crea una sessione: scegli gruppo → giorno (con i posti liberi indicati) → orario (predefinito 21:30–00:00, modificabile) → titolo/descrizione → conferma | Responsabili dei propri gruppi; CD/Admin tutti i gruppi attivi |
| `/sposta` | Cambio giorno: scegli la sessione → nuovo giorno (con i posti liberi). Orario e durata non cambiano. Giorno libero = spostamento subito; giorno pieno = richiesta in attesa; giorno senza stanze = rifiutato | Come sopra |
| `/annulla` | Annulla una sessione (chiede conferma) e libera il posto: eventuali richieste in attesa su quel giorno partono da sole | Come sopra |
| `/modifica` | Cambia titolo e descrizione. Giorno e orario a mano **solo** CD/Admin, nei casi eccezionali (come sul sito) | Responsabili / CD / Admin |
| `/richieste` | Elenco delle richieste di cambio giorno in attesa, con pulsante per annullare la propria | Tutti i collegati vedono; annulla chi è autorizzato come sul sito |

### 6.2 Azioni per il CD/Admin

| Comando | Cosa fa |
|---|---|
| `/assemblea` | Crea un'assemblea (rispetta il blocco di tutti gli altri eventi nello stesso orario) |
| `/evento` | Crea un evento associativo |
| `/modifica`, `/annulla` | Valgono anche per assemblee ed eventi |

### 6.3 Cosa resta solo sul sito

Il bot offre per queste cose un pulsante **«Apri nel browser»**, non le esegue:

- **Profilo** (nome, email, password, link feed ICS): `/profilo` apre la pagina profilo.
- **Gestione gruppi e membri**, **stanze e capienza**, **utenti e ruoli**, **import Excel**, **log**.

> ⚠️ Questo è uno dei punti da confermare (sezione 12): ho interpretato «replicare le azioni sulle sessioni» come «sessioni, assemblee ed eventi»; il resto della gestione resta sul sito.

### 6.4 Chi non è collegato

Può usare solo `/settimana`, `/start`, `/aiuto` e `/collega`. Qualsiasi altra azione risponde «collega prima il tuo account» con il pulsante giusto.

---

## 7. Notifiche personali in privato

Oggi chi chiede un cambio giorno riceve un'**email** quando la richiesta viene eseguita (`SessionMoved`) o quando uno scambio va a buon fine (`SessionSwapped`). Con il bot queste notifiche potranno arrivare **anche (o solo) su Telegram** in chat privata. Ogni utente collegato sceglierà dal Profilo: solo email, solo Telegram, oppure entrambe.

Da decidere: valore predefinito per chi collega Telegram (proposta: **entrambe** finché non cambia).

---

## 8. Regole e comportamenti da non rompere

- **Il sito non dipende da Telegram**: se Telegram non risponde, tutte le azioni del sito funzionano comunque; i messaggi vengono messi in coda e riprovati più tardi.
- **Stesse regole del sito, stessi messaggi di errore** in italiano («Giorno pieno: 3/3 posti occupati», «Nel giorno e orario scelto c'è un'assemblea», ...).
- **Date e orari** sempre in fuso `Europe/Rome`.
- Se il topic non è impostato (o è stato cancellato), le notifiche non partono e il CD collegato riceve un avviso in privato.
- Ogni azione fatta dal bot finisce nello **storico modifiche** della sessione come per il sito, con l'utente collegato come autore.

---

## 9. Sicurezza e privacy

- **Nessuna password su Telegram.** Il collegamento avviene dal browser con link monouso a scadenza breve.
- L'identità in privato è il **codice utente Telegram** (non falsificabile), non il nome visualizzato.
- Il bot risponde solo nel **gruppo configurato** e ai **comandi di configurazione** solo agli amministratori autorizzati.
- La chiamata dal server di Telegram al sito è protetta da un segreto, così nessun altro può inviare finti comandi.
- Si salvano solo: codice Telegram e nome utente dell'account collegato, collegato a un utente del sito (cancellati con il collegamento o con l'utente). Nessuna lettura dei messaggi del gruppo oltre ai comandi diretti al bot.
- Il token del bot sta solo nelle variabili d'ambiente del server, mai nel repository. Per il calendario si usa un **bot dedicato**, separato da quello dei deploy.

---

## 10. Cosa NON farà (fuori ambito)

- Conferma di presenza (RSVP «ci sono / non ci sono»): le sessioni che saltano si gestiscono annullandole.
- Promemoria automatici o riepilogo settimanale automatico: basta `/settimana`.
- Notifica di modifiche a solo titolo/descrizione.
- Più gruppi Telegram.
- Cambio password/email, rigenerazione del feed ICS, gestione utenti, gruppi, stanze, import Excel dal bot.

---

## 11. Note tecniche (per chi sviluppa)

- **Riuso delle regole**: la logica oggi nei controller (`EventController::store/update/destroy`) va estratta in classi condivise (`App\Actions\*`) usate da controller e bot; le regole restano in `CalendarScheduler` e `SessionSwapService`. I test esistenti devono restare verdi.
- **Permessi**: il bot esegue sempre le azioni come l'utente collegato (`Gate::forUser`), mai con privilegi propri.
- **Notifiche**: agganciate all'`EventObserver` (creazione, spostamento, eliminazione), così coprono tutti i percorsi (cambio diretto, scambio, richiesta in attesa, modifica CD). Import Excel: notifiche sospese e un solo riepilogo. Invio tramite coda (il worker esiste già) con retry sui limiti di Telegram.
- **Libreria**: si usa un pacchetto se funziona bene. Candidato: `nutgram/nutgram` (+ integrazione Laravel), che offre conversazioni a passi, menu inline, rate limiting e test. Da verificare la compatibilità con Laravel 12 prima dell'installazione (poi committare `composer.json` e `composer.lock`). Se non regge, si scrive a mano un piccolo client con l'`Http` di Laravel (`sendMessage`, `answerCallbackQuery`, `setWebhook`) e una macchina a stati per i flussi.
- **Trasporto**: webhook con `secret_token` in produzione (Traefik/Cloudflare danno già HTTPS), comando di polling per lo sviluppo locale.
- **Dati nuovi**: collegamento utente-Telegram, token di collegamento (salvati come hash, monouso, a scadenza), impostazioni (chat e topic del gruppo), preferenza canale notifiche per utente.
- **Test**: finto client Telegram, payload di esempio per il webhook, notifiche fittizie; copertura di permessi, token scaduti/riusati, topic non impostato, errori di Telegram che non devono bloccare il sito.
- **Documentazione**: README (sezione dedicata) e `todo.md` aggiornati a ogni fase.

---

## 12. Fasi di rilascio proposte

| Fase | Contenuto |
|---|---|
| **A** | Estrazione delle azioni condivise, tabelle, webhook, collegamento account (da bot e da sito), `/imposta_topic`, notifiche nel topic (sessioni, assemblee/eventi, richieste in attesa, riepilogo import), `/settimana` |
| **B** | Azioni sulle sessioni in privato: `/nuova`, `/sposta`, `/annulla`, `/modifica`, `/richieste`, pulsante «Offri scambio», pulsanti contestuali dei messaggi |
| **C** | `/assemblea`, `/evento` per il CD, notifiche personali in privato con preferenza di canale, pulsanti «Apri nel browser» (profilo e sezioni admin) |

Ogni fase si può mettere in produzione da sola.

---

## 13. Punti da confermare con il referente CD

1. **Collegamento account** da bot con link monouso (e login/registrazione se serve), più la via inversa dal Profilo del sito. D'accordo?
2. **Notifiche**: sessioni, assemblee ed eventi associativi (creazione, annullamento, spostamento) e richieste in attesa. Le modifiche di solo titolo/descrizione non vengono notificate. Va bene?
3. **Notifiche con o senza suono** nel gruppo? (Proposta: con suono per creazione e annullamento, senza suono per gli spostamenti minori; oppure tutte uguali.)
4. **Ambito delle azioni in privato**: il bot copre **sessioni, assemblee ed eventi**; gruppi, stanze, utenti e import Excel restano solo sul sito (con pulsante che li apre). Confermi?
5. **Chi può impostare il topic**: amministratore del gruppo Telegram **e** CD/Admin sul sito. Va bene?
6. **Notifiche personali in privato** (oltre alla mail): predefinito «entrambe», modificabile da Profilo. Va bene?
7. **Pulsante «Offri scambio»** sotto le richieste in attesa: utile o troppo invasivo nel gruppo?
8. **Ordine di rilascio** A → B → C. Preferisce una priorità diversa (es. le notifiche prima di tutto)?

---

## 14. Decisioni già prese (riepilogo)

- Notifiche nel gruppo Telegram dell'associazione, nel topic «presenza»; ordini solo in chat privata.
- Autenticazione obbligatoria in privato per qualsiasi azione; collegamento permanente.
- Un solo gruppo Telegram; bot dedicato al calendario.
- `/settimana` unico comando per la settimana corrente, nel gruppo e in privato; in privato filtro opzionale «mie».
- Nessun RSVP, nessun promemoria automatico; sessioni che saltano = sessioni annullate.
- Pacchetto esistente se funziona bene, altrimenti a mano.
- Conferma esplicita per ogni azione che modifica o cancella.
- Dati utente (profilo): pulsante che apre il browser.
