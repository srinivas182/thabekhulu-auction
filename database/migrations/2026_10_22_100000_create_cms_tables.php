<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 3c-1: content pages built from blocks, header/footer menus and
 * investment collections. Home page texts live in the `home` settings group.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->string('slug', 160)->unique();
            $table->string('status', 16)->default('draft');        // draft | published
            $table->json('blocks');                                 // ordered list of typed blocks
            $table->boolean('is_system')->default(false);          // seeded legal pages: cannot be deleted
            $table->boolean('needs_content')->default(false);      // placeholder text awaiting Thabekhulu or their attorney
            $table->string('seo_title', 120)->nullable();
            $table->string('seo_description', 300)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('menu', 16);                             // header | footer
            $table->string('label', 60);
            $table->string('url', 255);                             // internal path (/about) or https URL
            $table->boolean('new_tab')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['menu', 'sort_order']);
        });

        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 80)->unique();
            $table->string('description', 300)->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_attribute_id')->nullable()->constrained()->nullOnDelete();
            $table->string('attribute_value', 120)->nullable();
            $table->boolean('renovated_only')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collections');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('pages');
    }
};
