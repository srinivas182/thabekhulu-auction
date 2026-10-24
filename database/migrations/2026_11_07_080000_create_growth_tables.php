<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 8b growth (G1): saved searches with alerts, watchlist, first-touch
 * campaign attribution, and a double opt-in newsletter list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->json('filters');
            $table->timestamp('last_alerted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('saved_search_hits', function (Blueprint $table) {
            $table->foreignId('saved_search_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at');
            $table->primary(['saved_search_id', 'listing_id']);           // each listing alerted once per search
        });

        Schema::create('watchlist_items', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at');
            $table->primary(['user_id', 'listing_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('attribution')->nullable();                      // first-touch utm_* captured before sign-up
        });

        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('email', 190)->unique();
            $table->string('name', 120)->nullable();
            $table->string('status', 12)->default('pending');             // pending | confirmed | unsubscribed
            $table->timestamp('consent_at');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['newsletter_subscribers', 'watchlist_items', 'saved_search_hits', 'saved_searches'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('attribution'));
    }
};
