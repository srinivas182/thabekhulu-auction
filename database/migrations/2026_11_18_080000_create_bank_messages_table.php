<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refund bank account review as a conversation. Keyed by the buyer, because
 * each resubmission is a new bank account record and the thread must carry on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();                     // the buyer
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author', 10);                                                      // reviewer | buyer
            $table->string('kind', 20);                                                        // info_request | reply | submitted | verified | rejected
            $table->text('body')->nullable();
            $table->timestamp('created_at');
            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_messages');
    }
};
