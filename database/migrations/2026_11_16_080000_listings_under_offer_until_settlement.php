<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Sold" now means settlement completed. Listings already marked sold whose
 * settlement isn't completed become "under offer"; the listing status is a
 * string column, so the new values need no schema change.
 */
return new class extends Migration
{
    public function up(): void
    {
        $completed = DB::table('settlement_cases')->where('status', 'completed')->pluck('listing_id');
        DB::table('listings')->where('status', 'sold')->whereNotIn('id', $completed)->update(['status' => 'under_offer']);
    }

    public function down(): void
    {
        DB::table('listings')->where('status', 'under_offer')->update(['status' => 'sold']);
        DB::table('listings')->where('status', 'not_sold')->update(['status' => 'sold']);
    }
};
