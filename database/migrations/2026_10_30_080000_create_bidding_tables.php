<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 6a bidding. One auction_states row per listing is the lock every
 * bid takes (B4). Bids are append-only; maximums are encrypted (B3).
 * Amounts in whole rand, like listing prices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auction_states', function (Blueprint $table) {
            $table->foreignId('listing_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('current_amount')->nullable();
            $table->foreignId('leader_approval_id')->nullable()->constrained('bidder_approvals')->nullOnDelete();
            $table->timestamp('ends_at');                                  // may move with soft close
            $table->unsignedInteger('extensions')->default(0);
            $table->unsignedInteger('bid_count')->default(0);
            $table->timestamp('paused_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bidder_approval_id')->constrained()->restrictOnDelete();
            $table->string('bidder_number', 6);
            $table->unsignedBigInteger('amount');
            $table->string('kind', 8);                                     // manual | proxy
            $table->timestamp('placed_at', 6);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 500)->nullable();
            $table->index(['listing_id', 'id']);
        });

        Schema::create('max_bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bidder_approval_id')->constrained()->cascadeOnDelete();
            $table->text('amount');                                        // encrypted
            $table->timestamp('set_at', 6);                                // earlier maximum wins a tie
            $table->timestamp('voided_at')->nullable();
            $table->unique(['listing_id', 'bidder_approval_id']);
        });

        Schema::create('bid_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash', 255);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['bid_otps', 'max_bids', 'bids', 'auction_states'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
