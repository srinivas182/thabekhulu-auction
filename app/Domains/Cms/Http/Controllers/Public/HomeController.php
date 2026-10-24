<?php

namespace App\Domains\Cms\Http\Controllers\Public;

use App\Domains\Catalogue\Models\Category;
use App\Domains\Cms\Models\Collection;
use App\Domains\Cms\Settings\HomeSettings;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Listings\Public\PublicListing;
use App\Domains\Platform\Support\Seo;
use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Home page from the approved design (Concept A + calendar + live bids),
 * wired to real listings. Live bid activity arrives with bidding (Sprint 6).
 */
class HomeController extends Controller
{
    public function __invoke(HomeSettings $home): Response
    {
        $with = ['category.attributeDefinitions', 'attributeValues', 'city.province', 'media'];

        $featured = $home->featured_listing
            ? Listing::public()->with($with)->where('ulid', $home->featured_listing)->first()
            : null;

        $closing = Listing::where('status', ListingStatus::Live)->with($with)
            ->whereHas('category', fn ($c) => $c->where('is_visible', true))
            ->orderBy('ends_at')->limit(3)->get();

        $tz = (string) config('platform.display_timezone');

        return Inertia::render('public/home', [
            'hero' => ['title' => $home->hero_title, 'text' => $home->hero_text],
            'featured' => $featured !== null ? PublicListing::card($featured) + ['closesLabel' => $featured->ends_at?->setTimezone($tz)->format('j F, H:i')] : null,
            'closingSoon' => $closing->map(fn (Listing $l): array => PublicListing::card($l))->values(),
            'categories' => Category::where('is_visible', true)->orderBy('sort_order')
                ->withCount(['listings' => fn ($q) => $q->public()])
                ->get()->map(fn (Category $c): array => ['name' => $c->name, 'slug' => $c->slug, 'description' => $c->description, 'count' => (int) $c->getAttribute('listings_count')]),
            'collections' => Collection::where('is_visible', true)->orderBy('sort_order')->get()
                ->map(fn (Collection $c): array => ['name' => $c->name, 'description' => $c->description, 'count' => $c->listings()->count(), 'url' => $this->collectionUrl($c)])
                ->filter(fn (array $c): bool => $c['count'] > 0)->values(),
            'steps' => $home->steps,
            'calendar' => $home->show_calendar ? $this->calendar() : [],
            'ffcNumber' => $home->ffc_number,
            'seo' => Seo::make('Property and mining auctions in South Africa | Thabekhulu', (string) $home->hero_text, null, url('/')),
        ])->withViewData(['structuredData' => [
            ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => 'Thabekhulu Development Group', 'url' => url('/'), 'logo' => url('/favicon.svg'), 'areaServed' => 'ZA'],
            // No SearchAction: the site has no free-text search to advertise.
            ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => 'Thabekhulu auctions', 'url' => url('/')],
        ]]);
    }

    /**
     * Live and coming auctions grouped by closing week (next 6 weeks).
     *
     * @return list<array{week: string, count: int, categories: string}>
     */
    private function calendar(): array
    {
        $tz = (string) config('platform.display_timezone');

        return array_values(Listing::public()->with('category:id,name')
            ->whereBetween('ends_at', [now(), now()->addWeeks(6)])
            ->orderBy('ends_at')->get()
            ->groupBy(fn (Listing $l): string => (string) $l->ends_at?->setTimezone($tz)->startOfWeek()->toDateString())
            ->map(function ($group, string $start): array {
                $from = Carbon::parse($start);

                return [
                    'week' => $from->format('j M').' – '.$from->copy()->addDays(6)->format('j M'),
                    'count' => $group->count(),
                    'categories' => $group->pluck('category.name')->unique()->implode(', '),
                ];
            })->all());
    }

    private function collectionUrl(Collection $c): string
    {
        $query = array_filter(['category' => $c->category?->slug, 'renovated' => $c->renovated_only ? 1 : null]);

        return route('auctions.index', $query, false);
    }
}
