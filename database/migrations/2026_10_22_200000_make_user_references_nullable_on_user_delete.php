<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eliminare un utente non deve cancellare ciò che ha creato: sessioni/eventi, log delle modifiche
 * e richieste di scambio restano, con il riferimento all'utente messo a NULL.
 * (Prima le tre FK erano `cascadeOnDelete`: cancellare un utente cancellava tutte le sue sessioni.)
 */
return new class extends Migration
{
    /** tabella => colonna che punta a users.id */
    private const REFERENCES = [
        'events' => 'created_by',
        'event_logs' => 'user_id',
        'session_swap_requests' => 'requested_by',
    ];

    public function up(): void
    {
        foreach (self::REFERENCES as $table => $column) {
            Schema::table($table, function (Blueprint $t) use ($column) {
                $t->dropForeign([$column]);
            });

            Schema::table($table, function (Blueprint $t) use ($column) {
                $t->unsignedBigInteger($column)->nullable()->change();
            });

            Schema::table($table, function (Blueprint $t) use ($column) {
                $t->foreign($column)->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    /**
     * Ripristina le FK a cascata e le colonne NOT NULL. Fallisce se esistono righe con riferimento NULL
     * (utenti già eliminati): è voluto, così nessun dato viene cancellato di nascosto.
     */
    public function down(): void
    {
        foreach (self::REFERENCES as $table => $column) {
            Schema::table($table, function (Blueprint $t) use ($column) {
                $t->dropForeign([$column]);
            });

            Schema::table($table, function (Blueprint $t) use ($column) {
                $t->unsignedBigInteger($column)->nullable(false)->change();
            });

            Schema::table($table, function (Blueprint $t) use ($column) {
                $t->foreign($column)->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }
};
