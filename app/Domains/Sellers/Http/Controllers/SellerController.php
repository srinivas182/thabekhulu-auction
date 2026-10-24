<?php

namespace App\Domains\Sellers\Http\Controllers;

use App\Domains\Audit\Support\Audit;
use App\Domains\Bidding\Models\AuctionState;
use App\Domains\Identity\Enums\UserType;
use App\Domains\Leads\Models\Lead;
use App\Domains\Listings\Models\Listing;
use App\Domains\Sellers\Support\SellerPackages;
use App\Domains\Settlement\Models\Payout;
use App\Domains\Settlement\Models\SettlementCase;
use App\Domains\Verification\Models\Verification;
use App\Domains\Verification\Support\SecureFiles;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/** Seller accounts (Sprint 8a). All routes sit behind `feature:sellers`, off in Phase 1. */
class SellerController extends Controller
{
    public const DOC_KINDS = ['title_deed' => 'Title deed', 'mining_right' => 'Mining or prospecting right', 'authority' => 'Proof of authority to sell (e.g. board resolution)', 'other' => 'Other'];

    public function showRegister(): Response
    {
        return Inertia::render('seller/register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'], 'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:190', 'unique:users,email'], 'mobile' => ['required', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::defaults()], 'popia' => ['accepted'],
        ], ['popia.accepted' => __('Please agree to the privacy notice to register.')]);

        $user = User::query()->forceCreate([
            'type' => UserType::Seller, 'first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'email' => strtolower($data['email']),
            'mobile' => $data['mobile'], 'password' => $data['password'], 'popia_consent_at' => now(),
        ]);
        event(new Registered($user));
        Auth::login($user);
        Audit::record('sellers', 'registered', "Seller {$user->name} registered", $user, $user);

        return redirect()->route('seller.dashboard');
    }

    public function dashboard(#[CurrentUser] User $user): Response
    {
        abort_unless($user->isSeller(), 403);
        $tz = (string) config('platform.display_timezone');
        $verification = Verification::where('user_id', $user->id)->latest('id')->first();

        return Inertia::render('seller/dashboard', [
            'verification' => $verification === null ? 'Not started' : (Verification::STATUSES[$verification->status] ?? $verification->status),
            'packageExpires' => SellerPackages::expiryFor($user->id)?->format('j F Y'),
            'submissions' => Lead::where('user_id', $user->id)->latest()->get()->map(fn (Lead $l) => [
                'ulid' => $l->ulid, 'property' => "{$l->property_type}, {$l->location}", 'status' => $l->status,
                'submitted' => $l->created_at?->setTimezone($tz)->format('j M Y'), 'documents' => DB::table('lead_documents')->where('lead_id', $l->id)->count(),
            ]),
            'listings' => Listing::where('seller_user_id', $user->id)->latest()->get()->map(function (Listing $l) {
                $result = DB::table('auction_results')->where('listing_id', $l->id)->first();
                $case = SettlementCase::where('listing_id', $l->id)->whereIn('status', ['open', 'completed'])->latest('id')->first();
                $payout = $case ? Payout::where('settlement_case_id', $case->id)->value('status') : null;

                return [
                    'title' => $l->title, 'status' => $l->status->label(), 'bids' => (int) (AuctionState::whereKey($l->id)->value('bid_count') ?? 0),
                    'result' => $result ? ($result->outcome === 'sold' ? 'Sold for R '.number_format((int) $result->winning_amount, 0, '.', ' ') : 'No bids') : null,
                    'payout' => $payout ? ['prepared' => 'Being prepared', 'approved' => 'Approved', 'paid' => 'Paid'][$payout] ?? $payout : null,
                ];
            }),
            'docKinds' => self::DOC_KINDS,
        ]);
    }

    public function submit(Request $request, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless($user->isSeller(), 403);
        $data = $request->validate([
            'property_type' => ['required', 'string', 'max:40'], 'location' => ['required', 'string', 'max:120'],
            'asking_price' => ['nullable', 'integer', 'min:1', 'max:10000000000'], 'description' => ['required', 'string', 'max:5000'],
            'documents' => ['array', 'max:10'], 'documents.*.kind' => ['required', 'in:'.implode(',', array_keys(self::DOC_KINDS))],
            'documents.*.file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $lead = Lead::create([
            'user_id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'mobile' => (string) $user->mobile,
            'property_type' => $data['property_type'], 'location' => $data['location'], 'asking_price' => $data['asking_price'] ?? null,
            'description' => strip_tags($data['description']), 'status' => 'new', 'consent_at' => now(), 'ip_address' => $request->ip(),
        ]);
        foreach (array_keys($data['documents'] ?? []) as $i) {
            $file = $request->file("documents.{$i}.file");
            if (! $file instanceof UploadedFile) {
                continue;
            }
            DB::table('lead_documents')->insert(['lead_id' => $lead->id, 'kind' => $data['documents'][$i]['kind'], 'path' => SecureFiles::put($file, "leads/{$lead->ulid}"),
                'mime' => (string) $file->getMimeType(), 'created_at' => now(), 'updated_at' => now()]);
        }
        Audit::record('sellers', 'submitted', "Seller {$user->name} submitted {$lead->property_type} in {$lead->location}", $lead, $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Thank you. Our team will review your property and contact you.')]);

        return back();
    }
}
