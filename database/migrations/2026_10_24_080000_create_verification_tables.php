<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 4a: pluggable verification providers and buyer verification records.
 * Credentials and ID numbers are encrypted; a keyed hash of the ID number
 * enforces one account per ID without storing it in plain text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_providers', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30)->unique();                   // smile_id | verifyid | sumsub
            $table->boolean('enabled')->default(false);
            $table->string('mode', 10)->default('sandbox');        // sandbox | live
            $table->text('credentials')->nullable();               // encrypted JSON
            $table->timestamp('last_success_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('verification_routes', function (Blueprint $table) {
            $table->string('check_type', 30)->primary();          // sa_identity, passport_identity, liveness, company, screening, bank_account
            $table->string('provider_key', 30);                    // provider key or "manual"
            $table->timestamps();
        });

        Schema::create('verifications', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('in_progress')->index(); // in_progress | submitted | verified | review | rejected | expired
            $table->string('document_type', 20)->nullable();      // sa_id | passport
            $table->text('id_number')->nullable();                 // encrypted
            $table->string('id_number_hash', 64)->nullable()->unique();
            $table->string('passport_country', 2)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('marital_status', 40)->nullable();
            $table->json('spouse')->nullable();                    // name, ID (encrypted), consent document reference
            $table->json('checks')->nullable();                    // per check: provider, reference, outcome, reasons, at
            $table->string('rejection_reason', 500)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('verification_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verification_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30);                            // id_front | id_back | passport | selfie | proof_of_address | spouse_consent
            $table->string('path');                                // private disk, encrypted contents
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->date('delete_after')->nullable();              // FICA retention
            $table->timestamps();
        });

        Schema::create('verification_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider_key', 30);
            $table->string('event_id', 120);                       // provider's id for idempotency
            $table->foreignId('verification_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 60)->nullable();
            $table->timestamp('created_at');
            $table->unique(['provider_key', 'event_id']);
        });
    }

    public function down(): void
    {
        foreach (['verification_events', 'verification_documents', 'verifications', 'verification_routes', 'verification_providers'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
