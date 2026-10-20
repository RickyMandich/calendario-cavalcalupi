<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->warn('DemoSeeder ignored in production.');
            return;
        }

        // Ensure roles exist
        $this->call(RoleSeeder::class);

        $admin = User::firstOrCreate(
            ['email' => 'admin@cavalcalupi.it'],
            [
                'name' => 'Amministratore Cavalcalupi',
                'password' => bcrypt('password'),
            ]
        );
        $admin->syncRoles(['ADMIN']);

        $cd = User::firstOrCreate(
            ['email' => 'cd@cavalcalupi.it'],
            [
                'name' => 'Consiglio Direttivo',
                'password' => bcrypt('password'),
            ]
        );
        $cd->syncRoles(['CD']);

        $membro = User::firstOrCreate(
            ['email' => 'membro@cavalcalupi.it'],
            [
                'name' => 'Membro Associazione',
                'password' => bcrypt('password'),
            ]
        );
        $membro->syncRoles(['Membro']);
    }
}
