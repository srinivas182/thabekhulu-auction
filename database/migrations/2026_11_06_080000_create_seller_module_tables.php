<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 8a seller module (behind the "sellers" flag, off in Phase 1):
 * seller-owned submissions (leads) and their documents, the listing's
 * seller account, and seller packages with expiry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();      // seller account, if submitted by one
            $table->foreignId('listing_id')->nullable()->after('user_id')->constrained()->nullOnDelete(); // draft created from it
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->foreignId('seller_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('lead_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30);                         // title_deed | mining_right | authority | other
            $table->string('path');                             // encrypted, private
            $table->string('mime', 100);
            $table->timestamps();
        });

        Schema::create('seller_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->unsignedBigInteger('price');                // cents (paid booking is Phase 2)
            $table->unsignedSmallInteger('duration_days');
            $table->unsignedSmallInteger('max_listings');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('seller_package_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_package_id')->constrained()->restrictOnDelete();
            $table->date('starts_on');
            $table->date('expires_on');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'expires_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_package_assignments');
        Schema::dropIfExists('seller_packages');
        Schema::dropIfExists('lead_documents');
        Schema::table('listings', fn (Blueprint $t) => $t->dropConstrainedForeignId('seller_user_id'));
        Schema::table('leads', function (Blueprint $t) {
            $t->dropConstrainedForeignId('listing_id');
            $t->dropConstrainedForeignId('user_id');
        });
    }
};
