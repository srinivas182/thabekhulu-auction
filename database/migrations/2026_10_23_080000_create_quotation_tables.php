<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 3Q quotations. Money in whole cents, quantities in hundredths,
 * percentages in basis points (1500 = 15%).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('company', 160)->nullable();
            $table->string('email');
            $table->string('phone', 30)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('vat_number', 30)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // linked buyer account
            $table->timestamps();
            $table->index('email');
        });

        Schema::create('catalogue_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('description', 500)->nullable();
            $table->string('unit', 20);
            $table->unsignedBigInteger('unit_price');          // cents
            $table->boolean('vat_applicable')->default(true);
            $table->string('frequency', 16)->default('once');  // once | monthly | annual
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('quote_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->json('content');                            // pre-filled sections and lines
            $table->boolean('needs_review')->default(true);
            $table->timestamps();
        });

        Schema::create('quote_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number');
        });

        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('number', 30);                       // TDG-Q-2026-0001
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('status', 24)->default('draft')->index();
            $table->string('title', 160);
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('issue_date');
            $table->date('valid_until');
            $table->unsignedInteger('vat_rate_bps');
            $table->text('payment_method')->nullable();
            $table->longText('scope')->nullable();
            $table->longText('assumptions')->nullable();
            $table->longText('exclusions')->nullable();
            $table->longText('terms')->nullable();
            $table->text('internal_notes')->nullable();
            // Totals (cents), recalculated on every save.
            $table->unsignedBigInteger('once_subtotal')->default(0);
            $table->unsignedBigInteger('once_discount')->default(0);
            $table->unsignedBigInteger('once_vat')->default(0);
            $table->unsignedBigInteger('once_total')->default(0);
            $table->unsignedBigInteger('monthly_total')->default(0);
            $table->unsignedBigInteger('annual_total')->default(0);
            // Acceptance record (3Q-b).
            $table->string('fingerprint', 64)->nullable();
            $table->string('accepted_name', 160)->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->string('accepted_ip', 45)->nullable();
            $table->string('accepted_agent', 255)->nullable();
            $table->string('decline_reason', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['number', 'version']);
        });

        Schema::create('quote_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('kind', 16)->default('item');        // item | heading
            $table->string('frequency', 16)->default('once');   // once | monthly | annual
            $table->string('description', 500);
            $table->unsignedInteger('quantity')->default(100);  // hundredths: 100 = 1
            $table->string('unit', 20)->nullable();
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->string('discount_type', 8)->nullable();     // percent | amount
            $table->unsignedBigInteger('discount_value')->default(0); // bps or cents
            $table->boolean('vat_applicable')->default(true);
            $table->unsignedBigInteger('net')->default(0);
            $table->unsignedBigInteger('vat')->default(0);
        });

        Schema::create('quote_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('name', 160);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('description', 500)->nullable();
        });

        Schema::create('quote_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('label', 160);
            $table->unsignedInteger('percent_bps');
            $table->string('due', 160)->nullable();
        });

        Schema::create('quote_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['quote_events', 'quote_payments', 'quote_milestones', 'quote_lines', 'quotes', 'quote_sequences', 'quote_templates', 'catalogue_items', 'clients'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
