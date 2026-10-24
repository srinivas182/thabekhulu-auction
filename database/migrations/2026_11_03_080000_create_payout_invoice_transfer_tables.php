<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 7b: seller payouts (two people, S6), platform-fee tax invoices with
 * gap-free numbers (S7), and transfer stages (S8). Money in cents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_case_id')->unique()->constrained()->restrictOnDelete();
            $table->string('deal_type', 12);
            $table->unsignedBigInteger('price');                   // cents
            $table->unsignedBigInteger('fee');                     // cents, 0 for renovated sales
            $table->unsignedBigInteger('vat');                     // cents, on the fee
            $table->unsignedBigInteger('amount');                  // cents paid to the seller
            $table->string('payee', 160);
            $table->string('payee_reference', 160)->nullable();    // bank and account as given to the bank
            $table->string('status', 10)->default('prepared');     // prepared | approved | paid
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('proof_path')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();                // TDG-INV-2026-0001
            $table->foreignId('settlement_case_id')->unique()->constrained()->restrictOnDelete();
            $table->string('recipient', 160);
            $table->string('recipient_address', 500)->nullable();
            $table->string('recipient_vat_number', 20)->nullable();
            $table->string('description', 255);
            $table->unsignedBigInteger('fee');                     // cents, excluding VAT
            $table->unsignedInteger('vat_rate_bps');
            $table->unsignedBigInteger('vat');                     // cents
            $table->unsignedBigInteger('total');                   // cents
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('issued_at');
        });

        Schema::create('transfer_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_case_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 24);                           // lodged | registered | s11_applied | s11_granted
            $table->date('done_on');
            $table->string('note', 500)->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['settlement_case_id', 'stage']);
        });
    }

    public function down(): void
    {
        foreach (['transfer_stages', 'invoices', 'invoice_sequences', 'payouts'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
