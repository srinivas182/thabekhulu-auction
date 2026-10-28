<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Approve, then pay: EFT deposits can carry the buyer's proof of payment
 * (finance still confirms against the bank), and approved buyers with a
 * received deposit can set an advance maximum before bidding opens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->string('proof_path')->nullable();
            $table->string('proof_mime', 100)->nullable();
            $table->timestamp('proof_uploaded_at')->nullable();
        });
        Schema::create('advance_bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bidder_approval_id')->constrained()->cascadeOnDelete();
            $table->text('amount');                       // encrypted, like maximum bids
            $table->timestamp('set_at', 6);               // earlier maximum wins ties
            $table->timestamp('applied_at')->nullable();  // placed when bidding opened
            $table->string('result', 20)->nullable();     // placed | outbid
            $table->unique(['listing_id', 'bidder_approval_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advance_bids');
        Schema::table('deposits', fn (Blueprint $table) => $table->dropColumn(['proof_path', 'proof_mime', 'proof_uploaded_at']));
    }
};
