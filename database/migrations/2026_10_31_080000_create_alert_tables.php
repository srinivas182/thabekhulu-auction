<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sprint 6b: WhatsApp opt-in (POPIA consent) and once-only auction alerts. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('whatsapp_number', 20)->nullable();
            $table->timestamp('whatsapp_opt_in_at')->nullable();
        });

        Schema::create('auction_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);                       // closing_60 | closing_10 | won | lost
            $table->timestamp('created_at');
            $table->unique(['listing_id', 'user_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_alerts');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['whatsapp_number', 'whatsapp_opt_in_at']));
    }
};
