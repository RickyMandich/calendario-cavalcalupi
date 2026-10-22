# Implementation plan - Profilo utente e gestione utenti

## Obiettivo

1. **Utente loggato**: pagina profilo (`/profile`) con modifica di nome reale, email, password e rigenerazione del token ICS.
2. **Pagina gestione utenti** (`/admin/users`, Admin e CD): checkbox "Confermato" (conferma manuale dell'email), pulsante di reinvio della mail di conferma, pulsante di eliminazione utente.

## Stato di partenza (analizzato)

- `UserController` ha solo `index` e `updateRole`; `EmailVerificationController` gestisce la verifica dell'utente loggato.
- `User implements MustVerifyEmail`; INVARIANTE: utente non verificato = solo `Membro`, nessun gruppo, nessun feed ICS (403).
- `ics_token` è generato in `User::booted()` e dall'accessor; il feed è `/calendar/ics/{token}`.
- Nel repository non esiste la cartella `.agent/rules`: nessuna regola di progetto specifica da rispettare oltre a quelle generali.
- **Problema trovato**: `events.created_by`, `event_logs.user_id` e `session_swap_requests.requested_by` sono FK con `cascadeOnDelete`. Eliminare un utente cancellerebbe tutte le sessioni create da lui (e relativi log). Va corretto prima di aggiungere il pulsante di eliminazione.

## Decisioni

| Tema | Decisione |
|---|---|
| Cambio email | Richiede la password attuale. Se cambia, `email_verified_at` torna `null` e parte la mail di verifica (in `try/catch`, come in registrazione). Ruoli e gruppi già assegnati **non** vengono tolti (niente perdita di dati): finché non riverifica, il feed ICS risponde 403 e compare il banner. |
| Cambio password | Richiede password attuale + nuova (min 8) + conferma. |
| Rigenera token ICS | `POST /profile/ics-token`, con `confirm()` lato UI: il vecchio link smette di funzionare. Il link è mostrato solo a chi ha l'email verificata. |
| Errori nel profilo | Un *error bag* per ogni modulo (`profile`, `email`, `password`) perché `current_password` compare in due moduli. |
| Checkbox "Confermato" | Spuntare conferma l'email. Togliere la conferma è consentito solo a un `Membro` senza gruppi (altrimenti si violerebbe l'invariante). Non modificabile sul proprio account. |
| Reinvio mail di conferma | Solo per utenti non verificati, throttle 6/min, errore leggibile se l'SMTP non risponde. |
| Elimina utente | Non su se stessi; non l'ultimo Admin; con conferma. Le sessioni/eventi creati dall'utente **restano** (`created_by` → `NULL`), i log restano (`user_id` → `NULL`), le richieste di scambio pendenti dell'utente vengono annullate (motivo `user_deleted`) e quelle storiche restano (`requested_by` → `NULL`). Appartenenze ai gruppi e ruoli spariscono (FK / Spatie). Le righe `sessions` dell'utente vengono eliminate (logout immediato) se il driver è `database`. |

## Passi

### 1. Migrazione FK "nullOnDelete"
`database/migrations/2026_10_22_200000_make_user_references_nullable_on_user_delete.php`
- `events.created_by`, `event_logs.user_id`, `session_swap_requests.requested_by`: drop FK, colonna `nullable`, nuova FK con `nullOnDelete()`.
- `down()` ripristina FK cascade e colonne NOT NULL (fallisce se esistono righe con `NULL`: è voluto, non si perdono dati di nascosto).

### 2. Profilo (`ProfileController`)
- `edit`, `updateName`, `updateEmail`, `updatePassword`, `regenerateIcsToken`.
- Rotte nel gruppo `auth` (SENZA `verified`, come le rotte di verifica): `profile.edit` (GET `/profile`), `profile.update` (PUT), `profile.email` (PUT, throttle), `profile.password` (PUT, throttle), `profile.ics-token` (POST).
- Vista `resources/views/profile/edit.blade.php` (riquadri `.panel`, stesso stile del sito, 4 sezioni).
- Link al profilo: il nome utente in header (desktop/tablet) e nell'account del menu mobile diventa un link.

### 3. Gestione utenti (`UserController`)
- `setVerified` (PUT `admin.users.verified`), `resendVerification` (POST `admin.users.verification`), `destroy` (DELETE `admin.users.destroy`), tutte con `Gate::authorize('manage-users')` e nel gruppo `can:manage-users`.
- `index` usa `withCount('groups')` per sapere quando la conferma non è revocabile.
- Vista `admin/users/index.blade.php`: nuova colonna "Confermato" (checkbox che invia il modulo + "Reinvia mail"), pulsante "Elimina" nella colonna Azioni, "(te stesso)" porta al profilo.

### 4. Test (Pest/PHPUnit)
- `tests/Feature/ProfileTest.php`: accesso, nome, email (password errata, duplicata, invariata, cambio = non verificata + notifica), password (errata, conferma, minimo, ok), token ICS (nuovo token, vecchio feed 404, nuovo 200), link ICS nascosto a chi non è verificato.
- `tests/Feature/UserManagementActionsTest.php`: conferma manuale (sì/no, regole di revoca, self, permessi), reinvio mail (solo non verificati, permessi), eliminazione (self, ultimo Admin, permessi, eventi/log/richieste di scambio conservati).

### 5. Documentazione e chiusura
- README: sezione "Profilo utente e gestione utenti" + elenco test.
- `todo.md`: nuova Fase 29 con le voci sopra.
- A lavoro finito rinominare questo file in `implementationPlan-V-profiloEGestioneUtenti.md`.

## Da eseguire a mano dopo l'implementazione
- `php artisan migrate` (in dev e in produzione) e `php artisan test` (non eseguibili da qui).
- Prova nel browser: modifica profilo, cambio email con mail di verifica reale (Resend), rigenerazione token e re-iscrizione del calendario, eliminazione di un utente di prova con sessioni create.
