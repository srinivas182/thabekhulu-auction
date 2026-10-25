<?php

namespace Database\Seeders;

use App\Domains\Bidding\Models\Bid;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Cms\Models\Post;
use App\Domains\Events\Models\Event;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Verification\Models\BidderApproval;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo content for prospects (not run by DatabaseSeeder):
 *   php artisan db:seed --class=DemoSeeder --force
 * Everything is marked "Sample" and has a slug starting "sample-", so
 * `php artisan demo:remove` takes it all out before launch.
 *
 * Photos: storage/demo-photos/<key>.jpg when present (scripts/demo-photos.sh
 * downloads free Unsplash-licensed photos); otherwise a branded placeholder.
 */
class DemoSeeder extends Seeder
{
    public const NOTE = 'Sample listing for demonstration only: this is not a real property for sale, and the photo is illustrative.';

    public function run(): void
    {
        foreach ($this->listings() as $l) {
            if (Listing::where('slug', $l['slug'])->exists()) {
                continue; // safe to run again
            }
            $listing = Listing::create([
                'category_id' => Category::where('slug', $l['category'])->value('id'),
                'city_id' => City::where('slug', $l['city'])->value('id'),
                'title' => 'Sample: '.$l['title'], 'slug' => $l['slug'], 'suburb' => $l['suburb'],
                'summary' => $l['summary'], 'description' => '<p>'.e($l['description']).'</p><p><em>'.self::NOTE.'</em></p>',
                'deal_type' => 'commission', 'seller_net_price' => (int) round($l['minimum'] * 0.8), 'fee_bps' => 1000,
                'minimum_price' => $l['minimum'], 'deposit_amount' => $l['deposit'], 'deposit_terms' => $l['terms'],
                'auction_type' => $l['type'], 'status' => $l['status'], 'is_featured' => $l['featured'] ?? false,
                'starts_at' => now()->addDays($l['starts']), 'ends_at' => now()->addDays($l['ends']),
            ]);
            foreach ($l['attributes'] as $key => $value) {
                $attr = DB::table('category_attributes')->where('category_id', $listing->category_id)->where('key', $key)->value('id');
                if ($attr) {
                    DB::table('listing_attribute_values')->insert(['listing_id' => $listing->id, 'category_attribute_id' => $attr, 'value' => (string) $value, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            $listing->addMedia($this->photo($l['photo'], $l['category']))->toMediaCollection('photos');

            if ($l['status'] === ListingStatus::Sold) {
                $this->recordSale($listing, (int) $l['sold_for']);
            }
        }

        if (! Event::where('slug', 'sample-investor-briefing')->exists()) {
            Event::create(['title' => 'Sample: Investor briefing on mining and land auctions', 'slug' => 'sample-investor-briefing', 'kind' => 'in_person',
                'venue' => 'Sandton Convention Centre, Johannesburg', 'starts_at' => now()->addDays(14)->setTime(10, 0), 'ends_at' => now()->addDays(14)->setTime(13, 0),
                'price' => 0, 'capacity' => 120, 'status' => 'published',
                'description' => '<p>How online property and mining-right auctions work: verification, deposits, bidding and settlement.</p><p><em>Sample event for demonstration.</em></p>']);
        }

        foreach ($this->posts() as $p) {
            if (Post::where('slug', $p['slug'])->exists()) {
                continue;
            }
            $post = Post::create(['title' => 'Sample: '.$p['title'], 'slug' => $p['slug'], 'topic' => $p['topic'], 'excerpt' => $p['excerpt'],
                'body' => $p['body'].'<p><em>Sample article for demonstration.</em></p>', 'status' => 'published', 'published_at' => now()->subDays($p['days'])]);
            $post->addMedia($this->photo($p['photo'], 'land'))->toMediaCollection('cover');
        }
    }

    /** A closed auction with a winning bid, so "Sold" results show (price, date, bidder number). */
    private function recordSale(Listing $listing, int $amount): void
    {
        $buyer = User::query()->where('email', 'sample.bidder@thabekhulu.invalid')->first() ?? User::query()->forceCreate([
            'type' => 'buyer', 'first_name' => 'Sample', 'last_name' => 'Bidder', 'email' => 'sample.bidder@thabekhulu.invalid',
            'password' => bin2hex(random_bytes(24)), 'email_verified_at' => now(), 'deactivated_at' => now(), // cannot sign in
        ]);
        $approval = BidderApproval::create(['listing_id' => $listing->id, 'user_id' => $buyer->id, 'status' => 'approved', 'bidder_number' => '4821']);
        Bid::create(['listing_id' => $listing->id, 'bidder_approval_id' => $approval->id, 'bidder_number' => '4821', 'amount' => $amount, 'kind' => 'manual', 'placed_at' => $listing->ends_at?->subHour()]);
        DB::table('auction_states')->insert(['listing_id' => $listing->id, 'current_amount' => $amount, 'leader_approval_id' => $approval->id, 'ends_at' => $listing->ends_at,
            'extensions' => 0, 'bid_count' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('auction_results')->insert(['listing_id' => $listing->id, 'kind' => 'open', 'outcome' => 'sold', 'winner_approval_id' => $approval->id, 'winning_amount' => $amount,
            'bidders' => 1, 'closed_at' => $listing->ends_at, 'handed_off_at' => $listing->ends_at]);
    }

    /** The downloaded photo, or a branded placeholder (a temporary copy: the library moves the file it is given). */
    private function photo(string $key, string $category): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'demo').'.jpg';
        $real = storage_path("demo-photos/{$key}.jpg");
        if (is_file($real) && @getimagesize($real) !== false) {
            copy($real, $tmp);

            return $tmp;
        }
        [$r, $g, $b] = ['homes' => [29, 74, 60], 'land' => [94, 120, 64], 'mining' => [74, 58, 46], 'malls' => [44, 62, 92]][$category] ?? [29, 74, 60];
        $img = imagecreatetruecolor(1600, 1000);
        for ($y = 0; $y < 1000; $y++) { // vertical gradient in the category's colour
            $f = $y / 1000;
            $c = fn (int $v): int => max(0, min(255, (int) ($v + (255 - $v) * $f * 0.35)));
            imageline($img, 0, $y, 1599, $y, (int) imagecolorallocate($img, $c($r), $c($g), $c($b)));
        }
        $white = (int) imagecolorallocate($img, 255, 255, 255);
        imagestring($img, 5, 60, 900, 'Thabekhulu auctions  |  Sample listing  |  Illustrative image', $white);
        imagejpeg($img, $tmp, 85);
        imagedestroy($img);

        return $tmp;
    }

    /** @return list<array<string, mixed>> */
    private function listings(): array
    {
        return [
            ['slug' => 'sample-modern-villa-camps-bay', 'category' => 'homes', 'city' => 'cape-town', 'suburb' => 'Camps Bay', 'title' => 'Modern villa with sea views, Camps Bay',
                'summary' => 'Four-bedroom contemporary villa with pool and Atlantic views.', 'description' => 'Open-plan living levels open onto a pool terrace facing the Atlantic. Four en-suite bedrooms, double garage and staff accommodation.',
                'minimum' => 12500000, 'deposit' => 250000, 'terms' => 'applied', 'type' => 'open', 'status' => ListingStatus::Live, 'starts' => -2, 'ends' => 7, 'featured' => true, 'photo' => 'villa',
                'attributes' => ['bedrooms' => 4, 'bathrooms' => 4, 'garages' => 2, 'erf_size' => 890, 'floor_area' => 520]],
            ['slug' => 'sample-apartment-block-cape-town', 'category' => 'homes', 'city' => 'cape-town', 'suburb' => 'Gardens', 'title' => 'Apartment with mountain views, Gardens',
                'summary' => 'Two-bedroom apartment in a secure block near the city centre.', 'description' => 'North-facing two-bedroom apartment with balcony, secure parking and building amenities, walking distance from Kloof Street.',
                'minimum' => 2850000, 'deposit' => 50000, 'terms' => 'applied', 'type' => 'open', 'status' => ListingStatus::Scheduled, 'starts' => 5, 'ends' => 15, 'photo' => 'apartment',
                'attributes' => ['bedrooms' => 2, 'bathrooms' => 2, 'garages' => 1, 'floor_area' => 96, 'levies' => 3200]],
            ['slug' => 'sample-wine-estate-land-stellenbosch', 'category' => 'land', 'city' => 'stellenbosch', 'suburb' => 'Blaauwklippen Valley', 'title' => 'Wine-estate land, Stellenbosch',
                'summary' => '42 ha of agricultural land with water rights and mountain views.', 'description' => 'Gently sloping agricultural land suited to vineyards, with registered water rights, farm dam and tarred road access.',
                'minimum' => 18000000, 'deposit' => 500000, 'terms' => 'applied', 'type' => 'open', 'status' => ListingStatus::Live, 'starts' => -1, 'ends' => 10, 'featured' => true, 'photo' => 'estate',
                'attributes' => ['hectares' => 42, 'zoning' => 'Agricultural', 'water_rights' => 1, 'services' => 'Borehole, Eskom supply', 'access' => 'Tarred road']],
            ['slug' => 'sample-farm-portion-west-coast', 'category' => 'land', 'city' => 'worcester', 'suburb' => 'Breede Valley', 'title' => 'Farm portion with grazing, Breede Valley',
                'summary' => '120 ha mixed-use farm portion with grazing and farmhouse.', 'description' => 'Mixed grazing and cultivated land with a farmhouse, sheds and two boreholes.',
                'minimum' => 6500000, 'deposit' => 150000, 'terms' => 'applied', 'type' => 'open', 'status' => ListingStatus::Scheduled, 'starts' => 7, 'ends' => 21, 'photo' => 'farm',
                'attributes' => ['hectares' => 120, 'zoning' => 'Agricultural', 'water_rights' => 0, 'access' => 'Gravel road']],
            ['slug' => 'sample-smallholding-brits', 'category' => 'land', 'city' => 'brits', 'suburb' => 'Hartbeespoort area', 'title' => 'Smallholding near Hartbeespoort, Brits',
                'summary' => '8.5 ha smallholding: sold at auction.', 'description' => 'Smallholding with a main house, cottage and irrigated paddocks.',
                'minimum' => 3200000, 'deposit' => 75000, 'terms' => 'applied', 'type' => 'open', 'status' => ListingStatus::Sold, 'starts' => -20, 'ends' => -3, 'photo' => 'aerial', 'sold_for' => 3650000,
                'attributes' => ['hectares' => 8.5, 'zoning' => 'Agricultural', 'water_rights' => 1]],
            ['slug' => 'sample-coal-prospecting-right-emalahleni', 'category' => 'mining', 'city' => 'emalahleni', 'suburb' => 'Witbank coalfield', 'title' => 'Coal prospecting right, Emalahleni',
                'summary' => 'Granted prospecting right over 640 ha: sealed tender.', 'description' => 'Prospecting right over 640 ha in the Witbank coalfield with a completed drilling programme. Offered by sealed tender; transfer subject to section 11 consent.',
                'minimum' => 23000000, 'deposit' => 1200000, 'terms' => 'security', 'type' => 'sealed', 'status' => ListingStatus::Live, 'starts' => -3, 'ends' => 18, 'photo' => 'mine',
                'attributes' => ['commodity' => 'Coal', 'right_type' => 'Prospecting right', 'right_reference' => 'MP 30/5/1/1/2/SAMPLE', 'right_status' => 'Granted', 'area' => 640,
                    'resource_statement' => 1, 'environmental_authorisation' => 'Yes', 'deal_structure' => 'Transfer of right (section 11 consent)']],
            ['slug' => 'sample-shopping-centre-polokwane', 'category' => 'malls', 'city' => 'polokwane', 'suburb' => 'Bendor', 'title' => 'Neighbourhood shopping centre, Polokwane',
                'summary' => '18 500 m² centre, 42 tenants, 94% occupied.', 'description' => 'Established neighbourhood centre anchored by a national grocer, with 600 parking bays and long leases.',
                'minimum' => 145000000, 'deposit' => 2500000, 'terms' => 'applied', 'type' => 'open', 'status' => ListingStatus::Live, 'starts' => -1, 'ends' => 12, 'photo' => 'mall',
                'attributes' => ['gla' => 18500, 'tenants' => 42, 'occupancy' => 94, 'anchor_tenant' => 'National grocer']],
            ['slug' => 'sample-retail-centre-durban', 'category' => 'malls', 'city' => 'durban', 'suburb' => 'Berea', 'title' => 'Convenience retail centre, Berea',
                'summary' => '6 200 m² convenience centre with 18 tenants.', 'description' => 'Convenience centre on a busy arterial road with fuel-station frontage.',
                'minimum' => 48000000, 'deposit' => 1000000, 'terms' => 'applied', 'type' => 'open', 'status' => ListingStatus::Scheduled, 'starts' => 10, 'ends' => 24, 'photo' => 'mall2',
                'attributes' => ['gla' => 6200, 'tenants' => 18, 'occupancy' => 89]],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function posts(): array
    {
        return [
            ['slug' => 'sample-how-online-property-auctions-work', 'title' => 'How online property auctions work', 'topic' => 'Buying at auction', 'days' => 3, 'photo' => 'villa',
                'excerpt' => 'From verifying your identity to placing your first bid: the steps, in plain language.',
                'body' => '<p>Register, verify your identity, apply to bid on a listing and pay the deposit. Once approved, you bid live; a bid in the last five minutes extends the auction.</p>'],
            ['slug' => 'sample-buying-a-mining-right', 'title' => 'Buying a mining right at auction', 'topic' => 'Mining', 'days' => 9, 'photo' => 'estate',
                'excerpt' => 'Why the winning bidder becomes the preferred bidder, and what section 11 consent means.',
                'body' => '<p>A winning bid does not by itself transfer a mining right: the transfer needs the Minister’s consent under section 11 of the MPRDA, so the winner is the preferred bidder until it is granted.</p>'],
        ];
    }
}
