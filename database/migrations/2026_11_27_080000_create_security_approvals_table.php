<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One-time approval codes emailed to the security approvers for sensitive back-office actions. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();   // who asked
            $table->string('action', 120);                                    // route name
            $table->string('summary', 2000);                                  // what the approvers were told
            $table->string('payload_hash', 64);                               // ties the code to this exact action and data
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('ip', 45)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at');
            $table->index(['user_id', 'action', 'payload_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_approvals');
    }
};
