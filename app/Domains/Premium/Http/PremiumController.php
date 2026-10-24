<?php

namespace App\Domains\Premium\Http;

use App\Domains\Audit\Support\Audit;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Listings\Models\Listing;
use App\Domains\Premium\Models\PremiumSlot;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Admin-assigned premium slots (premium.manage, S10). */
class PremiumController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/premium/index', [
            'slots' => PremiumSlot::with(['listing:id,title', 'category:id,name', 'city:id,name'])->orderByDesc('ends_on')->limit(200)->get()->map(fn (PremiumSlot $s) => [
                'id' => $s->id, 'listing' => $s->listing->title, 'category' => $s->category->name, 'city' => $s->city->name ?? 'All cities',
                'position' => $s->position, 'from' => $s->starts_on->format('j M Y'), 'to' => $s->ends_on->format('j M Y'),
                'active' => $s->starts_on->lte(today()) && $s->ends_on->gte(today()),
            ]),
            'listings' => Listing::public()->orderBy('title')->get(['ulid', 'title', 'category_id', 'city_id'])->map(fn ($l) => ['ulid' => $l->ulid, 'title' => $l->title, 'category' => $l->category_id, 'city' => $l->city_id]),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'cities' => City::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, #[CurrentUser] User $actor): RedirectResponse
    {
        $d = $request->validate([
            'listing' => ['required', 'exists:listings,ulid'], 'city_id' => ['nullable', 'exists:cities,id'],
            'position' => ['required', 'integer', 'between:1,3'], 'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ]);
        $d['city_id'] ??= null; // "Whole category" may arrive without a city (found in testing)
        $listing = Listing::where('ulid', $d['listing'])->firstOrFail();
        if (! $listing->status->isPublic()) {
            throw ValidationException::withMessages(['listing' => __('Only listings on the website can have a premium slot.')]);
        }
        if ($d['city_id'] !== null && (int) $d['city_id'] !== $listing->city_id) {
            throw ValidationException::withMessages(['city_id' => __('Choose the listing’s own city, or all cities.')]);
        }

        $overlap = fn () => PremiumSlot::where('category_id', $listing->category_id)
            ->where(fn ($q) => $d['city_id'] === null ? $q->whereNull('city_id') : $q->where('city_id', $d['city_id']))
            ->whereDate('starts_on', '<=', $d['ends_on'])->whereDate('ends_on', '>=', $d['starts_on']);
        if ($overlap()->where('position', $d['position'])->exists()) {
            throw ValidationException::withMessages(['position' => __('That position is already taken in this category and city for those dates.')]);
        }
        if ($overlap()->where('listing_id', $listing->id)->exists()) {
            throw ValidationException::withMessages(['listing' => __('This listing already has a slot here for those dates.')]);
        }

        $slot = PremiumSlot::create(['listing_id' => $listing->id, 'category_id' => $listing->category_id, 'city_id' => $d['city_id'], 'position' => $d['position'],
            'starts_on' => $d['starts_on'], 'ends_on' => $d['ends_on'], 'created_by' => $actor->id]);
        Audit::record('premium', 'assigned', "Premium position {$slot->position} for {$listing->title} ({$d['starts_on']} to {$d['ends_on']})", $listing, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Premium slot assigned.')]);

        return back();
    }

    public function destroy(PremiumSlot $slot, #[CurrentUser] User $actor): RedirectResponse
    {
        Audit::record('premium', 'removed', "Removed premium position {$slot->position} for {$slot->listing->title}", $slot->listing, $actor);
        $slot->delete();

        return back();
    }
}
