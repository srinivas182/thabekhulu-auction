<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Passports: expiry date (FICA needs a valid document); date of birth already has a column. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verifications', function (Blueprint $table) {
            $table->date('passport_expires_on')->nullable()->after('passport_country');
        });
    }

    public function down(): void
    {
        Schema::table('verifications', fn (Blueprint $table) => $table->dropColumn('passport_expires_on'));
    }
};
