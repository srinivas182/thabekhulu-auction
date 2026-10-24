<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 8c: one queue of suspicious-activity items for a person to review
 * (T7). The platform gathers evidence; Thabekhulu's compliance officer
 * decides and files any report with the FIC outside the platform.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suspicious_items', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('source', 24);                         // integrity | screening | downloads | bank_line | default | lockout | bid_code
            $table->string('fingerprint', 190)->unique();         // each signal is recorded once
            $table->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('summary', 500);
            $table->json('details')->nullable();
            $table->string('status', 12)->default('open');        // open | reviewing | dismissed | escalated
            $table->timestamp('detected_at');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'detected_at']);
        });

        Schema::create('suspicious_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('suspicious_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('note');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suspicious_notes');
        Schema::dropIfExists('suspicious_items');
    }
};
