# Implementation Plan - Permesso "gestione sessioni" per gruppo (Fase 16)

**Riferimento:** `analisi-finale-modifiche.md` sez. 4.2.
**Dipendenze:** `gestioneRuoliUtenti` (`User::isStaff()`), `sicurezzaRegistrazioneEQuickLogin` (`DemoSeeder`).

## 1. Obiettivo
Oggi qualunque membro di un gruppo attivo può creare/modificare le sessioni del gruppo (e cancellare quelle che ha creato). Nuova regola: solo CD/Admin e le persone a cui CD/Admin hanno assegnato il permesso **per quel gruppo**. Il permesso è per gruppo, può essere assegnato a più persone, richiede che la persona sia membro e non si propaga alle stagioni successive.

## 2. Stato attuale (da codice)
- `group_user`: `id`, `user_id`, `group_id`, timestamps, unique `(user_id, group_id)`.
- `User::groups()` e `Group::users()` sono `belongsToMany(...)->withTimestamps()`.
- Gate `update-session` (AppServiceProvider): staff o membro di un gruppo attivo.
- `EventController`:
  - `create`: per i non-staff elenca tutti i gruppi attivi dell'utente;
  - `store`: per i non-staff controlla solo `groups()->where('groups.id', ...)->exists()`;
  - `destroy`: consente anche `created_by === Auth::id()`;
  - `show.blade.php` riproduce la stessa condizione per il pulsante "Elimina".
- `calendar/index.blade.php` mostra "Nuova Sessione / Evento" a ogni utente autenticato.
- `GroupController::removeUser` fa `detach` (il permesso sparisce con la riga pivot).

## 3. Decisioni di progetto
- Permesso = colonna booleana `can_manage_sessions` su `group_user`, default `false` (nessuna ereditarietà tra stagioni: i gruppi vecchi sono inattivi e il Gate richiede gruppo attivo).
- Non si usa un permesso Spatie globale (non sa di "gruppo").
- Dopo il deploy nessun non-staff ha il permesso finché CD/Admin non lo assegna (nessuna migrazione dati automatica).
- Chi ha il permesso può anche **eliminare** le sessioni del proprio gruppo; il solo fatto di averle create non basta più.

## 4. Passi

### 4.1 Database
1. `php artisan make:migration add_can_manage_sessions_to_group_user_table --table=group_user`: `$table->boolean('can_manage_sessions')->default(false)->after('group_id');` (down: `dropColumn`).

### 4.2 Modelli
2. `User::groups()` → `->withPivot('can_manage_sessions')->withTimestamps()`. `Group::users()` idem.
3. `User`:
   - `managedGroups(): BelongsToMany` = `$this->groups()->wherePivot('can_manage_sessions', true)->where('groups.is_active', true)`;
   - `canManageSessionsOf(Group|int $group): bool`: staff → `true`; altrimenti `managedGroups()->where('groups.id', $id)->exists()`.
4. `Group`:
   - `managers(): BelongsToMany` = `users()->wherePivot('can_manage_sessions', true)`;
   - `setSessionManager(User|int $user, bool $value): void`: `DomainException` se il gruppo non è attivo ("Impossibile modificare i responsabili di un gruppo non attivo.") o se l'utente non è membro ("L'utente non appartiene al gruppo."); poi `users()->updateExistingPivot($id, ['can_manage_sessions' => $value])`.
   - `addUser()` non cambia: il default `false` si applica da solo.

### 4.3 Gate
5. `AppServiceProvider`:
   - `manage-group-sessions` (User, Group) → `$user->canManageSessionsOf($group)`;
   - `update-session` riscritto: staff → `true`; altrimenti solo se `type === 'session'`, `group_id` presente e `canManageSessionsOf($event->group_id)`;
   - nuovo `delete-event` (User, Event): staff, oppure sessione per cui `update-session` è consentito.

### 4.4 Controller
6. `EventController`:
   - `create`: per i non-staff `$groups = $user->managedGroups()->orderBy('name')->get()`; se vuoto, la vista mostra il messaggio "Non hai il permesso di gestire sessioni in nessun gruppo: chiedi a un CD o Admin."; i non-staff non vedono le opzioni assemblea/evento associativo (già così). Passare anche `$noPermission`.
   - `store`: per `type=session` e non-staff: `Gate::authorize('manage-group-sessions', $group)` al posto del controllo di appartenenza (403 se negato).
   - `edit/update`: nessun cambiamento di logica (usano `update-session`); sostituire `hasRole('ADMIN') || hasRole('CD')` con `$user->isStaff()`. Per i non-staff, `$groups` resta limitato ai gruppi gestiti.
   - `destroy`: `Gate::authorize('delete-event', $event)` (rimuovere il controllo `created_by`).
7. `GroupController`: nuovo `setManager(Request $request, Group $group, User $user)`: `Gate::authorize('manage-groups')`; valida `can_manage_sessions` boolean; `try { $group->setSessionManager($user, $request->boolean('can_manage_sessions')); } catch (DomainException $e) { return back()->withErrors(['can_manage_sessions' => $e->getMessage()]); }`; messaggio di successo.
8. `routes/web.php` (gruppo `admin`): `PUT /groups/{group}/users/{user}/manager` → `groups.users.manager`.
9. `CalendarController::index`: passare `$canCreateSessions = $user && ($user->isStaff() || $user->managedGroups()->exists())`.

### 4.5 Viste
10. `admin/groups/index.blade.php`: per ogni membro mostrare un'etichetta "⭐ Responsabile" se `$member->pivot->can_manage_sessions`, e un piccolo form `PUT` (campo nascosto `can_manage_sessions` = valore opposto) con pulsante "Rendi responsabile" / "Togli responsabilità". Sotto il titolo del gruppo: "Responsabili: N" oppure, se zero, "Nessun responsabile: solo CD/Admin possono modificare le sessioni". Per i gruppi inattivi mostrare l'etichetta ma senza pulsanti.
11. `events/show.blade.php`: il pulsante "Elimina" diventa `@can('delete-event', $event)`.
12. `calendar/index.blade.php`: pulsante "Nuova Sessione / Evento" solo se `$canCreateSessions`; `events/create.blade.php`: se `$noPermission` mostrare l'avviso al posto della selezione del gruppo e disabilitare il submit per le sessioni.
13. `DemoSeeder` (solo locale): creare "Tavolo Demo" attivo e collegare l'utente `membro@cavalcalupi.it` con `can_manage_sessions = true`, così il flusso è provabile in locale.

## 5. Test
- `AuthorizationGatesTest::test_membro_can_only_update_own_active_group_session` → riscrivere in più casi: membro senza permesso → `update-session` **false**; con permesso nel gruppo A → true su A, false su B; con permesso su A e membro di B senza permesso → B false; gruppo A disattivato → false; assemblea → false; staff → sempre true.
- Nuovo `SessionManagementPermissionTest` (HTTP):
  - membro con permesso crea una sessione nel proprio gruppo (nota: quando arriveranno le stanze, il test configura una stanza tramite l'helper del piano `stanzeERegoleCalendario`);
  - membro senza permesso: `POST /events` → 403; `GET /events/{id}/edit` → 403; `PUT` → 403; `DELETE` → 403, **anche se ha creato lui l'evento** (caso regressione di `created_by`);
  - membro con permesso elimina una sessione del proprio gruppo; non quella di un altro gruppo;
  - CD/Admin impostano il permesso via `PUT /admin/groups/{g}/users/{u}/manager` (200/redirect), un `Membro` ottiene 403;
  - impostare il permesso a un non-membro → errore di validazione, nessuna modifica; su gruppo inattivo → errore;
  - `removeUser` elimina il permesso (riaggiungere l'utente → permesso `false`);
  - due utenti dello stesso gruppo con permesso: entrambi possono modificare.
- `CalendarAndWebRoutesTest`: lo scenario "membro" non deve più presumere che ogni membro possa creare; aggiornare se necessario.
- Verifica visiva: `admin/groups` mostra etichette e pulsanti; `/calendar` per un membro senza permesso non mostra "Nuova Sessione".

## 6. Documentazione
- `README.md`: tabella "Ruoli e Permessi" e "Logica di Business" (§1: sessioni gestite dal permesso per gruppo); sezione test aggiornata.
- `situazione-di-partenza.md`: aggiungere in fondo la sezione "Aggiornamenti (20/10/2026)" con la nuova regola ("non ogni membro, ma chi ha il permesso del gruppo"), senza riscrivere il testo originale.
- `analisi-finale-modifiche.md`: nessuna modifica.
- `todo.md`: Fase 16.

## 7. Criteri di accettazione
- Un membro senza permesso non può creare, modificare, spostare né eliminare sessioni (403), neppure le proprie.
- Il permesso su un gruppo non vale su un altro; sparisce quando l'utente esce dal gruppo; non conta su gruppi inattivi.
- CD/Admin assegnano e tolgono il permesso dalla pagina gruppi.
- Test verdi.

## 8. Rischi e deploy
- Subito dopo il deploy i membri non potranno modificare nulla: prima di annunciarlo, CD/Admin devono assegnare i responsabili dei gruppi attivi (comunicarlo su Telegram).
- Nessuna perdita di dati: la migrazione aggiunge solo una colonna.

## 9. Chiusura
Rinominare in `implementationPlan-V-permessoGestioneSessioni.md`.
