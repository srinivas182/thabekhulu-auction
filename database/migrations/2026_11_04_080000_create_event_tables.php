<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 7c: events (free or paid, online or in person), ticket orders
 * (seats held 15 minutes while a card payment is pending) and single-use
 * QR tickets whose secret is stored only as a hash (S9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('title', 160);
            $table->string('slug', 180)->unique();
            $table->text('description')->nullable();               // cleaned HTML
            $table->string('kind', 10);                            // in_person | online
            $table->string('venue', 255)->nullable();              // in person
            $table->string('online_link', 500)->nullable();        // online: shown to ticket holders only
            $table->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedBigInteger('price')->default(0);       // cents per ticket; 0 = free
            $table->unsignedInteger('capacity');
            $table->string('status', 10)->default('draft');        // draft | published | cancelled
            $table->timestamps();
        });

        Schema::create('ticket_orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 24)->unique();             // TDG-E-XXXXXXX (payment reference)
            $table->string('name', 160);
            $table->string('email', 190);
            $table->string('mobile', 20)->nullable();
            $table->unsignedTinyInteger('quantity');
            $table->unsignedBigInteger('amount');                  // cents
            $table->string('status', 10);                          // pending | paid | free | expired | cancelled
            $table->timestamp('hold_until')->nullable();           // pending card payments hold seats
            $table->string('provider_ref', 120)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['event_id', 'status']);
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('ticket_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('code', 9);                             // human code for the door, e.g. K7Q3-9XWM
            $table->string('token_hash', 64)->unique();            // sha256 of the QR secret
            $table->timestamp('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'code']);
        });
    }

    public function down(): void
    {
        foreach (['tickets', 'ticket_orders', 'events'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
