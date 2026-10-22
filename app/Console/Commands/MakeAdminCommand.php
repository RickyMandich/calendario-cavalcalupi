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
    protected $description = 'Promuove un utente esistente a ADMIN (rimuove altri ruoli e segna l\'email come verificata).';

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

        // Un Admin deve avere l'email verificata: l'operazione da shell vale come verifica
        // (serve anche per il primo Admin quando le email in produzione non sono ancora configurate).
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            $this->warn("L'email di {$email} non era verificata: segnata come verificata.");
        }

        // Sync role to ADMIN only
        $user->syncRoles([$adminRole->name]);

        // Forget cached permissions
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Log::info('Utente promosso a ADMIN via comando', ['user_id' => $user->id, 'email' => $email]);

        $this->info("Utente {$email} è ora ADMIN.");
        return self::SUCCESS;
    }
}
