<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 6c: sealed tender offers (encrypted, raise-only, B8), the two-person
 * tender opening, and one result per closed auction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tender_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bidder_approval_id')->constrained()->restrictOnDelete();
            $table->text('amount');                                   // encrypted; read only after a two-person opening
            $table->timestamp('submitted_at', 6);                     // the time of the current amount (ties go to the earlier)
            $table->unsignedInteger('revisions')->default(0);
            $table->unique(['listing_id', 'bidder_approval_id']);
        });

        Schema::create('tender_openings', function (Blueprint $table) {
            $table->foreignId('listing_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('requested_at');
            $table->foreignId('opened_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('opened_at')->nullable();
        });

        Schema::create('auction_results', function (Blueprint $table) {
            $table->foreignId('listing_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('kind', 8);                                // open | sealed
            $table->string('outcome', 10);                            // sold | no_bids
            $table->foreignId('winner_approval_id')->nullable()->constrained('bidder_approvals')->nullOnDelete();
            $table->unsignedBigInteger('winning_amount')->nullable();
            $table->foreignId('runner_up_approval_id')->nullable()->constrained('bidder_approvals')->nullOnDelete();
            $table->unsignedBigInteger('runner_up_amount')->nullable();
            $table->unsignedInteger('bidders')->default(0);
            $table->timestamp('closed_at');
        });
    }

    public function down(): void
    {
        foreach (['auction_results', 'tender_openings', 'tender_offers'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
