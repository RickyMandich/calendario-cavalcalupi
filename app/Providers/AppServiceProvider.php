<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\User;
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
        // Log Viewer authorization - ADMIN only
        Gate::define('viewLogViewer', function (?User $user) {
            return $user !== null && $user->hasRole('ADMIN');
        });

        // Manage groups gate - staff (ADMIN or CD)
        Gate::define('manage-groups', fn (User $user) => $user->isStaff());

        // Manage assemblies gate - staff
        Gate::define('manage-assemblies', fn (User $user) => $user->isStaff());

        // Manage users gate - staff
        Gate::define('manage-users', fn (User $user) => $user->isStaff());
        // Manage rooms gate - staff
        Gate::define('manage-rooms', fn (User $user) => $user->isStaff());
        // Manage group sessions gate
        Gate::define('manage-group-sessions', function (User $user, \App\Models\Group $group) {
            return $user->canManageSessionsOf($group);
        });

        // Manage users gate - staff
        Gate::define('manage-users', fn (User $user) => $user->isStaff());

        // Update session gate: ADMIN, CD, or users with session management permission
        Gate::define('update-session', function (User $user, Event $event) {
            if ($user->isStaff()) {
                return true;
            }

            if ($event->type !== 'session' || !$event->group_id) {
                return false;
            }

            // Use the new permission check
            return $user->canManageSessionsOf($event->group);
        });

        // Delete event gate: staff or session manager for the group
        Gate::define('delete-event', function (User $user, Event $event) {
            if ($user->isStaff()) {
                return true;
            }
            if ($event->type !== 'session' || !$event->group_id) {
                return false;
            }
            return $user->canManageSessionsOf($event->group);
        });
    }
}
