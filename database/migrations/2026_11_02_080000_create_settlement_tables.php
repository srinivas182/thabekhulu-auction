<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 7a: settlement cases (winner sale or runner-up offer), their steps,
 * defaults with capped forfeiture (two people), and bidding suspensions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_cases', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('listing_id')->constrained()->restrictOnDelete();
            $table->foreignId('bidder_approval_id')->constrained()->restrictOnDelete();
            $table->string('kind', 12);                                   // sale | runner_up
            $table->unsignedBigInteger('price');                          // rand: winning bid, or the runner-up's own highest bid
            $table->string('status', 12);                                 // offered | open | completed | defaulted | declined | expired
            $table->date('agreement_due')->nullable();
            $table->date('balance_due')->nullable();
            $table->timestamp('offer_expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['listing_id', 'status']);
        });

        Schema::create('settlement_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_case_id')->constrained()->cascadeOnDelete();
            $table->string('step', 24);                                   // agreement_signed | balance_paid | attorney_appointed
            $table->date('done_on');
            $table->string('document_path')->nullable();                  // encrypted, private
            $table->string('document_mime', 100)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['settlement_case_id', 'step']);
        });

        Schema::create('settlement_defaults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_case_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('reason', 1000);
            $table->unsignedBigInteger('auction_cost');                   // cents, entered by staff
            $table->unsignedBigInteger('proposed_forfeit');               // cents: min(10% of price, auction cost, deposit held)
            $table->boolean('security_signoff')->default(false);          // required for security deposits
            $table->string('status', 10)->default('proposed');            // proposed | confirmed | withdrawn
            $table->foreignId('declared_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('declared_at');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
        });

        Schema::create('suspensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('until');
            $table->string('reason', 500);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('lifted_at')->nullable();
            $table->foreignId('lifted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'until']);
        });
    }

    public function down(): void
    {
        foreach (['suspensions', 'settlement_defaults', 'settlement_steps', 'settlement_cases'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
