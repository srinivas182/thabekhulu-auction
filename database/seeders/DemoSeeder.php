<?php

namespace Database\Seeders;

use App\Domains\Demo\Support\DemoContent;
use Illuminate\Database\Seeder;

/** Sample listings, an event and articles for demos (php artisan db:seed --class=DemoSeeder --force). Never part of DatabaseSeeder. */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $added = DemoContent::seed();
        $this->command?->info("{$added} sample listings added, plus a sample event and two sample articles.");
        $this->command?->line('Swap in real photos: php artisan demo:photos --template, edit storage/app/demo-photos.txt, then php artisan demo:photos');
        $this->command?->line('Remove all sample content before launch: php artisan demo:remove');
    }
}
