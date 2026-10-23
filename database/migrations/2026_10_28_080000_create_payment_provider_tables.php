<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sprint 5b: payment providers (same pattern as verification providers) and provider events. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_providers', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30)->unique();
            $table->boolean('enabled')->default(false);
            $table->string('mode', 10)->default('sandbox');
            $table->text('credentials')->nullable();               // encrypted JSON
            $table->timestamp('last_success_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider_key', 30);
            $table->string('event_id', 160);
            $table->foreignId('deposit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 60)->nullable();
            $table->timestamp('created_at');
            $table->unique(['provider_key', 'event_id']);
        });

        Schema::table('deposits', function (Blueprint $table) {
            $table->string('provider_ref', 120)->nullable()->after('method')->index();
        });
    }

    public function down(): void
    {
        Schema::table('deposits', fn (Blueprint $table) => $table->dropColumn('provider_ref'));
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payment_providers');
    }
};
