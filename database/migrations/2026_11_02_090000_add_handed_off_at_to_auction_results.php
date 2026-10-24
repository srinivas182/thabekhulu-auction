<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** When the close finished handing deposits on; the close command completes any unfinished hand-off. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auction_results', fn (Blueprint $table) => $table->timestamp('handed_off_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('auction_results', fn (Blueprint $table) => $table->dropColumn('handed_off_at'));
    }
};
