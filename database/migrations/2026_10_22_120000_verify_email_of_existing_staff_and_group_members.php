<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Da ora gli utenti con email non verificata possono essere solo Membro, senza gruppi e senza feed ICS.
 * Per non togliere l'accesso a chi è già dentro all'associazione, gli utenti esistenti che hanno
 * ruolo ADMIN/CD o appartengono già a un gruppo vengono considerati verificati. Tutti gli altri
 * restano non verificati e verificano dal banner/link ricevuto per email.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tables = config('permission.table_names');

        $staffIds = DB::table($tables['model_has_roles'])
            ->join($tables['roles'], $tables['roles'].'.id', '=', $tables['model_has_roles'].'.role_id')
            ->where($tables['model_has_roles'].'.model_type', 'App\\Models\\User')
            ->whereIn($tables['roles'].'.name', ['ADMIN', 'CD'])
            ->pluck($tables['model_has_roles'].'.model_id');

        $memberIds = DB::table('group_user')->pluck('user_id');

        $ids = $staffIds->merge($memberIds)->unique()->values()->all();

        if ($ids !== []) {
            DB::table('users')
                ->whereNull('email_verified_at')
                ->whereIn('id', $ids)
                ->update(['email_verified_at' => now()]);
        }
    }

    public function down(): void
    {
        // Nessun ripristino: non si può sapere quali utenti erano non verificati prima.
    }
};
