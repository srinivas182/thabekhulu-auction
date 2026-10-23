<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 4b-2: company and trust bidders, proof of funds and per-listing
 * bidding approval (decision K6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bidding_entities', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();     // the person who acts for it
            $table->string('type', 10);                                           // company | trust
            $table->string('name', 160);
            $table->string('registration_number', 40);                            // CIPC number or Master's Office reference
            $table->text('controllers');                                          // encrypted JSON: directors/trustees and owners of 25%+
            $table->string('status', 16)->default('pending');                    // pending | verified | rejected
            $table->string('rejection_reason', 500)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('entity_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bidding_entity_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30);                                           // registration | resolution | trust_deed | letters_of_authority | ownership
            $table->string('path');                                               // encrypted contents, private disk
            $table->string('mime', 100);
            $table->timestamps();
        });

        Schema::create('proofs_of_funds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bidding_entity_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');                                 // rand shown on the letter/statement
            $table->string('path');                                               // encrypted contents
            $table->string('mime', 100);
            $table->string('status', 16)->default('pending');                    // pending | accepted | rejected
            $table->date('valid_until')->nullable();                              // 90 days after acceptance
            $table->string('note', 500)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bidder_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bidding_entity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('proof_of_funds_id')->nullable()->constrained('proofs_of_funds')->nullOnDelete();
            $table->string('status', 16)->default('pending');                    // pending | approved | rejected | revoked
            $table->string('bidder_number', 6)->nullable();                       // shown publicly instead of names
            $table->json('checklist')->nullable();                                // snapshot at decision time
            $table->string('reason', 500)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['listing_id', 'user_id']);
            $table->unique(['listing_id', 'bidder_number']);
        });
    }

    public function down(): void
    {
        foreach (['bidder_approvals', 'proofs_of_funds', 'entity_documents', 'bidding_entities'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
