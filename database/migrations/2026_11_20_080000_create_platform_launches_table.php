<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Records the one-time launch reset; once a row exists, the reset refuses to run again. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_launches', function (Blueprint $table) {
            $table->id();
            $table->timestamp('reset_at');
            $table->string('reset_by', 160);
            $table->string('backup_path')->nullable();
            $table->json('summary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_launches');
    }
};
