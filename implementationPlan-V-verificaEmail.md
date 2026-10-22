# Implementation Plan - Verifica email e restrizioni per utenti non verificati

## 1. Requisito
Un utente con email **non verificata**:
- può avere solo il ruolo `Membro` (mai `CD` / `ADMIN`);
- non può far parte di gruppi;
- non ha il feed ICS.
Può comunque registrarsi, accedere e consultare il calendario pubblico.

## 2. Stato attuale (da codice)
- `users.email_verified_at` esiste già (migrazione base) e la factory lo valorizza di default; `User` non implementa `MustVerifyEmail`.
- `AuthController::register` crea l'utente `Membro` e lo logga, senza inviare email.
- Entry point da proteggere: `Group::addUser()` (usato da `GroupController::assignUser`), `UserController::updateRole`, `app:make-admin`, `IcsCalendarController`, modale ICS in `calendar/index.blade.php`.
- `DemoSeeder` crea utenti con `firstOrCreate` (`email_verified_at` non è fillable -> resterebbero non verificati).
- Utenti già esistenti in produzione hanno `email_verified_at = null`: senza backfill staff e membri dei gruppi perderebbero accesso al deploy.

## 3. Soluzione

### 3.1 Modello e rotte
- `User implements MustVerifyEmail`.
- `EmailVerificationController` (rotte nel gruppo `auth`, **senza** middleware `verified`: l'utente non verificato deve poter navigare):
  - `GET /email/verify` (`verification.notice`) pagina "controlla la tua email" con pulsante di reinvio;
  - `GET /email/verify/{id}/{hash}` (`verification.verify`, `signed` + `throttle:6,1`) usa `EmailVerificationRequest::fulfill()`;
  - `POST /email/verification-notification` (`verification.send`, `throttle:6,1`).
- Registrazione: dispatch di `Registered` (invia il link) dentro `try/catch` con `report()`: se l'SMTP non è configurato la registrazione non deve dare 500 (l'utente può reinviare dal banner). Il redirect resta `calendar.index`.
- Testo email in italiano via `VerifyEmail::toMailUsing` in `AppServiceProvider`.
- Layout: banner ambra per gli utenti autenticati non verificati, con pulsante "Reinvia email".

### 3.2 Regole
- `Group::addUser()`: `DomainException` se l'utente non ha verificato l'email (vale per UI, import e qualunque chiamata di dominio). Elenco "Aggiungi utente" in `admin/groups` limitato agli utenti verificati (con nota sul numero di non verificati).
- `UserController::updateRole`: solo `Membro` per i non verificati (errore su `role`); pagina Utenti con badge "Email non verificata" e opzioni CD/ADMIN disabilitate.
- `app:make-admin`: segna anche l'email come verificata (operazione da shell = fiducia dell'operatore; serve per il primo Admin quando le email in produzione non sono ancora configurate) e lo scrive nell'output.
- `IcsCalendarController`: 403 per utenti non verificati.
- `calendar/index`: per i non verificati niente URL/token ICS, al suo posto un invito a verificare l'email.
- `DemoSeeder`: utenti demo verificati.

### 3.3 Migrazione backfill
`2026_10_22_120000_verify_email_of_existing_staff_and_group_members.php`: imposta `email_verified_at = now()` agli utenti non verificati che hanno ruolo `ADMIN`/`CD` o appartengono già a un gruppo (già "conosciuti" dall'associazione). Gli altri membri restano non verificati e verificano dal banner. `down()` vuoto.

### 3.4 Test
- Nuovo `tests/Feature/EmailVerificationTest.php`: registrazione invia `VerifyEmail` e crea utente non verificato; banner e pagina avviso; reinvio; link firmato verifica (e hash errato 403); `addUser` rifiutato/accettato; assegnazione da UI rifiutata e utente assente dall'elenco; feed ICS 403 vs 200; modale ICS senza token per non verificati; `updateRole` CD/ADMIN rifiutato ma Membro ok; `make-admin` verifica l'email.
- Aggiornare i test esistenti che creano utenti con `User::create` (non verificati) e poi li aggiungono a gruppi / chiedono il feed: `AuthorizationGatesTest`, `EventAndGroupLogicTest`, `IcsCalendarControllerTest` -> `User::factory()->create(...)`.

### 3.5 Documentazione
README (sezione funzionalità + `MAIL_*` necessari per la verifica + test), `todo.md` (Fase 26).

## 4. Rischi
- **`MAIL_*` in produzione** (già aperto in todo, fase 18): senza SMTP funzionante nessuno può verificare l'email; fino ad allora usare `app:make-admin` per gli Admin.
- Gli utenti già presenti senza ruolo/gruppo perdono il feed finché non verificano.

## 5. Chiusura
Rinominare in `implementationPlan-V-verificaEmail.md`.
