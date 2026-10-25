<?php

namespace App\Domains\Demo\Console;

use App\Domains\Demo\Support\DemoContent;
use App\Domains\Listings\Models\Listing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Replaces sample listing photos with real ones from Pexels (free licence).
 * storage/app/demo-photos.txt: one line per photo, "listing-slug  pexels-link".
 */
class DemoPhotos extends Command
{
    protected $signature = 'demo:photos {--template : Write the file with every sample listing, ready to fill in}';

    protected $description = 'Use Pexels photos for the sample listings';

    public function handle(): int
    {
        $file = storage_path('app/demo-photos.txt');

        if ($this->option('template')) {
            $lines = ['# One line per photo: the sample listing, a space, then a Pexels link (https://www.pexels.com/photo/...-12345/).', '# The first photo for a listing becomes its main photo. Lines starting with # are ignored.'];
            foreach (DemoContent::listings() as $s) {
                $lines[] = $s['slug'].'  ';
            }
            file_put_contents($file, implode("\n", $lines)."\n");
            $this->info("Written {$file}. Add a Pexels link after each listing, then run: php artisan demo:photos");

            return self::SUCCESS;
        }
        if (! is_file($file)) {
            $this->error('No photo list yet. Run: php artisan demo:photos --template');

            return self::FAILURE;
        }

        $bySlug = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $parts = preg_split('/\s+/', trim($line)) ?: [];
            if ($line === '' || str_starts_with(trim($line), '#') || count($parts) < 2) {
                continue;
            }
            [$slug, $url] = $parts;
            $image = DemoContent::pexelsImage((string) $url);
            if (! str_starts_with((string) $slug, DemoContent::PREFIX) || $image === null) {
                $this->warn("Skipped: {$line} (only sample listings and pexels.com links are accepted)");

                continue;
            }
            $bySlug[$slug][] = $image;
        }

        foreach ($bySlug as $slug => $images) {
            $listing = Listing::where('slug', $slug)->first();
            if ($listing === null) {
                $this->warn("No sample listing called {$slug}");

                continue;
            }
            $files = [];
            foreach ($images as $url) {
                $r = Http::timeout(30)->get($url);
                if ($r->successful() && str_starts_with((string) $r->header('Content-Type'), 'image/')) {
                    $path = tempnam(sys_get_temp_dir(), 'pexels').'.jpg';
                    file_put_contents($path, $r->body());
                    $files[] = $path;
                } else {
                    $this->warn("Couldn't download {$url} (HTTP {$r->status()})");
                }
            }
            if ($files === []) {
                continue; // keep the existing photo rather than leave the listing empty
            }
            $listing->clearMediaCollection('photos');
            foreach ($files as $i => $path) {
                $listing->addMedia($path)->usingFileName("{$slug}-{$i}.jpg")->toMediaCollection('photos');
            }
            $this->info("{$slug}: ".count($files).' photo(s)');
        }

        return self::SUCCESS;
    }
}
