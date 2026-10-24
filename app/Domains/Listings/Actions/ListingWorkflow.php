<?php

namespace App\Domains\Listings\Actions;

use App\Domains\Audit\Support\Audit;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Listings\Support\Pricing;
use App\Domains\Sellers\Support\SellerPackages;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Listing status changes (sprint 3 spec 5.5). Every change is stored in
 * the listing's history and the audit log.
 */
class ListingWorkflow
{
    public function submit(User $actor, Listing $listing): void
    {
        $this->expect($listing, [ListingStatus::Draft, ListingStatus::ChangesRequested]);

        $problems = $this->readinessProblems($listing);
        if ($problems !== []) {
            throw ValidationException::withMessages(['listing' => $problems]);
        }

        $this->move($actor, $listing, ListingStatus::InReview, null, ['submitted_at' => now()]);
    }

    public function approve(User $actor, Listing $listing): void
    {
        $this->expect($listing, [ListingStatus::InReview]);

        $problems = $this->readinessProblems($listing);
        if ($problems !== []) {
            throw ValidationException::withMessages(['listing' => $problems]);
        }

        $status = $listing->starts_at !== null && $listing->starts_at->isPast() ? ListingStatus::Live : ListingStatus::Scheduled;

        $this->move($actor, $listing, $status, null, ['approved_by' => $actor->id, 'approved_at' => now(), 'review_note' => null]);
    }

    public function requestChanges(User $actor, Listing $listing, string $note): void
    {
        $this->expect($listing, [ListingStatus::InReview]);

        $this->move($actor, $listing, ListingStatus::ChangesRequested, $note, ['review_note' => $note]);
    }

    public function withdraw(User $actor, Listing $listing, string $reason): void
    {
        $this->expect($listing, [ListingStatus::Scheduled, ListingStatus::Live, ListingStatus::InReview]);

        $this->move($actor, $listing, ListingStatus::Withdrawn, $reason);
    }

    public function reopen(User $actor, Listing $listing): void
    {
        $this->expect($listing, [ListingStatus::Withdrawn]);

        $this->move($actor, $listing, ListingStatus::Draft, 'Reopened for editing');
    }

    /** Called by the scheduler: scheduled listings whose start time has come go live. */
    public function goLive(Listing $listing): void
    {
        if ($listing->status === ListingStatus::Scheduled && $listing->starts_at?->isPast()) {
            $this->move(null, $listing, ListingStatus::Live, 'Auction start time reached');
        }
    }

    /**
     * What still stops a listing being submitted or approved.
     *
     * @return list<string>
     */
    public function readinessProblems(Listing $listing): array
    {
        // PRD: a seller's auction may not end after their package expires (Sprint 8a).
        $packageProblem = $listing->seller_user_id ? SellerPackages::endProblem((int) $listing->seller_user_id, $listing->ends_at) : null;
        if ($packageProblem !== null) {
            return [$packageProblem];
        }

        $problems = [];
        $listing->loadMissing(['category.attributeDefinitions', 'category.documentTypes', 'attributeValues', 'documents']);

        if ($listing->city_id === null) {
            $problems[] = __('Choose the city.');
        }
        if (blank($listing->summary) || blank($listing->description)) {
            $problems[] = __('Add the summary and description.');
        }
        $photos = $listing->getMedia('photos');
        if ($photos->count() < 3) {
            $problems[] = __('Add at least 3 photos.');
        } elseif ($photos->contains(fn ($m) => blank($m->getCustomProperty('alt')))) {
            $problems[] = __('Describe every photo (alt text) for screen readers and search engines.');
        }

        $floor = Pricing::floorFor($listing);
        if ($floor === null || $listing->minimum_price === null) {
            $problems[] = __('Complete the pricing: seller terms and minimum selling price.');
        } elseif ($listing->minimum_price < $floor) {
            $problems[] = __('The minimum selling price is below the allowed minimum.');
        }

        if ($listing->starts_at === null || $listing->ends_at === null || $listing->ends_at->lte($listing->starts_at)) {
            $problems[] = __('Set the auction start and end times.');
        }

        $filled = $listing->attributeValues->filter(fn ($v) => filled($v->value))->pluck('category_attribute_id')->all();
        foreach ($listing->category->attributeDefinitions->where('is_required', true) as $field) {
            if (! in_array($field->id, $filled, true)) {
                $problems[] = __('Fill in “:field”.', ['field' => $field->label]);
            }
        }

        $uploaded = $listing->documents->pluck('document_type_id')->all();
        foreach ($listing->category->documentTypes->where('is_mandatory', true) as $type) {
            if (! in_array($type->id, $uploaded, true)) {
                $problems[] = __('Upload the mandatory document “:doc”.', ['doc' => $type->name]);
            }
        }

        return $problems;
    }

    /**
     * @param  list<ListingStatus>  $allowed
     */
    private function expect(Listing $listing, array $allowed): void
    {
        if (! in_array($listing->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'listing' => __('This can’t be done while the listing is :status.', ['status' => strtolower($listing->status->label())]),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function move(?User $actor, Listing $listing, ListingStatus $to, ?string $note, array $extra = []): void
    {
        $from = $listing->status;

        DB::transaction(function () use ($actor, $listing, $to, $note, $extra, $from): void {
            $listing->forceFill(['status' => $to] + $extra)->save();
            $listing->statusChanges()->create([
                'from' => $from->value, 'to' => $to->value, 'user_id' => $actor?->id, 'note' => $note, 'created_at' => now(),
            ]);
        });

        Audit::record('listings', 'status_changed', "{$listing->title}: {$from->label()} → {$to->label()}", $listing, $actor, [
            'old' => ['status' => $from->value],
            'attributes' => ['status' => $to->value],
            'note' => $note,
        ]);
    }
}
