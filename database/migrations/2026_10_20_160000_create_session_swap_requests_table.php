<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_swap_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->date('from_date');
            $table->date('target_date');
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
            $table->unsignedBigInteger('matched_request_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason')->nullable();
            // 1 se pending, NULL altrimenti: con l'unique sotto, una sessione ha al massimo una richiesta pendente
            $table->unsignedTinyInteger('pending_flag')->nullable();
            $table->timestamps();

            $table->foreign('matched_request_id')->references('id')->on('session_swap_requests')->nullOnDelete();
            $table->unique(['event_id', 'pending_flag']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_swap_requests');
    }
};
