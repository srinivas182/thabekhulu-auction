<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 5a: deposits, the append-only ledger, bank statement imports and
 * receipts. Money in cents (decision P2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('listing_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('bidder_approval_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');                  // cents due
            $table->string('reference', 24)->unique();             // TDG-D-4821-7Q3KX
            $table->string('method', 16)->default('eft');
            $table->string('status', 20)->default('awaiting');     // awaiting | part_paid | received | refund_scheduled | refunded | applied
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->unique(['bidder_approval_id']);
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deposit_id')->constrained()->restrictOnDelete();
            $table->string('type', 16);                            // received | refunded | applied | forfeited | adjustment
            $table->bigInteger('amount');                          // cents; money in positive, money out negative
            $table->string('source', 16);                          // bank_import | provider | manual
            $table->string('source_ref', 120)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at');
        });

        Schema::create('bank_imports', function (Blueprint $table) {
            $table->id();
            $table->string('file_name', 255);
            $table->string('file_hash', 64)->unique();
            $table->string('format', 8);
            $table->unsignedInteger('lines_total')->default(0);
            $table->unsignedInteger('matched')->default(0);
            $table->unsignedInteger('queued')->default(0);
            $table->unsignedInteger('duplicates')->default(0);
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bank_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_import_id')->constrained()->cascadeOnDelete();
            $table->string('line_hash', 64)->unique();             // same line in a later statement is ignored
            $table->date('posted_on');
            $table->bigInteger('amount');                          // cents, credits only
            $table->string('description', 255);
            $table->string('reference_found', 24)->nullable();
            $table->foreignId('deposit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16);                          // matched | unmatched | short | over | resolved | ignored
            $table->string('resolution', 500)->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('receipt_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number');
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();                // TDG-R-2026-0001
            $table->foreignId('deposit_id')->constrained()->restrictOnDelete();
            $table->string('kind', 10);                            // deposit | refund
            $table->unsignedBigInteger('amount');
            $table->timestamp('issued_at');
        });
    }

    public function down(): void
    {
        foreach (['receipts', 'receipt_sequences', 'bank_lines', 'bank_imports', 'ledger_entries', 'deposits'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
