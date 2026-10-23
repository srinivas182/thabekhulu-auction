<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 4c data room: NDA acceptances per listing and a log of every
 * document a bidder downloads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nda_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('nda_version');
            $table->string('typed_name', 160);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('accepted_at');
            $table->unique(['listing_id', 'user_id', 'nda_version']);
        });

        Schema::create('document_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('bidder_number', 6)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at');
            $table->index(['listing_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_downloads');
        Schema::dropIfExists('nda_acceptances');
    }
};
