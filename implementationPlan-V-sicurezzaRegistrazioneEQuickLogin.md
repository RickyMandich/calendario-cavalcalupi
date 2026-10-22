# Implementation Plan - Sicurezza di registrazione e quick-login (Fase 14, P0)

**Riferimento:** `analisi-finale-modifiche.md` sez. 3.
**Dipendenze:** `verificaStatoAttuale` (baseline test verdi).
**Priorità:** massima. Con la registrazione pubblica, gli account demo con password `password` e le rotte `/quick-login/*` sono un accesso Admin aperto a chiunque.

## 1. Obiettivo
1. Gli utenti demo e il quick-login esistono **solo in ambiente `local`**.
2. Il seeding in produzione crea solo i ruoli.
3. Registrazione e login protetti da throttle; registrazione che produce solo `Membro`, senza gruppi.
4. Pagina pubblica `/calendar` che non espone token, email o storico.
5. Ripulire un eventuale account demo già presente in produzione, senza perdere eventi reali.

## 2. Stato attuale (da codice)
- `routes/web.php`: `Route::get('/quick-login/{role}', ...)->name('quick.login')` dentro `Route::middleware('guest')`.
- `AuthController::quickLogin` fa login con tre email fisse, senza verificare l'ambiente.
- `resources/views/auth/login.blade.php`: blocco "Accesso Rapido Demo" con la password mostrata.
- `database/seeders/RoleSeeder.php`: crea ruoli **e** i tre utenti con `bcrypt('password')`; `DatabaseSeeder` lo chiama sempre.
- Test che dipendono dagli utenti demo: `CalendarAndWebRoutesTest` (`User::where('email', 'membro@cavalcalupi.it')`, `test_login_and_quick_login`). `AuthorizationGatesTest` usa `RoleSeeder` solo per i ruoli.

## 3. Decisioni di progetto
- Il `RoleSeeder` diventa idempotente e **solo ruoli** (`ADMIN`, `CD`, `Membro`, `firstOrCreate`).
- Nuovo `DemoSeeder` con utenti demo (e, nei piani successivi, gruppo demo e stanze demo). Si rifiuta di girare in produzione.
- `DatabaseSeeder` chiama sempre `RoleSeeder`; chiama `DemoSeeder` solo se `app()->environment('local')`.
- La rotta quick-login **non viene registrata** fuori da `local`; il controller ha comunque un `abort_unless` come seconda difesa.
- Throttle: `throttle:10,1` su `POST /login`, `throttle:6,1` su `POST /register`.

## 4. Passi

### 4.1 Seeder
1. `RoleSeeder`: rimuovere la parte che crea utenti; lasciare `forgetCachedPermissions()` e i tre `Role::firstOrCreate`.
2. Creare `database/seeders/DemoSeeder.php` con il codice utenti oggi in `RoleSeeder` (admin, cd, membro, password `password`). In `run()`: se `app()->isProduction()` → `$this->command?->warn('DemoSeeder ignorato in produzione.')` e `return`. Chiama internamente `RoleSeeder` (o `$this->call(RoleSeeder::class)`) per essere autosufficiente.
3. `DatabaseSeeder::run()`: `$this->call(RoleSeeder::class); if (app()->environment('local')) { $this->call(DemoSeeder::class); }`.

### 4.2 Quick login solo in locale
4. `routes/web.php`: togliere la riga dal gruppo `guest` e registrare
   `if (app()->environment('local')) { Route::middleware('guest')->get('/quick-login/{role}', [AuthController::class, 'quickLogin'])->name('quick.login'); }`.
5. `AuthController::quickLogin`: prima riga `abort_unless(app()->environment('local'), 404);`.
6. `auth/login.blade.php`: avvolgere il blocco "Accesso Rapido Demo" in `@if(app()->environment('local')) ... @endif`. Il blocco non deve nominare la password in nessun altro ambiente.

### 4.3 Registrazione e login
7. `AuthController::register`: invariato nella sostanza (`assignRole('Membro')`). Aggiungere un commento che dichiara l'invariante "mai ruoli privilegiati né gruppi da qui". Se esistono Role `Membro` mancanti (DB non seedato) la registrazione fallirebbe con eccezione: usare `Role::firstOrCreate(['name' => 'Membro', 'guard_name' => 'web'])` prima di `assignRole`, così il sistema non si rompe su un DB senza seed.
8. `User::$fillable`: prima verificare con `grep -rn "ics_token" app tests database` dove viene passato in `create()`. Se compare solo negli accessor/`saving` (assegnazione diretta) e nei test che leggono, rimuovere `'ics_token'` da `$fillable`. Se qualche test lo passa a `create()`, usare `forceFill()->save()` in quel test.
9. `routes/web.php`: aggiungere `->middleware('throttle:10,1')` alla rotta `login.submit` e `->middleware('throttle:6,1')` a `register.submit`.

### 4.4 Esposizione dati nella pagina pubblica
10. Verificare (nessuna modifica attesa) che `calendar/index.blade.php` mostri il modale ICS e l'URL con token solo dentro `@auth`, e che per gli ospiti non compaia né `ics_token` né email di nessuno (il nome del creatore è già mostrato oggi: va bene, sono dati organizzativi).
11. Aggiungere i test di non-regressione della sez. 5.

### 4.5 Pulizia di produzione (operativa, non codice)
12. Se il passo 6 di `verificaStatoAttuale` ha trovato account demo in produzione, **non eliminarli direttamente**: `events.created_by` e `event_logs.user_id` hanno `cascadeOnDelete`, quindi cancellare un utente cancella gli eventi che ha creato.
    Procedura:
    - far registrare l'Admin reale e promuoverlo (piano `gestioneRuoliUtenti`; fino ad allora con `tinker`: `User::where('email','...')->first()->syncRoles(['ADMIN'])`);
    - riassegnare `events.created_by` e `event_logs.user_id` dall'utente demo all'Admin reale (query di `UPDATE`);
    - solo dopo, eliminare gli utenti demo (oppure, se ci sono dubbi, cambiare la password con una casuale e togliere i ruoli).
13. In produzione eseguire i seeder solo così: `php artisan migrate --force` e, una volta, `php artisan db:seed --class=RoleSeeder --force` (i ruoli devono esistere perché la registrazione assegna `Membro`).

## 5. Test
Aggiornare/creare in `tests/Feature`:
- `CalendarAndWebRoutesTest`: `setUp` → `$this->seed(RoleSeeder::class); $this->seed(DemoSeeder::class);` (l'ambiente di test non è produzione). Sostituire `test_login_and_quick_login` con un vero login via `POST /login` sull'utente demo admin; aggiungere `test_quick_login_is_not_available_outside_local` (`get('/quick-login/admin')` → 404, ambiente `testing`).
- `AuthorizationGatesTest`: invariato (usa solo i ruoli).
- Nuovo `RegistrationSecurityTest`:
  - la registrazione crea un utente con solo il ruolo `Membro`, nessun gruppo (`$user->groups()->count() === 0`);
  - un POST di registrazione con campi extra (`role`, `ics_token`, `is_admin`) li ignora;
  - `DemoSeeder` in produzione non crea utenti (`$this->app['env'] = 'production'` e `$this->seed(DemoSeeder::class)` → `User::count() === 0`);
  - `DatabaseSeeder` in `testing` crea i ruoli e non crea utenti.
- Nuovo `PublicCalendarExposureTest`: `GET /calendar` da ospite non contiene `ics_token` di alcun utente né il blocco "Accesso Rapido Demo"; `GET /events/{id}` da ospite → redirect a login.
- Throttle: 11 `POST /login` errati dallo stesso IP → l'undicesimo risponde 429.

## 6. Documentazione
- `README.md`: sezione "Credenziali Utenti Demo" → precisare che esistono **solo in locale** (`APP_ENV=local`, dopo `migrate --seed`) e che in produzione si esegue solo `migrate --force` + `db:seed --class=RoleSeeder --force`. Rimuovere/aggiornare le righe sul quick-login e la lista test.
- `todo.md`: Fase 14 con i passi sopra.

## 7. Criteri di accettazione
- `GET /quick-login/admin` → 404 con `APP_ENV` diverso da `local`; la pagina di login non mostra pulsanti demo né password.
- `php artisan db:seed` con `APP_ENV=production` non crea alcun utente.
- Nessun account demo con password `password` esiste in produzione.
- Registrazione: solo `Membro`; 429 dopo troppi tentativi.
- `php artisan test` verde.

## 8. Rischi e rollback
- Gli ambienti di sviluppo esistenti restano `local`: nessun cambiamento per chi sviluppa.
- Se in produzione l'unico Admin era l'utente demo, **prima** di rimuoverlo ci deve essere un Admin reale: ordine obbligato del passo 12.

## 9. Chiusura
Rinominare in `implementationPlan-V-sicurezzaRegistrazioneEQuickLogin.md`.
