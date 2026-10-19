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

        // Manage groups gate - ADMIN or CD
        Gate::define('manage-groups', function (User $user) {
            return $user->hasRole('ADMIN') || $user->hasRole('CD');
        });

        // Manage assemblies and association events - ADMIN or CD
        Gate::define('manage-assemblies', function (User $user) {
            return $user->hasRole('ADMIN') || $user->hasRole('CD');
        });

        // Update session gate: ADMIN, CD, or member of the active group
        Gate::define('update-session', function (User $user, Event $event) {
            if ($user->hasRole('ADMIN') || $user->hasRole('CD')) {
                return true;
            }

            if ($event->type !== 'session' || !$event->group_id) {
                return false;
            }

            // Check if group is active and user belongs to it
            return $user->groups()
                ->where('groups.id', $event->group_id)
                ->where('is_active', true)
                ->exists();
        });
    }
}
