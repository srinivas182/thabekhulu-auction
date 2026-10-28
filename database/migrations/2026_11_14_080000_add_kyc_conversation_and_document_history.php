<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KYC review as a conversation (requests, buyer replies, re-uploads, decisions),
 * and documents kept when replaced (FICA record-keeping) instead of deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author', 10);             // reviewer | buyer
            $table->string('kind', 20);               // info_request | reply | document | submitted | rejected | verified
            $table->text('body')->nullable();
            $table->timestamp('created_at');
            $table->index(['verification_id', 'id']);
        });
        Schema::table('verification_documents', function (Blueprint $table) {
            $table->timestamp('superseded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('verification_documents', fn (Blueprint $table) => $table->dropColumn('superseded_at'));
        Schema::dropIfExists('verification_messages');
    }
};
