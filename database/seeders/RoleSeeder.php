<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create roles
        $adminRole = Role::firstOrCreate(['name' => 'ADMIN', 'guard_name' => 'web']);
        $cdRole = Role::firstOrCreate(['name' => 'CD', 'guard_name' => 'web']);
        $membroRole = Role::firstOrCreate(['name' => 'Membro', 'guard_name' => 'web']);

        // Create default users
        $admin = User::firstOrCreate(
            ['email' => 'admin@cavalcalupi.it'],
            [
                'name' => 'Amministratore Cavalcalupi',
                'password' => bcrypt('password'),
            ]
        );
        $admin->syncRoles([$adminRole]);

        $cd = User::firstOrCreate(
            ['email' => 'cd@cavalcalupi.it'],
            [
                'name' => 'Consiglio Direttivo',
                'password' => bcrypt('password'),
            ]
        );
        $cd->syncRoles([$cdRole]);

        $membro = User::firstOrCreate(
            ['email' => 'membro@cavalcalupi.it'],
            [
                'name' => 'Membro Associazione',
                'password' => bcrypt('password'),
            ]
        );
        $membro->syncRoles([$membroRole]);

        // Create sample groups
        $groupA = Group::firstOrCreate(
            ['name' => 'Tavolo D&D 5e: Le Cronache di Faerun'],
            ['is_active' => true]
        );
        $groupB = Group::firstOrCreate(
            ['name' => 'Tavolo Call of Cthulhu: Ombre su Innsmouth'],
            ['is_active' => true]
        );
        $groupC = Group::firstOrCreate(
            ['name' => 'Tavolo Cyberpunk Red: Night City (Concluso)'],
            ['is_active' => false]
        );

        // Assign membro to group A
        $groupA->addUser($membro);
        $groupA->addUser($admin);
        $groupB->addUser($cd);

        // Create sample events if none exist
        if (Event::count() === 0) {
            Event::create([
                'title' => 'Assemblea Ordinaria Soci',
                'description' => 'Discussione bilancio e approvazione attività annuali.',
                'start_time' => now()->addDays(3)->setTime(20, 30),
                'end_time' => now()->addDays(3)->setTime(23, 00),
                'type' => 'assembly',
                'created_by' => $cd->id,
            ]);

            Event::create([
                'title' => 'Serata Giochi da Tavolo & One-Shot',
                'description' => 'Apertura straordinaria della sede per serate di gioco aperte a tutti.',
                'start_time' => now()->addDays(5)->setTime(21, 00),
                'end_time' => now()->addDays(5)->setTime(23, 59),
                'type' => 'association_event',
                'created_by' => $cd->id,
            ]);

            Event::create([
                'title' => 'D&D 5e - Sessione 12: La Cripta dei Sussurri',
                'description' => 'Esplorazione del dungeon sotterraneo nelle terre selvagge.',
                'start_time' => now()->addDays(2)->setTime(21, 00),
                'end_time' => now()->addDays(2)->setTime(23, 30),
                'type' => 'session',
                'group_id' => $groupA->id,
                'created_by' => $membro->id,
            ]);

            Event::create([
                'title' => 'Call of Cthulhu - Sessione 4: Il Faro Abbandonato',
                'description' => 'Indagine notturna sulle sparizioni al molo.',
                'start_time' => now()->addDays(6)->setTime(21, 15),
                'end_time' => now()->addDays(6)->setTime(23, 45),
                'type' => 'session',
                'group_id' => $groupB->id,
                'created_by' => $cd->id,
            ]);
        }
    }
}
