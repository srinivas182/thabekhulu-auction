<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('properties');
            $table->string('user_agent', 255)->nullable()->after('ip_address');
            $table->index('created_at');
            $table->index(['causer_type', 'causer_id', 'created_at'], 'activity_log_causer_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex('activity_log_causer_created_index');
            $table->dropIndex(['created_at']);
            $table->dropColumn(['ip_address', 'user_agent']);
        });
    }
};
