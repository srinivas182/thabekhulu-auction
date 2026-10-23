<?php

namespace App\Domains\Verification\Http\Controllers;

use App\Domains\Audit\Support\Audit;
use App\Domains\Listings\Models\Listing;
use App\Domains\Listings\Public\PublicListing;
use App\Domains\Verification\Models\BidderApproval;
use App\Domains\Verification\Models\BiddingEntity;
use App\Domains\Verification\Models\ProofOfFunds;
use App\Domains\Verification\Support\BidderEligibility;
use App\Domains\Verification\Support\SaIdNumber;
use App\Domains\Verification\Support\SecureFiles;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Buyer side: companies and trusts, applying to bid on a listing, and
 * "My bidding approvals".
 */
class BiddingController extends Controller
{
    private const DOC_RULE = ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'];

    public function entities(#[CurrentUser] User $user): Response
    {
        return Inertia::render('account/entities', [
            'entities' => BiddingEntity::where('user_id', $user->id)->latest()->get()->map(fn (BiddingEntity $e): array => [
                'ulid' => $e->ulid, 'type' => $e->type, 'name' => $e->name, 'registration' => $e->registration_number,
                'status' => $e->status, 'rejectionReason' => $e->rejection_reason, 'controllers' => count($e->controllers),
            ]),
            'documents' => BiddingEntity::DOCUMENTS,
        ]);
    }

    public function storeEntity(Request $request, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless($user->isBuyer(), 403);
        $type = $request->input('type') === 'trust' ? 'trust' : 'company';
        $docRules = collect(BiddingEntity::DOCUMENTS[$type])->mapWithKeys(fn ($label, $kind) => ["documents.{$kind}" => ['required', ...self::DOC_RULE]])->all();

        $data = $request->validate([
            'type' => ['required', Rule::in(['company', 'trust'])],
            'name' => ['required', 'string', 'max:160'],
            'registration_number' => ['required', 'string', 'max:40', $type === 'company' ? 'regex:#^\d{4}/\d{6}/\d{2}$#' : 'regex:#^[A-Za-z]{1,3}\s?\d{1,6}/\d{2,4}$#'],
            'controllers' => ['required', 'array', 'min:1', 'max:20'],
            'controllers.*.name' => ['required', 'string', 'max:160'],
            'controllers.*.role' => ['required', 'string', 'max:60'],
            'controllers.*.id_number' => ['required', 'string', 'max:20'],
            'controllers.*.ownership' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ] + $docRules, [
            'registration_number.regex' => $type === 'company' ? __('Use the CIPC format, e.g. 2015/123456/07.') : __('Use the Master’s Office reference, e.g. IT 1234/2015.'),
        ], ['documents.*' => 'document']);

        foreach ($data['controllers'] as $i => $c) {
            $raw = preg_replace('/\D/', '', $c['id_number']) ?? '';
            if (strlen($raw) === 13 && ! SaIdNumber::isValid($raw)) {
                throw ValidationException::withMessages(["controllers.{$i}.id_number" => __('That isn’t a valid South African ID number.')]);
            }
        }
        if (array_sum(array_map(fn ($c) => (float) ($c['ownership'] ?? 0), $data['controllers'])) > 100) {
            throw ValidationException::withMessages(['controllers' => __('Ownership adds up to more than 100%.')]);
        }

        $entity = BiddingEntity::create([
            'user_id' => $user->id, 'type' => $type, 'name' => $data['name'], 'registration_number' => strtoupper(trim($data['registration_number'])),
            'controllers' => array_values(array_map(fn ($c) => ['name' => $c['name'], 'role' => $c['role'], 'id_number' => preg_replace('/\s/', '', $c['id_number']) ?? '', 'ownership' => (float) ($c['ownership'] ?? 0)], $data['controllers'])),
            'status' => 'pending',
        ]);

        foreach (array_keys(BiddingEntity::DOCUMENTS[$type]) as $kind) {
            /** @var UploadedFile $file */
            $file = $request->file("documents.{$kind}");
            $entity->documents()->create(['kind' => $kind, 'path' => SecureFiles::put($file, "entities/{$entity->ulid}"), 'mime' => (string) $file->getMimeType()]);
        }

        Audit::record('kyc', 'entity_submitted', "{$user->name} added {$type} {$entity->name}", $entity, $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Thank you. We’ll verify :name and email you.', ['name' => $entity->name])]);

        return back();
    }

    public function apply(Request $request, Listing $listing, #[CurrentUser] User $user, BidderEligibility $eligibility): RedirectResponse
    {
        abort_unless($user->isBuyer() && $listing->status->isPublic(), 403);
        $needsFunds = $eligibility->needsProofOfFunds($listing);

        $existing = BidderApproval::where('listing_id', $listing->id)->where('user_id', $user->id)->first();
        if ($existing !== null && in_array($existing->status, ['pending', 'approved'], true)) {
            throw ValidationException::withMessages(['apply' => __('You’ve already applied for this listing.')]);
        }

        $reusable = ProofOfFunds::where('user_id', $user->id)->where('status', 'accepted')->latest('id')->get()
            ->first(fn (ProofOfFunds $p) => $p->isValidFor((int) $listing->minimum_price));

        $data = $request->validate([
            'entity' => ['nullable', 'string', Rule::exists('bidding_entities', 'ulid')->where('user_id', $user->id)],
            'funds_amount' => [Rule::requiredIf($needsFunds && $reusable === null), 'nullable', 'integer', 'min:'.(int) $listing->minimum_price],
            'funds' => [Rule::requiredIf($needsFunds && $reusable === null), 'nullable', ...self::DOC_RULE],
        ], [
            'funds_amount.min' => __('Proof of funds must cover at least the minimum bid of R :min.', ['min' => number_format((int) $listing->minimum_price, 0, '.', ' ')]),
            'funds.required' => __('Upload a bank letter or statement showing available funds.'),
        ]);

        $entity = isset($data['entity']) ? BiddingEntity::where('ulid', $data['entity'])->first() : null;
        $proof = $reusable;
        $file = $request->file('funds');
        if ($file instanceof UploadedFile) {
            $proof = ProofOfFunds::create([
                'user_id' => $user->id, 'bidding_entity_id' => $entity?->id, 'amount' => (int) $data['funds_amount'],
                'path' => SecureFiles::put($file, "funds/{$user->id}"), 'mime' => (string) $file->getMimeType(), 'status' => 'pending',
            ]);
        }

        $approval = BidderApproval::updateOrCreate(['listing_id' => $listing->id, 'user_id' => $user->id], [
            'bidding_entity_id' => $entity?->id, 'proof_of_funds_id' => $needsFunds ? $proof?->id : null,
            'status' => 'pending', 'reason' => null, 'decided_by' => null, 'decided_at' => null, 'bidder_number' => null,
        ]);

        Audit::record('bidders', 'applied', "{$user->name} applied to bid on {$listing->title}".($entity ? " for {$entity->name}" : ''), $approval, $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Application received. We’ll email you when you’re approved to bid.')]);

        return back();
    }

    public function approvals(#[CurrentUser] User $user, BidderEligibility $eligibility): Response
    {
        $approvals = BidderApproval::with(['listing.category', 'listing.city', 'entity'])->where('user_id', $user->id)->latest()->get();

        return Inertia::render('account/approvals', [
            'approvals' => $approvals->map(fn (BidderApproval $a): array => [
                'listing' => $a->listing->title, 'url' => PublicListing::url($a->listing), 'status' => $a->status,
                'statusLabel' => BidderApproval::STATUSES[$a->status], 'bidderNumber' => $a->bidder_number, 'reason' => $a->reason,
                'biddingAs' => $a->entity?->name,
                // Buyers see what is outstanding, not staff notes.
                'checklist' => $a->status === 'pending' ? array_map(fn ($i) => ['label' => $i['label'], 'state' => $i['state']], $eligibility->checklist($a)) : [],
            ]),
        ]);
    }
}
