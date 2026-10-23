<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spouse details are encrypted (ciphertext, not JSON); MySQL's JSON column
 * rejects that, so the column becomes text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verifications', function (Blueprint $table) {
            $table->text('spouse')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('verifications', function (Blueprint $table) {
            $table->json('spouse')->nullable()->change();
        });
    }
};
