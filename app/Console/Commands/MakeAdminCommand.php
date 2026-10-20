<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Facades\Log;

class MakeAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:make-admin {email : Email di un utente già registrato}';

    /**
     * The console command description.
     */
    protected $description = 'Promuove un utente esistente a ADMIN (e rimuove eventuali altri ruoli).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error('Utente non trovato: registrarsi prima dal sito.');
            return self::FAILURE;
        }

        // Ensure ADMIN role exists
        $adminRole = Role::firstOrCreate([
            'name' => 'ADMIN',
            'guard_name' => 'web',
        ]);

        // Sync role to ADMIN only
        $user->syncRoles([$adminRole->name]);

        // Forget cached permissions
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Log::info('Utente promosso a ADMIN via comando', ['user_id' => $user->id, 'email' => $email]);

        $this->info("Utente {$email} è ora ADMIN.");
        return self::SUCCESS;
    }
}
