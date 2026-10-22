<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 3c-2: Investor insights posts, gallery albums, "Sell with us"
 * leads and contact enquiries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->string('slug', 160)->unique();
            $table->string('topic', 40);
            $table->string('excerpt', 300)->nullable();
            $table->longText('body')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('draft');       // draft | published
            $table->timestamp('published_at')->nullable()->index(); // future date = scheduled
            $table->string('seo_title', 120)->nullable();
            $table->string('seo_description', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('galleries', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('slug', 120)->unique();
            $table->string('description', 300)->nullable();
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name', 120);
            $table->string('email');
            $table->string('mobile', 20);
            $table->string('property_type', 40);
            $table->string('location', 120);
            $table->unsignedBigInteger('asking_price')->nullable();
            $table->text('description');
            $table->string('status', 24)->default('new')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('consent_at');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('lead_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note');
            $table->timestamp('created_at');
        });

        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email');
            $table->string('phone', 20)->nullable();
            $table->string('subject', 160);
            $table->text('message');
            $table->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('new')->index();  // new | answered | closed
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('enquiry_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['enquiry_replies', 'enquiries', 'lead_notes', 'leads', 'galleries', 'posts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
