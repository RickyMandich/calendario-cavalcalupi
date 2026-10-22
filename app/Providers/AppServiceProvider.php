<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Email di verifica in italiano
        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            return (new MailMessage)
                ->subject('Verifica il tuo indirizzo email')
                ->greeting('Ciao '.$notifiable->name.',')
                ->line('Conferma il tuo indirizzo email per poter essere assegnato ai gruppi e usare il feed ICS personale.')
                ->action('Verifica email', $url)
                ->line('Se non ti sei registrato tu, ignora questo messaggio.');
        });

        // Log Viewer authorization - ADMIN only
        Gate::define('viewLogViewer', function (?User $user) {
            return $user !== null && $user->hasRole('ADMIN');
        });

        // Gate riservati allo staff (ADMIN o CD, stessi poteri)
        Gate::define('manage-groups', fn (User $user) => $user->isStaff());
        Gate::define('manage-assemblies', fn (User $user) => $user->isStaff());
        Gate::define('manage-users', fn (User $user) => $user->isStaff());
        Gate::define('manage-rooms', fn (User $user) => $user->isStaff());
        Gate::define('import-calendar', fn (User $user) => $user->isStaff());

        // Permesso "gestione sessioni" per gruppo (staff sempre; altri solo se assegnato in quel gruppo)
        Gate::define('manage-group-sessions', fn (User $user, Group $group) => $user->canManageSessionsOf($group));

        // Modifica/spostamento di una sessione
        Gate::define('update-session', function (User $user, Event $event) {
            if ($user->isStaff()) {
                return true;
            }

            if ($event->type !== 'session' || ! $event->group_id) {
                return false;
            }

            return $user->canManageSessionsOf($event->group_id);
        });

        // Eliminazione evento: staff, oppure chi gestisce le sessioni del gruppo
        Gate::define('delete-event', function (User $user, Event $event) {
            if ($user->isStaff()) {
                return true;
            }

            if ($event->type !== 'session' || ! $event->group_id) {
                return false;
            }

            return $user->canManageSessionsOf($event->group_id);
        });
    }
}
