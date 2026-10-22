<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 3b listings. Seller terms (seller_net_price, seller_price,
 * renovation_cost, fee_bps) are internal and never sent to public pages.
 * Money in whole rand, fee in basis points, times in UTC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('suburb', 80)->nullable();
            $table->string('address', 255)->nullable();               // internal
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->string('title', 160);
            $table->string('slug', 180)->unique();
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();              // sanitised HTML
            $table->string('video_url', 255)->nullable();

            $table->string('deal_type', 16);                          // commission | renovated
            $table->unsignedBigInteger('seller_net_price')->nullable();
            $table->unsignedBigInteger('seller_price')->nullable();
            $table->unsignedBigInteger('renovation_cost')->default(0);
            $table->unsignedInteger('fee_bps');
            $table->unsignedBigInteger('minimum_price')->nullable();  // public
            $table->unsignedBigInteger('deposit_amount');
            $table->string('deposit_terms', 16);                      // security | applied
            $table->string('auction_type', 16)->default('open');      // open | sealed
            $table->text('bidding_terms')->nullable();                // overrides the template when set

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 24)->index();
            $table->text('review_note')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->string('seo_title', 120)->nullable();
            $table->string('seo_description', 300)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'starts_at']);
            $table->index(['category_id', 'status']);
        });

        Schema::create('listing_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_attribute_id')->constrained()->restrictOnDelete();
            $table->string('value', 500)->nullable();
            $table->timestamps();
            $table->unique(['listing_id', 'category_attribute_id']);
        });

        Schema::create('listing_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('from', 24)->nullable();
            $table->string('to', 24);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at');
        });

        Schema::create('listing_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();
            $table->string('path');                                   // private disk
            $table->string('original_name', 255);
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('listing_compliance_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('item', 40);
            $table->foreignId('signed_off_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('signed_off_at');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['listing_id', 'item']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_compliance_items');
        Schema::dropIfExists('listing_documents');
        Schema::dropIfExists('listing_status_changes');
        Schema::dropIfExists('listing_attribute_values');
        Schema::dropIfExists('listings');
    }
};
