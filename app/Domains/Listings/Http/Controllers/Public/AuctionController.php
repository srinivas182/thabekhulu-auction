<?php

namespace App\Domains\Listings\Http\Controllers\Public;

use App\Domains\Bidding\Support\AuctionView;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Catalogue\Models\Province;
use App\Domains\Listings\Models\Listing;
use App\Domains\Listings\Public\ListingSearch;
use App\Domains\Listings\Public\PublicListing;
use App\Domains\Settings\Settings\GeneralSettings;
use App\Domains\Verification\Models\BidderApproval;
use App\Domains\Verification\Models\BiddingEntity;
use App\Domains\Verification\Models\Verification;
use App\Domains\Verification\Support\BidderEligibility;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class AuctionController extends Controller
{
    public function index(Request $request, ?Category $category = null, ?string $city = null): Response
    {
        abort_if($category !== null && ! $category->is_visible, 404);
        $cityModel = $city !== null ? City::where('slug', $city)->firstOrFail() : null;

        $filters = [
            'category' => $category->slug ?? $request->string('category')->toString() ?: null,
            'province' => $request->string('province')->toString() ?: null,
            'city' => $cityModel->slug ?? ($request->string('city')->toString() ?: null),
            'min' => $request->integer('min') ?: null,
            'max' => $request->integer('max') ?: null,
            'within' => $request->string('within')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
            'renovated' => $request->boolean('renovated'),
            'sort' => $request->string('sort')->toString() ?: 'closing',
        ];

        $results = ListingSearch::run($filters)->through(fn (Listing $l): array => PublicListing::card($l));

        $place = $cityModel?->name;
        $heading = match (true) {
            $category !== null && $place !== null => "{$category->name} auctions in {$place}",
            $category !== null => "{$category->name} auctions",
            default => 'Property auctions',
        };

        return Inertia::render('public/auctions/index', [
            'heading' => $heading,
            'intro' => $category?->description,
            'seo' => [
                'title' => $category->seo_title ?? "{$heading} | Thabekhulu",
                'description' => $category->seo_description ?? 'Bid online for homes, land, mining and commercial property across South Africa.',
            ],
            'results' => $results,
            'filters' => $filters,
            'categories' => Category::where('is_visible', true)->orderBy('sort_order')->get(['name', 'slug']),
            'provinces' => Province::orderBy('name')->get(['name', 'slug']),
            'sorts' => ListingSearch::SORTS,
            'basePath' => $category !== null ? route('auctions.category', $category, false) : route('auctions.index', [], false),
        ]);
    }

    public function show(Category $category, string $city, string $slug, GeneralSettings $general): Response
    {
        $listing = Listing::public()->where('slug', $slug)->where('category_id', $category->id)
            ->with(['category', 'city.province'])->firstOrFail();

        // Old or wrong path segments redirect to the canonical address.
        if (($listing->city->slug ?? 'south-africa') !== $city) {
            abort(redirect(PublicListing::url($listing), 301));
        }

        $data = PublicListing::detail($listing);

        $structured = $this->structuredData($listing, $data);

        // Structured data is written by the Blade template so it is always in
        // the HTML, even if the server-side render service is unavailable.
        return Inertia::render('public/auctions/show', [
            'listing' => $data,
            'bidder' => $this->bidderState($listing),
            'auction' => AuctionView::for($listing, request()->user()),
            'office' => ['phone' => $general->contact_phone, 'email' => $general->contact_email],
            'seo' => [
                'title' => ($listing->seo_title ?: $listing->title).' | Thabekhulu auctions',
                'description' => $listing->seo_description ?: (string) $listing->summary,
                'image' => $data['image'],
                'canonical' => $data['url'],
            ],
        ])->withViewData(['structuredData' => $structured]);
    }

    /**
     * Where the signed-in buyer stands for this listing (shown in the bid panel).
     *
     * @return array<string, mixed>|null
     */
    private function bidderState(Listing $listing): ?array
    {
        $user = request()->user();
        if (! $user instanceof User || ! $user->isBuyer()) {
            return null;
        }

        $approval = BidderApproval::where('listing_id', $listing->id)->where('user_id', $user->id)->first();
        $verification = Verification::where('user_id', $user->id)->latest('id')->first();
        $eligibility = app(BidderEligibility::class);

        return [
            'verification' => $verification?->status,
            'approval' => $approval?->status,
            'bidderNumber' => $approval?->status === 'approved' ? $approval->bidder_number : null,
            'reason' => $approval !== null && in_array($approval->status, ['rejected', 'revoked'], true) ? $approval->reason : null,
            'needsFunds' => $eligibility->needsProofOfFunds($listing),
            'entities' => BiddingEntity::where('user_id', $user->id)->where('status', '!=', 'rejected')->get(['ulid', 'name', 'status'])
                ->map(fn ($e) => ['ulid' => $e->ulid, 'name' => $e->name, 'verified' => $e->status === 'verified']),
        ];
    }

    public function sitemap(): HttpResponse
    {
        $urls = [route('home'), route('auctions.index')];
        foreach (Category::where('is_visible', true)->orderBy('sort_order')->get() as $c) {
            $urls[] = route('auctions.category', $c);
        }
        Listing::public()->with(['category', 'city'])->each(function (Listing $l) use (&$urls): void {
            $urls[] = PublicListing::url($l);
        });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $u) {
            $xml .= '<url><loc>'.e($u).'</loc></url>';
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * schema.org data for search engines and AI answers (spec 5.6).
     *
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function structuredData(Listing $listing, array $data): array
    {
        $place = array_filter([$listing->suburb, $listing->city?->name, $listing->city?->province->name]);

        return [
            [
                '@context' => 'https://schema.org',
                '@type' => 'RealEstateListing',
                'name' => $listing->title,
                'description' => $listing->summary,
                'url' => $data['url'],
                'image' => $data['image'],
                'datePosted' => $listing->approved_at?->toIso8601String(),
                'contentLocation' => ['@type' => 'Place', 'name' => implode(', ', $place), 'address' => [
                    '@type' => 'PostalAddress', 'addressLocality' => $listing->city?->name, 'addressRegion' => $listing->city?->province->name, 'addressCountry' => 'ZA',
                ]],
                'offers' => [
                    '@type' => 'Offer', 'price' => $listing->minimum_price, 'priceCurrency' => 'ZAR',
                    'availabilityStarts' => $listing->starts_at?->toIso8601String(), 'availabilityEnds' => $listing->ends_at?->toIso8601String(),
                    'seller' => ['@type' => 'Organization', 'name' => 'Thabekhulu Development Group'],
                ],
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Auctions', 'item' => route('auctions.index')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => $listing->category->name, 'item' => route('auctions.category', $listing->category)],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => $listing->title, 'item' => $data['url']],
                ],
            ],
        ];
    }
}
