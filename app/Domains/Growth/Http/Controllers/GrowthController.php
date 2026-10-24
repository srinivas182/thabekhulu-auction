<?php

namespace App\Domains\Growth\Http\Controllers;

use App\Domains\Audit\Support\Audit;
use App\Domains\Growth\Notifications\GrowthMail;
use App\Domains\Listings\Models\Listing;
use App\Domains\Listings\Public\ListingSearch;
use App\Domains\Listings\Public\PublicListing;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Growth (G1): saved searches, watchlist, newsletter double opt-in. */
class GrowthController extends Controller
{
    /** Filters a saved search may keep (the public search's own keys). */
    public const FILTER_KEYS = ['category', 'province', 'city', 'min', 'max', 'status', 'renovated', 'within'];

    // --- saved searches -------------------------------------------------------------------

    public function saveSearch(Request $request, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless($user->isBuyer(), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'filters' => ['array']]);
        $filters = array_filter(array_intersect_key((array) ($data['filters'] ?? []), array_flip(self::FILTER_KEYS)), fn ($v) => $v !== null && $v !== '');
        if (DB::table('saved_searches')->where('user_id', $user->id)->count() >= 10) {
            return back()->withErrors(['name' => __('You can keep up to 10 saved searches.')]);
        }

        $id = DB::table('saved_searches')->insertGetId(['ulid' => (string) Str::ulid(), 'user_id' => $user->id, 'name' => $data['name'], 'filters' => json_encode($filters), 'created_at' => now(), 'updated_at' => now()]);
        // Today's matches are recorded now, so alerts only ever cover new listings.
        foreach (ListingSearch::run($filters, 200)->items() as $l) {
            DB::table('saved_search_hits')->insertOrIgnore(['saved_search_id' => $id, 'listing_id' => $l->id, 'created_at' => now()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Search saved. We’ll email you when new matching auctions are listed.')]);

        return back();
    }

    public function deleteSearch(string $ulid, #[CurrentUser] User $user): RedirectResponse
    {
        DB::table('saved_searches')->where('ulid', $ulid)->where('user_id', $user->id)->delete();

        return back();
    }

    // --- watchlist ------------------------------------------------------------------------

    public function toggleWatch(Listing $listing, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless($user->isBuyer() && $listing->status->isPublic(), 403);
        $q = DB::table('watchlist_items')->where('user_id', $user->id)->where('listing_id', $listing->id);
        $q->exists() ? $q->delete() : DB::table('watchlist_items')->insert(['user_id' => $user->id, 'listing_id' => $listing->id, 'created_at' => now()]);

        return back();
    }

    public function mine(#[CurrentUser] User $user): Response
    {
        return Inertia::render('account/saved', [
            'searches' => DB::table('saved_searches')->where('user_id', $user->id)->latest('id')->get()
                ->map(fn ($s) => ['ulid' => $s->ulid, 'name' => $s->name, 'url' => '/auctions?'.http_build_query((array) json_decode((string) $s->filters, true))]),
            'watching' => Listing::with(['category', 'city'])->whereIn('id', DB::table('watchlist_items')->where('user_id', $user->id)->pluck('listing_id'))
                ->get()->filter(fn (Listing $l) => $l->status->isShown())->map(fn (Listing $l) => PublicListing::card($l))->values(),
        ]);
    }

    // --- newsletter (POPIA double opt-in) ----------------------------------------------------

    public function subscribe(Request $request): RedirectResponse
    {
        if (filled($request->input('website'))) {
            return back(); // honeypot
        }
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:190'], 'name' => ['nullable', 'string', 'max:120'], 'consent' => ['accepted']],
            ['consent.accepted' => __('Please agree to receive our newsletter.')]);
        $email = strtolower($data['email']);
        $row = DB::table('newsletter_subscribers')->where('email', $email)->first();

        if ($row === null || $row->status !== 'confirmed') {
            $ulid = $row->ulid ?? (string) Str::ulid();
            DB::table('newsletter_subscribers')->updateOrInsert(['email' => $email], [
                'ulid' => $ulid, 'name' => $data['name'] ?? null, 'status' => 'pending', 'consent_at' => now(), 'ip_address' => $request->ip(),
                'unsubscribed_at' => null, 'updated_at' => now(), 'created_at' => $row->created_at ?? now(),
            ]);
            Notification::route('mail', $email)->notify(new GrowthMail('newsletter_confirm', __('Confirm your subscription'), [
                __('Please confirm that you would like to receive Thabekhulu auction news by email.'),
                __('If you didn’t ask for this, ignore this email and you won’t be subscribed.'),
            ], __('Confirm subscription'), URL::temporarySignedRoute('newsletter.confirm', now()->addDays(7), ['subscriber' => $ulid])));
        }
        // The same reply either way, so the form can't be used to discover who is subscribed.
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Check your email to confirm your subscription.')]);

        return back();
    }

    public function confirm(string $subscriber): RedirectResponse
    {
        $n = DB::table('newsletter_subscribers')->where('ulid', $subscriber)->whereIn('status', ['pending', 'unsubscribed'])
            ->update(['status' => 'confirmed', 'confirmed_at' => now(), 'unsubscribed_at' => null, 'updated_at' => now()]);
        Inertia::flash('toast', ['type' => 'success', 'message' => $n > 0 ? __('You’re subscribed. Thank you.') : __('Your subscription is already confirmed.')]);

        return redirect('/');
    }

    public function unsubscribe(string $subscriber): RedirectResponse
    {
        DB::table('newsletter_subscribers')->where('ulid', $subscriber)->update(['status' => 'unsubscribed', 'unsubscribed_at' => now(), 'updated_at' => now()]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('You’ve been unsubscribed.')]);

        return redirect('/');
    }

    /** Confirmed subscribers for the mailing tool, each with a signed unsubscribe link. */
    public function export(#[CurrentUser] User $actor): StreamedResponse
    {
        Audit::record('growth', 'newsletter_exported', 'Exported newsletter subscribers', null, $actor);

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['Email', 'Name', 'Confirmed', 'Unsubscribe link'], ',', '"', '\\');
            foreach (DB::table('newsletter_subscribers')->where('status', 'confirmed')->orderBy('email')->get() as $s) {
                fputcsv($out, [$s->email, $s->name, $s->confirmed_at, URL::signedRoute('newsletter.unsubscribe', ['subscriber' => $s->ulid])], ',', '"', '\\');
            }
            fclose($out);
        }, 'newsletter-subscribers-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv', 'Cache-Control' => 'no-store, private']);
    }
}
