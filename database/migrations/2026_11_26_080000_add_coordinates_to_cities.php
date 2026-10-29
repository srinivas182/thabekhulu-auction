<?php

use App\Domains\Catalogue\Support\CityCoordinates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** City centres, so listings without their own coordinates still appear on the map. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
        });
        foreach (CityCoordinates::CITIES as $name => [$lat, $lng]) {
            DB::table('cities')->where('name', $name)->whereNull('latitude')->update(['latitude' => $lat, 'longitude' => $lng]);
        }
    }

    public function down(): void
    {
        Schema::table('cities', fn (Blueprint $table) => $table->dropColumn(['latitude', 'longitude']));
    }
};
