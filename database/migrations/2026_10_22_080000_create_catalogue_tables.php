<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 3a catalogue: categories with commercial defaults, admin-defined
 * key-fact fields, document types, and South African locations.
 * Money is stored as whole rand; percentages as basis points (2000 = 20%).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 80)->unique();
            $table->string('description', 500)->nullable();
            $table->string('icon', 40)->nullable();
            $table->unsignedBigInteger('default_deposit');          // rand
            $table->unsignedInteger('default_fee_bps');              // basis points
            $table->string('default_deposit_terms', 16);             // security | applied
            $table->boolean('requires_compliance_notice')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('seo_title', 120)->nullable();
            $table->string('seo_description', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('category_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('key', 60);
            $table->string('label', 80);
            $table->string('type', 16);                               // number | text | select | boolean | date
            $table->string('unit', 20)->nullable();
            $table->json('options')->nullable();                      // select choices
            $table->boolean('is_required')->default(false);
            $table->boolean('show_on_card')->default(false);
            $table->boolean('show_in_key_facts')->default(true);
            $table->boolean('is_filterable')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'key']);
        });

        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->boolean('is_mandatory')->default(false);
            $table->string('visibility', 16);                         // internal | verified | approved_nda
            $table->json('accepted_formats');
            $table->unsignedSmallInteger('max_size_mb')->default(20);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('slug', 60)->unique();
            $table->string('code', 4)->unique();
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);
            $table->string('slug', 80);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['province_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
        Schema::dropIfExists('provinces');
        Schema::dropIfExists('document_types');
        Schema::dropIfExists('category_attributes');
        Schema::dropIfExists('categories');
    }
};
