<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The winner pays the balance to Thabekhulu (EFT with proof, confirmed by
 * finance), payments can be confirmed by hand with a second approver, and
 * buyers upload their signed sale agreement for staff to accept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_payments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('settlement_case_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('reference', 24)->unique();       // TDG-B-1234-ABCDE
            $table->unsignedBigInteger('amount_due');        // cents
            $table->unsignedBigInteger('received')->default(0);
            $table->string('status', 12)->default('awaiting'); // awaiting | part_paid | received
            $table->string('proof_path')->nullable();
            $table->string('proof_mime', 100)->nullable();
            $table->timestamp('proof_uploaded_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });
        Schema::table('bank_lines', function (Blueprint $table) {
            $table->foreignId('settlement_payment_id')->nullable()->constrained()->nullOnDelete();
        });
        Schema::create('payment_confirmations', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10);                      // deposit | balance
            $table->unsignedBigInteger('target_id');         // deposits.id or settlement_payments.id
            $table->unsignedBigInteger('amount');            // cents
            $table->string('bank_reference', 120);
            $table->date('paid_on');
            $table->string('status', 10)->default('pending'); // pending | approved | rejected
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->index(['status', 'kind']);
        });
        Schema::create('settlement_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_case_id')->constrained()->cascadeOnDelete();
            $table->string('step', 30);                      // agreement_signed
            $table->string('path');
            $table->string('mime', 100);
            $table->string('status', 10)->default('pending'); // pending | accepted | rejected
            $table->string('reason', 500)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_uploads');
        Schema::dropIfExists('payment_confirmations');
        Schema::table('bank_lines', fn (Blueprint $t) => $t->dropConstrainedForeignId('settlement_payment_id'));
        Schema::dropIfExists('settlement_payments');
    }
};
