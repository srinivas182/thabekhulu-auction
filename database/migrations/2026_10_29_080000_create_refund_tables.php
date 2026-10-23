<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 5c: refunds in two-person batches (decision P5). A refund pays the
 * deposit's ledger balance back to the bidder's verified account (EFT) or
 * through the escrow provider that holds it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_batches', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();                 // TDG-RB-2026-0001
            $table->string('status', 16)->default('prepared');      // prepared | approved | paid | cancelled
            $table->unsignedBigInteger('total');                    // cents
            $table->unsignedInteger('count');
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('prepared_at');
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('proof_path')->nullable();               // bank confirmation, encrypted
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deposit_id')->constrained()->restrictOnDelete();
            $table->foreignId('refund_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount');                   // cents, fixed when the batch is prepared
            $table->string('channel', 16)->nullable();              // eft | tradesafe | paystack
            $table->string('reason', 60);                           // unsuccessful | runner_up_released | withdrawn | other
            $table->string('status', 16)->default('scheduled');     // scheduled | batched | paid | failed | cancelled
            $table->date('due_on');
            $table->string('provider_ref', 160)->nullable();
            $table->string('failure', 500)->nullable();
            $table->foreignId('scheduled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('refund_batches');
    }
};
