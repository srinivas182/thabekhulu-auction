<?php

namespace Database\Seeders;

use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\CategoryAttribute;
use App\Domains\Cms\Models\Collection;
use App\Domains\Cms\Models\MenuItem;
use App\Domains\Cms\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Starting website structure (sprint 3 spec 6, revision 2). Idempotent.
 * Pages awaiting real text are marked needs_content; legal texts must come
 * from Thabekhulu's attorney before launch.
 */
class CmsSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            ['about', 'About Thabekhulu', false, 'Who Thabekhulu Development Group is, what we sell and why buyers can trust the process.'],
            ['how-it-works', 'How auctions work', false, 'Registration, verification, deposits, bidding, the close and what happens after you win.'],
            ['buyers-guide', 'Buyer’s guide', false, 'Practical guidance for first-time and investor buyers.'],
            ['fees', 'Fees and costs', false, 'What buyers and sellers pay, with examples.'],
            ['contact', 'Contact us', false, 'Office address, phone, email and hours.'],
            ['rules-of-auction', 'Rules of Auction', true, 'Rules of Auction as required by the Consumer Protection Act, signed by the auctioneer.'],
            ['privacy', 'Privacy notice (POPIA)', true, 'How personal information is collected, used, stored and protected.'],
            ['paia-manual', 'PAIA manual', true, 'Manual in terms of section 51 of the Promotion of Access to Information Act.'],
            ['disclaimer', 'Disclaimer', true, 'Listings are subject to verification; a winning bid does not by itself transfer a mining right or guarantee regulatory approval.'],
            ['automatically-available-records', 'Notice of automatically available records', true, 'Records available without a formal PAIA request.'],
        ];

        foreach ($pages as [$slug, $title, $legal, $about]) {
            Page::firstOrCreate(['slug' => $slug], [
                'title' => $title,
                'status' => 'published',
                'is_system' => true,
                'needs_content' => true,
                'published_at' => now(),
                'blocks' => [[
                    'type' => 'rich_text',
                    'html' => '<p>'.e($about).'</p><p><strong>['.($legal ? 'Text to be supplied by Thabekhulu’s attorney' : 'Text to be supplied by Thabekhulu').']</strong></p>',
                ]],
            ]);
        }

        if (MenuItem::count() === 0) {
            foreach ([['Auctions', '/auctions'], ['How it works', '/how-it-works'], ['About', '/about'], ['Contact', '/contact']] as $i => [$label, $url]) {
                MenuItem::create(['menu' => 'header', 'label' => $label, 'url' => $url, 'sort_order' => $i]);
            }
            foreach ([['Buyer’s guide', '/buyers-guide'], ['Fees', '/fees'], ['Rules of Auction', '/rules-of-auction'], ['Privacy (POPIA)', '/privacy'], ['PAIA manual', '/paia-manual'], ['Disclaimer', '/disclaimer'], ['Automatically available records', '/automatically-available-records'], ['Contact', '/contact']] as $i => [$label, $url]) {
                MenuItem::create(['menu' => 'footer', 'label' => $label, 'url' => $url, 'sort_order' => $i]);
            }
        }

        $mining = Category::where('slug', 'mining')->first();
        $commodity = $mining ? CategoryAttribute::where('category_id', $mining->id)->where('key', 'commodity')->first() : null;
        $collections = [
            ['coal-mining', 'Coal mining', 'Coal mining and prospecting rights.', $mining?->id, $commodity?->id, 'Coal', false],
            ['income-producing', 'Income-producing property', 'Shopping centres with tenants and rental income.', Category::where('slug', 'malls')->value('id'), null, null, false],
            ['development-land', 'Development land', 'Farms, plots and land ready for development.', Category::where('slug', 'land')->value('id'), null, null, false],
            ['renovated-homes', 'Renovated homes', 'Homes renovated by Thabekhulu before sale.', Category::where('slug', 'homes')->value('id'), null, null, true],
        ];
        foreach ($collections as $i => [$slug, $name, $description, $category, $attribute, $value, $renovated]) {
            Collection::firstOrCreate(['slug' => $slug], [
                'name' => $name, 'description' => $description, 'category_id' => $category,
                'category_attribute_id' => $attribute, 'attribute_value' => $value, 'renovated_only' => $renovated, 'sort_order' => $i,
            ]);
        }
    }
}
