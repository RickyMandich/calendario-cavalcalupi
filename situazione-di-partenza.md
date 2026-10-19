# obbiettivo
gestire il calendario di cavalcalupi, un'associazione di giochi di ruolo di cui faccio parte, che vuole poter fare in modo di tenere traccia in tempo reale di quali gruppi giocano in che momento. questa esigenza c'è perchè attualmente in sede non c'è abbastanza spazio per permettere a tutti i gruppi di giocare contemporaneamente per cui giochiamo alternati giovedì e venerdì.
# vincoli
- ogni utente fa parte di uno o più gruppi
- vengono considerati per le operazioni solo i gruppi di quest'anno (ossia creati dallo scorso 15 agosto, idealmente le campagne iniziano a settembre ottobre e finiscono a maggio/giugno, anche se alcuni gruppi continuano a giocare un po' oltre per finire la storia, anche se comunque non oltre fine giugno, per cui il 15 agosto dovrebbe essere una data sicura per non causare problemi a nessuno)
- il consiglio direttivo è colui che può creare i gruppi e assegnare i gruppi alle persone
- una persona non può essere assegnata a un gruppo non attivo (per cui creato prima dello scorso 15 agosto)
- ogni membro di un gruppo può modificare quando quel gruppo gioca (le questioni logistiche si discutono tramite messaggi esterni al sito prima, per cui qui non servono controlli o approvazioni, al massimo una tabella di """log""" che si ricorda una sessione, chi la ha modificata e in che modo)
- ogni evento del calendario è una sessione di un gruppo di gioco, un'assemblea o un evento associativo
    - le sessioni possono venire create o modificate solo dai membri del gruppo o da un membro del CD
    - un'assemblea o un evento associativo può essere creato e modificato solo da un membro del CD
- ogni utente deve poter vedere tutti gli eventi del calendario dal sito, non solo quelli del suo gruppo, così è più semplice chiedere cambi di data agli altri, mettiamo comunque un pulsante per scegliere se da sito si vedano tutte le sessioni o solo quelle del gruppo
- deve esserci la possibilità di aggiungere il calendario "cavalcalupi di {username}" al proprio calendario esterno tramite link ics, questo ics deve essere per utente, in questo modo non servirà riconfigurare il calendario ogni anno (visto che i gruppi cambiano tutti gli anni, motivo per cui abbiamo i gruppi attivi) e allo stesso tempo non inondiamo tutti i giocatori con sessioni che non li riguardano nella loro applicazione calendario. parlo di link ics così si aggiorna automaticamente e non bisogna aggiornare i singoli eventi a mano, naturalmente anche qua assemblee e eventi associativi dovranno comparire, gli unici da togliere sono le sessioni degli altri gruppi
# stack (voglio usare questi, se vuoi sostituire uno di questi pezzi proponi e valutiamo, se ci sono cose che proprio mancano aggiungi)
- laravel
- tailwind
- bladewindUI per i componenti del frontend
- mariadb