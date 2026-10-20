<?php

namespace Database\Seeders;

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
    }
}
