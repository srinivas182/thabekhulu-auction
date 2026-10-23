<?php

namespace App\Domains\Verification\Http\Controllers;

use App\Domains\Audit\Support\Audit;
use App\Domains\Listings\Models\Listing;
use App\Domains\Listings\Public\PublicListing;
use App\Domains\Verification\Models\BidderApproval;
use App\Domains\Verification\Models\BiddingEntity;
use App\Domains\Verification\Models\EntityDocument;
use App\Domains\Verification\Models\ProofOfFunds;
use App\Domains\Verification\Notifications\BidderUpdate;
use App\Domains\Verification\Support\BidderEligibility;
use App\Domains\Verification\Support\SecureFiles;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff side: company/trust verification (kyc.approve_company), proof of
 * funds (kyc.approve) and per-listing bidder approval (bidders.approve).
 */
class BidderApprovalController extends Controller
{
    public function __construct(private readonly BidderEligibility $eligibility) {}

    public function bidders(Listing $listing): Response
    {
        $approvals = BidderApproval::with(['user:id,first_name,last_name,email', 'entity', 'proofOfFunds', 'listing.category'])
            ->where('listing_id', $listing->id)->orderByRaw("case status when 'pending' then 0 when 'approved' then 1 else 2 end")->latest()->get();

        return Inertia::render('admin/listings/bidders', [
            'listing' => ['ulid' => $listing->ulid, 'title' => $listing->title, 'needsFunds' => $this->eligibility->needsProofOfFunds($listing), 'url' => $listing->status->isPublic() ? PublicListing::url($listing) : null],
            'approvals' => $approvals->map(fn (BidderApproval $a): array => [
                'id' => $a->id, 'name' => $a->user->name, 'email' => $a->user->email, 'biddingAs' => $a->entity?->name, 'status' => $a->status,
                'statusLabel' => BidderApproval::STATUSES[$a->status], 'bidderNumber' => $a->bidder_number, 'reason' => $a->reason,
                'appliedAt' => $a->created_at?->toIso8601String(),
                'checklist' => $a->status === 'pending' ? $this->eligibility->checklist($a) : ($a->checklist ?? []),
                'fundsId' => $a->proof_of_funds_id,
            ]),
            'can' => ['approve' => Gate::allows('bidders.approve')],
        ]);
    }

    public function decide(Request $request, BidderApproval $approval, #[CurrentUser] User $actor): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject', 'revoke'])],
            'reason' => ['nullable', 'required_unless:decision,approve', 'string', 'max:500'],
        ]);
        $approval->load(['listing.category', 'user']);
        $listing = $approval->listing;

        if ($data['decision'] === 'approve') {
            if ($approval->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => __('Only pending applications can be approved.')]);
            }
            $checklist = $this->eligibility->checklist($approval);
            if (! BidderEligibility::passes($checklist)) {
                throw ValidationException::withMessages(['decision' => __('Every required item must be complete before approving.')]);
            }
            $approval->fill(['status' => 'approved', 'checklist' => $checklist, 'bidder_number' => $this->bidderNumber($listing)]);
            $line = __('You’re approved to bid on :title as bidder #:number.', ['title' => $listing->title, 'number' => $approval->bidder_number]);
        } elseif ($data['decision'] === 'reject') {
            abort_unless($approval->status === 'pending', 422);
            $approval->fill(['status' => 'rejected', 'reason' => $data['reason'], 'checklist' => $this->eligibility->checklist($approval)]);
            $line = __('Your application to bid on :title was not approved: :reason', ['title' => $listing->title, 'reason' => $data['reason']]);
        } else {
            abort_unless($approval->status === 'approved', 422);
            $approval->fill(['status' => 'revoked', 'reason' => $data['reason']]);
            $line = __('Your approval to bid on :title has been withdrawn: :reason', ['title' => $listing->title, 'reason' => $data['reason']]);
        }

        $approval->fill(['decided_by' => $actor->id, 'decided_at' => now()])->save();
        Audit::record('bidders', $data['decision'], ucfirst($data['decision'])."d {$approval->user->name} for {$listing->title}", $approval, $actor, ['reason' => $data['reason'] ?? null, 'bidder_number' => $approval->bidder_number]);
        $approval->user->notify(new BidderUpdate($data['decision'], __('Bidding on :title', ['title' => $listing->title]), $line, route('account.approvals')));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved; the buyer has been emailed.')]);

        return back();
    }

    public function entity(BiddingEntity $entity): Response
    {
        $entity->load(['user', 'documents']);

        return Inertia::render('admin/kyc/entity', [
            'entity' => [
                'ulid' => $entity->ulid, 'type' => $entity->type, 'name' => $entity->name, 'registration' => $entity->registration_number,
                'status' => $entity->status, 'rejectionReason' => $entity->rejection_reason, 'controllers' => $entity->controllers,
                'person' => $entity->user->name, 'email' => $entity->user->email,
                'documents' => $entity->documents->map(fn (EntityDocument $d) => [
                    'id' => $d->id, 'kind' => BiddingEntity::DOCUMENTS[$entity->type][$d->kind] ?? $d->kind, 'mime' => $d->mime,
                    'url' => route('admin.kyc.entity-document', [$entity, $d]),
                ]),
            ],
            'can' => ['decide' => Gate::allows('kyc.approve_company')],
        ]);
    }

    public function entityDocument(BiddingEntity $entity, EntityDocument $document, #[CurrentUser] User $actor): HttpResponse
    {
        abort_unless($document->bidding_entity_id === $entity->id, 404);
        Audit::record('kyc', 'entity_document_viewed', "Opened {$document->kind} of {$entity->name}", $entity, $actor);

        return $this->inline(SecureFiles::read($document->path), $document->mime);
    }

    public function decideEntity(Request $request, BiddingEntity $entity, #[CurrentUser] User $actor): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['verify', 'reject'])], 'reason' => ['nullable', 'required_if:decision,reject', 'string', 'max:500']]);
        abort_unless($entity->status === 'pending', 422);

        $entity->forceFill([
            'status' => $data['decision'] === 'verify' ? 'verified' : 'rejected', 'rejection_reason' => $data['reason'] ?? null,
            'reviewed_by' => $actor->id, 'reviewed_at' => now(),
        ])->save();

        Audit::record('kyc', "entity_{$data['decision']}", ($data['decision'] === 'verify' ? 'Verified ' : 'Rejected ').$entity->name, $entity, $actor, ['reason' => $data['reason'] ?? null]);
        $entity->user->notify(new BidderUpdate('entity', $entity->name, $data['decision'] === 'verify'
            ? __(':name is verified; you can now bid for it.', ['name' => $entity->name])
            : __('We couldn’t verify :name: :reason', ['name' => $entity->name, 'reason' => $data['reason']]), route('account.entities')));

        return $this->done('Saved; the buyer has been emailed.');
    }

    public function fundsDocument(ProofOfFunds $funds, #[CurrentUser] User $actor): HttpResponse
    {
        Audit::record('kyc', 'funds_document_viewed', "Opened proof of funds of {$funds->user->name}", $funds, $actor);

        return $this->inline(SecureFiles::read($funds->path), $funds->mime);
    }

    public function decideFunds(Request $request, ProofOfFunds $funds, #[CurrentUser] User $actor): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['accept', 'reject'])],
            'amount' => ['nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'required_if:decision,reject', 'string', 'max:500'],
        ]);
        abort_unless($funds->status === 'pending', 422);

        $funds->forceFill([
            'status' => $data['decision'] === 'accept' ? 'accepted' : 'rejected',
            'amount' => isset($data['amount']) ? (int) $data['amount'] : $funds->amount, // the officer records the amount actually shown
            'valid_until' => $data['decision'] === 'accept' ? Carbon::today()->addDays(ProofOfFunds::VALID_DAYS) : null,
            'note' => $data['note'] ?? null, 'reviewed_by' => $actor->id, 'reviewed_at' => now(),
        ])->save();

        Audit::record('kyc', "funds_{$data['decision']}", ucfirst($data['decision'])."ed proof of funds of {$funds->user->name}", $funds, $actor, ['amount' => $funds->amount]);

        return $this->done('Proof of funds saved.');
    }

    /** Random 4-digit number, unique within the listing, so bidders can't be linked across auctions. */
    private function bidderNumber(Listing $listing): string
    {
        do {
            $number = str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (BidderApproval::where('listing_id', $listing->id)->where('bidder_number', $number)->exists());

        return $number;
    }

    private function inline(string $content, string $mime): HttpResponse
    {
        return response($content, 200, ['Content-Type' => $mime, 'Content-Disposition' => 'inline', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __($message)]);

        return back();
    }
}
