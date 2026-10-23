<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 4b-1: KYC decisions, sanctions/PEP screening records and refund
 * bank accounts. Account numbers are encrypted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verifications', function (Blueprint $table) {
            $table->string('info_requested', 1000)->nullable()->after('rejection_reason');
        });

        Schema::create('screenings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('result', 16);                           // clear | possible_match | confirmed_match
            $table->string('lists', 255);                           // what was checked, e.g. "FIC TFS, UN, PEP"
            $table->string('provider', 30)->default('manual');
            $table->string('reference', 120)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('screened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('screened_at');
            $table->timestamps();
            $table->index(['user_id', 'screened_at']);
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('bank', 60);
            $table->string('branch_code', 10);
            $table->text('account_number');                         // encrypted
            $table->string('account_last4', 4);
            $table->string('account_type', 20);                     // cheque | savings | transmission
            $table->string('holder_name', 160);
            $table->string('status', 16)->default('pending');       // pending | verified | rejected
            $table->string('proof_path')->nullable();               // encrypted file on private disk
            $table->string('rejection_reason', 500)->nullable();
            $table->boolean('locked')->default(false);              // set while a refund or deposit is pending (Sprint 5)
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('screenings');
        Schema::table('verifications', fn (Blueprint $table) => $table->dropColumn('info_requested'));
    }
};
