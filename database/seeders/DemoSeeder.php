<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Dati dimostrativi: utenti con password nota, un gruppo e alcune stanze.
 * Si rifiuta di girare in produzione.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('DemoSeeder ignorato in produzione.');

            return;
        }

        // Autosufficiente: garantisce che i ruoli esistano
        $this->call(RoleSeeder::class);

        $admin = User::firstOrCreate(
            ['email' => 'admin@cavalcalupi.it'],
            ['name' => 'Amministratore Cavalcalupi', 'password' => bcrypt('password')]
        );
        $admin->markEmailAsVerified();
        $admin->syncRoles(['ADMIN']);

        $cd = User::firstOrCreate(
            ['email' => 'cd@cavalcalupi.it'],
            ['name' => 'Consiglio Direttivo', 'password' => bcrypt('password')]
        );
        $cd->markEmailAsVerified();
        $cd->syncRoles(['CD']);

        $membro = User::firstOrCreate(
            ['email' => 'membro@cavalcalupi.it'],
            ['name' => 'Membro Associazione', 'password' => bcrypt('password')]
        );
        // Utenti demo con email verificata (altrimenti non potrebbero stare nel gruppo, essere CD/Admin né avere il feed)
        $membro->markEmailAsVerified();
        $membro->syncRoles(['Membro']);

        // Gruppo demo con il membro come responsabile delle sessioni
        $group = Group::firstOrCreate(['name' => 'Tavolo Demo'], ['is_active' => true]);
        $group->users()->syncWithoutDetaching([$membro->id => ['can_manage_sessions' => true]]);

        // Stanze demo: giovedì (4) = 1 + 2 = 3 posti, venerdì (5) = 1 + 1 = 2 posti
        if (Room::count() === 0) {
            $roomA = Room::create(['name' => 'Stanza A', 'capacity' => 1, 'is_active' => true]);
            $roomA->syncWeekdays([4, 5]);

            $roomB = Room::create(['name' => 'Stanza B', 'capacity' => 2, 'is_active' => true]);
            $roomB->syncWeekdays([4]);

            $roomC = Room::create(['name' => 'Stanza C', 'capacity' => 1, 'is_active' => true]);
            $roomC->syncWeekdays([5]);
        }
    }
}
