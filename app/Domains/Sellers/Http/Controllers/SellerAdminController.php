<?php

namespace App\Domains\Sellers\Http\Controllers;

use App\Domains\Audit\Support\Audit;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Leads\Models\Lead;
use App\Domains\Listings\Enums\AuctionType;
use App\Domains\Listings\Enums\DealType;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Sellers\Models\SellerPackage;
use App\Domains\Verification\Support\SecureFiles;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** Staff side of the seller module: lead documents, lead → draft listing, packages. */
class SellerAdminController extends Controller
{
    /** Turns a lead (seller submission or "Sell with us") into a draft listing linked to the seller. */
    public function convert(Request $request, Lead $lead, #[CurrentUser] User $actor): RedirectResponse
    {
        abort_if($lead->listing_id !== null, 422, __('This lead already has a listing.'));
        $data = $request->validate(['category_id' => ['required', 'integer', 'exists:categories,id'], 'title' => ['required', 'string', 'max:160']]);
        $category = Category::query()->whereKey((int) $data['category_id'])->firstOrFail();

        $listing = Listing::create([
            'category_id' => $category->id, 'title' => $data['title'], 'slug' => $this->uniqueSlug($data['title']), 'deal_type' => DealType::Commission,
            'fee_bps' => $category->default_fee_bps, 'deposit_amount' => $category->default_deposit, 'deposit_terms' => $category->default_deposit_terms,
            'auction_type' => AuctionType::Open, 'status' => ListingStatus::Draft, 'created_by' => $actor->id, 'updated_by' => $actor->id,
            'seller_user_id' => $lead->user_id,
        ]);
        $lead->forceFill(['listing_id' => $listing->id, 'status' => 'converted'])->save();
        Audit::record('listings', 'created_from_lead', "Created draft {$listing->title} from a seller lead", $listing, $actor);

        return redirect()->route('admin.listings.edit', $listing);
    }

    public function document(Lead $lead, int $document, #[CurrentUser] User $actor): HttpResponse
    {
        $doc = DB::table('lead_documents')->where('id', $document)->where('lead_id', $lead->id)->first() ?? abort(404);
        Audit::record('leads', 'document_viewed', "Opened {$doc->kind} for lead {$lead->ulid}", $lead, $actor);

        return response(SecureFiles::read((string) $doc->path), 200, ['Content-Type' => (string) $doc->mime, 'Content-Disposition' => 'inline', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function packages(): Response
    {
        return Inertia::render('admin/sellers/packages', [
            'packages' => SellerPackage::orderBy('name')->get(['id', 'name', 'price', 'duration_days', 'max_listings', 'active']),
            'sellers' => User::where('type', 'seller')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'email'])->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email,
                'expires' => DB::table('seller_package_assignments')->where('user_id', $u->id)->max('expires_on')]),
        ]);
    }

    public function storePackage(Request $request, #[CurrentUser] User $actor): RedirectResponse
    {
        $d = $request->validate(['name' => ['required', 'string', 'max:80'], 'price' => ['required', 'numeric', 'min:0'], 'duration_days' => ['required', 'integer', 'min:1', 'max:730'], 'max_listings' => ['required', 'integer', 'min:1', 'max:100']]);
        $p = SellerPackage::create(['name' => $d['name'], 'price' => (int) round(((float) $d['price']) * 100), 'duration_days' => (int) $d['duration_days'], 'max_listings' => (int) $d['max_listings']]);
        Audit::record('sellers', 'package_created', "Created seller package {$p->name}", $p, $actor);

        return back();
    }

    public function assign(Request $request, #[CurrentUser] User $actor): RedirectResponse
    {
        $d = $request->validate(['user_id' => ['required', 'exists:users,id'], 'seller_package_id' => ['required', 'exists:seller_packages,id'], 'starts_on' => ['required', 'date']]);
        $seller = User::findOrFail((int) $d['user_id']);
        abort_unless($seller->isSeller(), 422);
        $package = SellerPackage::findOrFail((int) $d['seller_package_id']);
        $start = CarbonImmutable::parse($d['starts_on']);
        DB::table('seller_package_assignments')->insert(['user_id' => $seller->id, 'seller_package_id' => $package->id, 'starts_on' => $start->toDateString(),
            'expires_on' => $start->addDays($package->duration_days)->toDateString(), 'assigned_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        Audit::record('sellers', 'package_assigned', "Assigned {$package->name} to {$seller->name}", $seller, $actor);

        return back();
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'listing';
        $slug = $base;
        for ($i = 2; Listing::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
