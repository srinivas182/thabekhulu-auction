<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Evidence attached to a compliance sign-off (mandate, title deed, mining right, escrow agreement...). Kept even if a sign-off is withdrawn. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_compliance_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('item', 40);
            $table->string('path');                 // encrypted at rest (SecureFiles)
            $table->string('mime', 100);
            $table->string('name');
            $table->unsignedInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->index(['listing_id', 'item']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_compliance_attachments');
    }
};
