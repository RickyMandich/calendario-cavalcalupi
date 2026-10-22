# Implementation Plan - Gestione ruoli degli utenti da interfaccia (Fase 15)

**Riferimento:** `analisi-finale-modifiche.md` sez. 4.1.
**Dipendenze:** `sicurezzaRegistrazioneEQuickLogin` (seeder separati, `RoleSeeder` solo ruoli).

## 1. Obiettivo
Chi si registra è `Membro`. Il primo Admin si crea da shell; da quel momento **Admin e CD** (stessi poteri) assegnano i ruoli `Membro` / `CD` / `ADMIN` da una pagina "Utenti". L'unica differenza tra Admin e CD resta l'accesso a `/log-viewer`.

## 2. Stato attuale
- Ruoli Spatie (`HasRoles` su `User`), nessuna UI per cambiarli.
- Gate esistenti in `AppServiceProvider`: `viewLogViewer` (solo ADMIN), `manage-groups` e `manage-assemblies` (ADMIN o CD). Le espressioni `hasRole('ADMIN') || hasRole('CD')` sono ripetute in `EventController` (4 volte), nei Gate e nelle viste.
- Rotte admin: gruppo `admin` con `can:manage-groups` in `routes/web.php`.
- Layout: link "Log Viewer" già visibile solo agli `@role('ADMIN')`.

## 3. Decisioni di progetto
- Un utente ha **un solo ruolo** tra i tre (`syncRoles([$ruolo])`).
- Nuovo Gate `manage-users` = ADMIN o CD.
- Non si può rimuovere l'ultimo `ADMIN` (anche se è l'utente che sta agendo).
- Ogni cambio ruolo viene scritto nel log applicativo (`Log::info`), consultabile dall'Admin nel log-viewer.
- Helper `User::isStaff()` per eliminare i duplicati.

## 4. Passi

### 4.1 Helper e Gate
1. `app/Models/User.php`: aggiungere `public function isStaff(): bool { return $this->hasAnyRole(['ADMIN', 'CD']); }`.
2. `AppServiceProvider`: aggiungere `Gate::define('manage-users', fn (User $user) => $user->isStaff());`. Riscrivere `manage-groups` e `manage-assemblies` con `isStaff()` (comportamento identico). Lasciare `viewLogViewer` solo ADMIN.
3. Sostituire nelle viste/controller esistenti le espressioni duplicate **solo dove si tocca il file in questo piano** (il resto in `permessoGestioneSessioni`, che riscrive `EventController`).

### 4.2 Comando per il primo Admin
4. `php artisan make:command MakeAdminCommand --command=app:make-admin` → `app/Console/Commands/MakeAdminCommand.php`:
   - signature `app:make-admin {email : Email di un utente già registrato}`;
   - cerca l'utente (`User::where('email', $email)->first()`); se manca: `$this->error('Utente non trovato: registrarsi prima dal sito.')` e `return self::FAILURE`;
   - `Role::firstOrCreate(['name' => 'ADMIN', 'guard_name' => 'web'])`, poi `$user->syncRoles(['ADMIN'])`;
   - `app(PermissionRegistrar::class)->forgetCachedPermissions()`;
   - stampa conferma e `Log::info`.
   - uso: `docker compose exec app php artisan app:make-admin tua@email.it`.

### 4.3 Pagina "Utenti"
5. `app/Http/Controllers/UserController.php`:
   - `index(Request $request)`: `Gate::authorize('manage-users')`; query `User::with('roles')->orderBy('name')`, filtro `?q=` su `name`/`email` (`where(fn ($q) => $q->where('name','like',"%$s%")->orWhere('email','like',"%$s%"))`), `->paginate(20)->withQueryString()`.
   - `updateRole(Request $request, User $user)`: `Gate::authorize('manage-users')`; valida `role` con `Rule::in(['Membro','CD','ADMIN'])`; se `$user->hasRole('ADMIN') && $validated['role'] !== 'ADMIN'` e `User::role('ADMIN')->count() <= 1` → `back()->withErrors(['role' => 'Deve restare almeno un Admin.'])`; altrimenti `$user->syncRoles([$role])`, `Log::info('Ruolo utente modificato', ['by' => $request->user()->id, 'user' => $user->id, 'from' => $old, 'to' => $role])`, redirect con messaggio di successo.
6. `routes/web.php` (dentro `auth`, prefisso `admin`, nome `admin.`): nuovo gruppo `->middleware('can:manage-users')` con `GET /users` → `users.index` e `PUT /users/{user}/role` → `users.role`.
7. `resources/views/admin/users/index.blade.php` (estende `layouts.app`): intestazione come `admin/groups/index`, form di ricerca (`.field` + `.btn-secondary`), tabella con nome, email, data registrazione, badge ruolo (Admin rosso, CD amber, Membro verde, come nel layout), `<select name="role">` + `.btn-primary` "Salva" per riga (form `PUT`), paginazione `{{ $users->links() }}`. Usare solo le classi componente (`.panel`, `.field`, `.btn-*`) così il tema scuro funziona senza modifiche.
8. `layouts/app.blade.php`: nel blocco `@auth`, accanto a "Gestione Gruppi", `@can('manage-users')` link "👤 Utenti" (`admin.users.*`). Il link Log Viewer resta sotto `@role('ADMIN')`.

## 5. Test (`tests/Feature/UserRolesTest.php`)
- Ospite su `/admin/users` → redirect a login; `Membro` → 403.
- CD e Admin vedono la pagina (200) e possono cambiare il ruolo di un altro utente (incluso promuovere ad `ADMIN`).
- Ruolo non valido → errore di validazione, nessuna modifica.
- Con un solo Admin, il tentativo di declassarlo → errore e ruolo invariato; con due Admin il declassamento riesce.
- Il filtro `?q=` restituisce solo gli utenti corrispondenti.
- `MakeAdminCommand`: promuove un utente esistente (`$this->artisan('app:make-admin', ['email' => ...])->assertSuccessful()`); email inesistente → exit code 1; idempotente se già Admin.
- `AuthorizationGatesTest` (già presente) continua a garantire che CD **non** acceda a `viewLogViewer`; aggiungere `manage-users` per ADMIN/CD true e `Membro` false.

## 6. Documentazione
- `README.md`: tabella "Ruoli e Permessi" (Admin e CD equivalenti; l'Admin in più ha `/log-viewer`), sezione "Primo avvio in produzione": registrarsi dal sito → `app:make-admin` → gestione ruoli dalla pagina Utenti. Aggiungere il test `UserRolesTest` all'elenco.
- `todo.md`: Fase 15.

## 7. Criteri di accettazione
- Un nuovo utente registrato risulta `Membro`; dopo `app:make-admin` risulta `ADMIN` e vede "Utenti" e "Log Viewer".
- Un CD vede "Utenti" ma **non** "Log Viewer" e non può aprire `/log-viewer`.
- Non è possibile restare senza Admin.
- Test verdi.

## 8. Rischi
- Cache dei permessi Spatie: `syncRoles` la invalida; il comando la svuota esplicitamente.
- Un CD può promuovere ad Admin (stessa autorità, scelta confermata): nessun controllo aggiuntivo.

## 9. Chiusura
Rinominare in `implementationPlan-V-gestioneRuoliUtenti.md`.
