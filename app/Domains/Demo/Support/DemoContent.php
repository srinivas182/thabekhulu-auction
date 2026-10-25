<?php

namespace App\Domains\Demo\Support;

use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Events\Models\Event;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sample content for demonstrations and UAT. Everything is marked "(sample)"
 * in its title and slugged "sample-…", so `demo:remove` can take it all out
 * before launch. Never run automatically.
 */
final class DemoContent
{
    public const PREFIX = 'sample-';

    /** @return list<array<string, mixed>> */
    public static function listings(): array
    {
        return [
            ['slug' => 'sample-family-home-sandton', 'cat' => 'homes', 'city' => 'sandton', 'title' => 'Four-bedroom family home with pool', 'suburb' => 'Bryanston', 'state' => 'live', 'days' => 6,
                'deal' => 'commission', 'net' => 2900000, 'min' => 3200000, 'deposit' => 50000, 'terms' => 'applied', 'type' => 'open',
                'summary' => 'North-facing family home on a 1 200 m² stand with a pool, staff quarters and double garage.',
                'text' => 'Four bedrooms, three bathrooms and an open-plan kitchen leading onto a covered patio and pool. Walking distance to schools and Bryanston shopping.'],
            ['slug' => 'sample-cape-dutch-homestead-stellenbosch', 'cat' => 'homes', 'city' => 'stellenbosch', 'title' => 'Renovated Cape Dutch homestead', 'suburb' => 'Jonkershoek', 'state' => 'soon', 'days' => 15,
                'deal' => 'renovated', 'seller_price' => 5100000, 'min' => 6400000, 'deposit' => 100000, 'terms' => 'applied', 'type' => 'open',
                'summary' => 'Restored 1890s homestead on 2 ha, renovated by Thabekhulu with a new kitchen, roof and borehole.',
                'text' => 'Five bedrooms under thatch, original yellowwood floors, a guest cottage and mountain views across the valley.'],
            ['slug' => 'sample-development-stand-midrand', 'cat' => 'land', 'city' => 'midrand', 'title' => 'Serviced development stand, 2 400 m²', 'suburb' => 'Waterfall', 'state' => 'live', 'days' => 9,
                'deal' => 'commission', 'net' => 1700000, 'min' => 1900000, 'deposit' => 50000, 'terms' => 'applied', 'type' => 'open',
                'summary' => 'Serviced residential stand zoned for up to 12 units, with approved site plan and services on the boundary.',
                'text' => 'Flat, cleared and fenced. Close to the N1, Mall of Africa and the Gautrain station.'],
            ['slug' => 'sample-farm-water-rights-tzaneen', 'cat' => 'land', 'city' => 'tzaneen', 'title' => '120 ha farm with water rights', 'suburb' => 'Letsitele', 'state' => 'soon', 'days' => 20,
                'deal' => 'commission', 'net' => 7800000, 'min' => 8600000, 'deposit' => 250000, 'terms' => 'applied', 'type' => 'open',
                'summary' => 'Citrus and macadamia farm with registered water rights, two boreholes, packing shed and farmhouse.',
                'text' => '80 ha under irrigation, 40 ha natural bush. Sold as a going concern; equipment list available in the data room.'],
            ['slug' => 'sample-coal-prospecting-right-emalahleni', 'cat' => 'mining', 'city' => 'emalahleni', 'title' => 'Coal prospecting right, 640 ha', 'suburb' => 'Witbank coalfield', 'state' => 'live', 'days' => 12,
                'deal' => 'commission', 'net' => 20000000, 'min' => 23000000, 'deposit' => 1200000, 'terms' => 'security', 'type' => 'sealed',
                'summary' => 'Prospecting right over 640 ha with completed drilling and a competent person’s report. Sealed tender.',
                'text' => 'Transfer is subject to section 11 consent under the MPRDA. The winning offer becomes the preferred bidder, subject to approvals.'],
            ['slug' => 'sample-manganese-prospecting-right-kathu', 'cat' => 'mining', 'city' => 'kathu', 'title' => 'Manganese prospecting right', 'suburb' => 'Kalahari manganese field', 'state' => 'soon', 'days' => 25,
                'deal' => 'commission', 'net' => 15000000, 'min' => 17000000, 'deposit' => 1000000, 'terms' => 'security', 'type' => 'open',
                'summary' => 'Early-stage manganese prospecting right with geophysical survey results and access agreements in place.',
                'text' => 'Close to rail and existing mines. Proof of funds is required to be approved as a bidder.'],
            ['slug' => 'sample-shopping-centre-rustenburg', 'cat' => 'malls', 'city' => 'rustenburg', 'title' => 'Neighbourhood shopping centre, 18 shops', 'suburb' => 'Waterfall East', 'state' => 'live', 'days' => 10,
                'deal' => 'commission', 'net' => 34000000, 'min' => 38000000, 'deposit' => 500000, 'terms' => 'applied', 'type' => 'open',
                'summary' => '6 800 m² convenience centre anchored by a national supermarket, 94% let, with parking for 220 cars.',
                'text' => 'Long leases with national tenants. Tenancy schedule and audited income statements in the data room.'],
        ];
    }

    /** Creates the sample content; returns how many listings were added (existing samples are left as they are). */
    public static function seed(): int
    {
        $now = CarbonImmutable::now();
        $admin = User::where('type', 'staff')->orderBy('id')->first();
        $added = 0;

        foreach (self::listings() as $s) {
            if (Listing::where('slug', $s['slug'])->exists()) {
                continue;
            }
            $category = Category::where('slug', $s['cat'])->firstOrFail();
            $city = City::where('slug', $s['city'])->firstOrFail();
            $live = $s['state'] === 'live';
            $listing = Listing::create([
                'category_id' => $category->id, 'city_id' => $city->id, 'suburb' => $s['suburb'], 'title' => $s['title'].' (sample)', 'slug' => $s['slug'],
                'summary' => 'Sample listing for demonstration. '.$s['summary'], 'description' => '<p>'.e($s['text']).'</p><p><em>This is sample content and not a real property.</em></p>',
                'deal_type' => $s['deal'], 'seller_net_price' => $s['net'] ?? null, 'seller_price' => $s['seller_price'] ?? null, 'fee_bps' => $category->default_fee_bps,
                'minimum_price' => $s['min'], 'deposit_amount' => $s['deposit'], 'deposit_terms' => $s['terms'], 'auction_type' => $s['type'],
                'status' => $live ? ListingStatus::Live : ListingStatus::Scheduled,
                'starts_at' => $live ? $now->subDay() : $now->addDays(max(1, $s['days'] - 10)), 'ends_at' => $now->addDays($s['days'])->setTime(12, 0),
                'created_by' => $admin?->id, 'updated_by' => $admin?->id, 'approved_by' => $admin?->id, 'approved_at' => $now,
            ]);
            $listing->addMedia(SampleImage::make($category->name, $s['title']))->usingFileName($s['slug'].'.jpg')->toMediaCollection('photos');
            $added++;
        }

        if (! Event::where('slug', 'sample-investor-briefing')->exists()) {
            Event::create(['title' => 'Investor briefing: mining tenders (sample)', 'slug' => 'sample-investor-briefing', 'kind' => 'in_person', 'venue' => 'Sandton Convention Centre, Johannesburg',
                'description' => '<p>Meet the team behind this season’s coal and manganese tenders: how sealed tenders work, proof of funds, and section 11 consent.</p><p><em>Sample event.</em></p>',
                'starts_at' => $now->addDays(14)->setTime(8, 0), 'ends_at' => $now->addDays(14)->setTime(11, 0), 'price' => 0, 'capacity' => 120, 'status' => 'published']);
        }

        foreach ([
            ['sample-buying-property-at-auction', 'How to buy property at auction in South Africa (sample)', 'Buying at auction', 'Register, verify your identity, pay the deposit and bid: what to expect at each step.',
                '<p>Online auctions follow the Consumer Protection Act. Before you bid you verify your identity, are approved for the listing and pay its deposit. Bids are binding, and a bid in the final five minutes extends the auction.</p>'],
            ['sample-mining-right-sales', 'What a mining right sale involves (sample)', 'Mining', 'Why a winning bid makes you the preferred bidder, and how section 11 consent works.',
                '<p>A mining or prospecting right can only be transferred with the Minister’s consent under section 11 of the MPRDA. The winning offer therefore becomes the preferred bidder, subject to approvals.</p>'],
        ] as [$slug, $title, $topic, $excerpt, $body]) {
            if (! DB::table('posts')->where('slug', $slug)->exists()) {
                DB::table('posts')->insert(['title' => $title, 'slug' => $slug, 'topic' => $topic, 'excerpt' => $excerpt, 'body' => $body.'<p><em>Sample article.</em></p>',
                    'author_id' => $admin?->id, 'status' => 'published', 'published_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        return $added;
    }

    /** @return array{listings: int, events: int, posts: int, kept: list<string>} */
    public static function remove(): array
    {
        $done = ['listings' => 0, 'events' => 0, 'posts' => 0, 'kept' => []];
        foreach (Listing::where('slug', 'like', self::PREFIX.'%')->get() as $l) {
            try {
                $l->delete();
                $done['listings']++;
            } catch (\Throwable) {
                $done['kept'][] = $l->slug; // has real activity (bids, deposits): left for a person to decide
            }
        }
        $done['events'] = Event::where('slug', 'like', self::PREFIX.'%')->whereDoesntHave('tickets')->delete();
        $done['posts'] = DB::table('posts')->where('slug', 'like', self::PREFIX.'%')->delete();

        return $done;
    }

    /** The image URL for a Pexels photo page link, or a direct images.pexels.com link; null for anything else. */
    public static function pexelsImage(string $url): ?string
    {
        $url = trim($url);
        if (preg_match('#^https://images\.pexels\.com/photos/(\d+)/#', $url, $m) || preg_match('#^https://(?:www\.)?pexels\.com/(?:[a-z-]+/)?photo/(?:[a-z0-9-]*-)?(\d+)/?#', $url, $m)) {
            return "https://images.pexels.com/photos/{$m[1]}/pexels-photo-{$m[1]}.jpeg?auto=compress&cs=tinysrgb&w=1600";
        }

        return null;
    }

    public static function slugify(string $s): string
    {
        return Str::slug($s);
    }
}
