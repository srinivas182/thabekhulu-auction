<?php

namespace App\Domains\Listings\Http\Controllers;

use App\Domains\Audit\Support\Audit;
use App\Domains\Bidding\Models\AuctionState;
use App\Domains\Bidding\Models\Bid;
use App\Domains\Catalogue\Enums\DepositTerms;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\CategoryAttribute;
use App\Domains\Catalogue\Models\City;
use App\Domains\Listings\Actions\ListingWorkflow;
use App\Domains\Listings\Actions\Relist;
use App\Domains\Listings\Enums\AuctionType;
use App\Domains\Listings\Enums\ComplianceItem;
use App\Domains\Listings\Enums\DealType;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Listings\Models\ListingDocument;
use App\Domains\Listings\Models\ListingStatusChange;
use App\Domains\Listings\Support\HtmlCleaner;
use App\Domains\Listings\Support\Pricing;
use App\Domains\Settings\Settings\AuctionSettings;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ListingController extends Controller
{
    public const SECTIONS = ['details', 'facts', 'pricing', 'auction', 'seo'];

    public function index(Request $request): Response
    {
        $filters = $request->only(['q', 'status', 'category']);

        $listings = Listing::with(['category:id,name', 'city:id,name'])
            // Interest at a glance, in this one query (no extra query per row).
            ->withCount([
                'bidderApprovals as applied_count',
                'bidderApprovals as pending_count' => fn ($q) => $q->where('status', 'pending'),
                'bidderApprovals as approved_count' => fn ($q) => $q->where('status', 'approved'),
            ])
            ->addSelect([
                'bidders_count' => Bid::selectRaw('count(distinct bidder_approval_id)')->whereColumn('listing_id', 'listings.id')->whereNull('voided_at'),
                'highest_bid' => AuctionState::select('current_amount')->whereColumn('listing_id', 'listings.id')->limit(1),
            ])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('title', 'like', "%{$term}%"))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['category'] ?? null, fn ($q, $slug) => $q->whereHas('category', fn ($c) => $c->where('slug', $slug)))
            ->latest('updated_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Listing $l): array => [
                'ulid' => $l->ulid,
                'title' => $l->title,
                'category' => $l->category->name,
                'city' => $l->city?->name,
                'status' => $l->status->value,
                'statusLabel' => $l->status->label(),
                'minimum' => $l->minimum_price,
                'startsAt' => $l->starts_at?->toIso8601String(),
                'endsAt' => $l->ends_at?->toIso8601String(),
                'applied' => (int) $l->getAttribute('applied_count'), 'pending' => (int) $l->getAttribute('pending_count'),
                'approved' => (int) $l->getAttribute('approved_count'), 'bidders' => (int) $l->getAttribute('bidders_count'),
                'highestBid' => $l->getAttribute('highest_bid') !== null ? (int) $l->getAttribute('highest_bid') : null,
                'sealed' => $l->auction_type->value === 'sealed',
            ]);

        return Inertia::render('admin/listings/index', [
            'listings' => $listings,
            'filters' => $filters,
            'categories' => Category::orderBy('sort_order')->get(['id', 'name', 'slug']),
            'statuses' => collect(ListingStatus::cases())->map(fn (ListingStatus $s) => ['value' => $s->value, 'label' => $s->label()]),
            'canCreate' => Gate::allows('listings.create'),
        ]);
    }

    public function store(Request $request, #[CurrentUser] User $actor): RedirectResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:160'],
        ]);
        $category = Category::query()->whereKey((int) $data['category_id'])->firstOrFail();

        $listing = Listing::create([
            'category_id' => $category->id,
            'title' => $data['title'],
            'slug' => $this->uniqueSlug($data['title']),
            'deal_type' => DealType::Commission,
            'fee_bps' => $category->default_fee_bps,
            'deposit_amount' => $category->default_deposit,
            'deposit_terms' => $category->default_deposit_terms,
            'auction_type' => AuctionType::Open,
            'status' => ListingStatus::Draft,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        Audit::record('listings', 'created', "Created listing {$listing->title}", $listing, $actor);

        return redirect()->route('admin.listings.edit', $listing);
    }

    public function edit(Listing $listing, ListingWorkflow $workflow): Response
    {
        $listing->load(['category.attributeDefinitions', 'category.documentTypes', 'attributeValues', 'documents.documentType', 'city', 'updater', 'complianceItems.signer']);
        $floor = Pricing::floorFor($listing);
        $minimum = $listing->minimum_price;
        $values = $listing->attributeValues->pluck('value', 'category_attribute_id');

        return Inertia::render('admin/listings/edit', [
            'listing' => [
                'ulid' => $listing->ulid,
                'title' => $listing->title,
                'slug' => $listing->slug,
                'category' => ['name' => $listing->category->name, 'slug' => $listing->category->slug, 'notice' => $listing->category->requires_compliance_notice],
                'status' => $listing->status->value,
                'statusLabel' => $listing->status->label(),
                'reviewNote' => $listing->review_note,
                'cityId' => $listing->city_id,
                'suburb' => $listing->suburb,
                'address' => $listing->address,
                'latitude' => $listing->latitude,
                'longitude' => $listing->longitude,
                'summary' => $listing->summary,
                'description' => $listing->description,
                'videoUrl' => $listing->video_url,
                'dealType' => $listing->deal_type->value,
                'sellerNetPrice' => $listing->seller_net_price,
                'sellerPrice' => $listing->seller_price,
                'renovationCost' => $listing->renovation_cost,
                'feePercent' => $listing->fee_bps / 100,
                'minimumPrice' => $minimum,
                'depositAmount' => $listing->deposit_amount,
                'depositTerms' => $listing->deposit_terms->value,
                'auctionType' => $listing->auction_type->value,
                'biddingTerms' => $listing->bidding_terms,
                'startsAt' => $this->local($listing->starts_at),
                'endsAt' => $this->local($listing->ends_at),
                'featured' => $listing->is_featured,
                'seoTitle' => $listing->seo_title,
                'seoDescription' => $listing->seo_description,
                'updatedAt' => $listing->updated_at?->toIso8601String(),
                'updatedBy' => $listing->updater?->name,
            ],
            'fields' => $listing->category->attributeDefinitions->map(fn (CategoryAttribute $f): array => [
                'id' => $f->id, 'label' => $f->label, 'type' => $f->type->value, 'unit' => $f->unit,
                'options' => $f->options ?? [], 'required' => $f->is_required, 'value' => $values[$f->id] ?? null,
            ]),
            'photos' => $listing->getMedia('photos')->map(fn (Media $m): array => $this->media($m)),
            'floorPlans' => $listing->getMedia('floor_plans')->map(fn (Media $m): array => $this->media($m)),
            'documentTypes' => $listing->category->documentTypes->map(fn ($t): array => [
                'id' => $t->id, 'name' => $t->name, 'mandatory' => $t->is_mandatory, 'visibility' => $t->visibility->label(),
                'formats' => $t->accepted_formats, 'maxSizeMb' => $t->max_size_mb,
                'files' => $listing->documents->where('document_type_id', $t->id)->values()->map(fn (ListingDocument $d): array => [
                    'id' => $d->id, 'name' => $d->original_name, 'size' => $d->size, 'uploadedAt' => $d->created_at?->toIso8601String(),
                ]),
            ]),
            'compliance' => collect(ComplianceItem::cases())->map(function (ComplianceItem $item) use ($listing): array {
                $done = $listing->complianceItems->firstWhere('item', $item->value);

                return [
                    'item' => $item->value, 'label' => $item->label(),
                    'signedBy' => $done?->signer->name, 'signedAt' => $done?->signed_off_at->toIso8601String(), 'note' => $done?->note,
                    'attachments' => DB::table('listing_compliance_attachments')->where('listing_id', $listing->id)->where('item', $item->value)->orderBy('id')->get()
                        ->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'url' => route('admin.listings.compliance.file', [$listing, $a->id])])->values(),
                ];
            }),
            'pricing' => [
                'floor' => $floor,
                'payoutAtMinimum' => $minimum ? Pricing::payout($listing, $minimum) : null,
                'deposit' => Pricing::depositCheck($listing->deposit_amount, $minimum),
            ],
            'readiness' => $workflow->readinessProblems($listing),
            'history' => $listing->statusChanges()->with('user:id,first_name,last_name')->limit(20)->get()->map(fn (ListingStatusChange $c): array => [
                'from' => $c->from, 'to' => $c->to, 'by' => $c->user !== null ? $c->user->name : 'System', 'note' => $c->note, 'at' => $c->created_at->toIso8601String(),
            ]),
            'cities' => City::with('province:id,name')->where('is_active', true)->orderBy('name')->get()->map(fn (City $c): array => [
                'id' => $c->id, 'name' => "{$c->name}, {$c->province->name}",
            ]),
            'options' => [
                'dealTypes' => collect(DealType::cases())->map(fn (DealType $d) => ['value' => $d->value, 'label' => $d->label()]),
                'auctionTypes' => collect(AuctionType::cases())->map(fn (AuctionType $d) => ['value' => $d->value, 'label' => $d->label()]),
                'depositTerms' => collect(DepositTerms::cases())->map(fn (DepositTerms $d) => ['value' => $d->value, 'label' => $d->label()]),
                'durationDays' => app(AuctionSettings::class)->default_duration_days,
            ],
            'can' => [
                'edit' => $this->canEdit($listing, 'details'),
                'editPricing' => $this->canEdit($listing, 'pricing'),
                'submit' => Gate::allows('listings.submit'),
                'publish' => Gate::allows('listings.publish'),
                'signOff' => Gate::allows('compliance.signoff'),
                'delete' => Gate::allows('listings.delete') && $listing->status === ListingStatus::Draft,
            ],
        ]);
    }

    public function update(Request $request, Listing $listing, string $section, #[CurrentUser] User $actor): RedirectResponse
    {
        abort_unless(in_array($section, self::SECTIONS, true), 404);
        abort_unless($this->canEdit($listing, $section), 403, __('This part of the listing is locked.'));

        $reason = null;
        if (in_array($section, ['pricing', 'auction'], true) && $listing->status === ListingStatus::Live) {
            $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];
        }

        $before = $listing->only(['title', 'city_id', 'suburb', 'deal_type', 'seller_net_price', 'seller_price', 'renovation_cost', 'fee_bps', 'minimum_price', 'deposit_amount', 'deposit_terms', 'auction_type', 'starts_at', 'ends_at']);

        match ($section) {
            'details' => $this->saveDetails($request, $listing),
            'facts' => $this->saveFacts($request, $listing),
            'pricing' => $this->savePricing($request, $listing),
            'auction' => $this->saveAuction($request, $listing),
            'seo' => $listing->fill($request->validate([
                'seo_title' => ['nullable', 'string', 'max:120'],
                'seo_description' => ['nullable', 'string', 'max:300'],
                'is_featured' => ['boolean'],
            ])),
        };

        $listing->updated_by = $actor->id;
        $changes = array_diff_key($listing->getDirty(), array_flip(['updated_by', 'description', 'summary']));
        $listing->save();
        $listing->touch();

        Audit::record('listings', 'updated', "Updated {$section} of {$listing->title}", $listing, $actor, [
            'section' => $section,
            'old' => array_intersect_key($before, $changes),
            'attributes' => $changes,
            'reason' => $reason,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved.')]);

        return back();
    }

    public function destroy(Listing $listing, #[CurrentUser] User $actor): RedirectResponse
    {
        abort_unless($listing->status === ListingStatus::Draft, 403, __('Only drafts can be deleted.'));

        $title = $listing->title;
        $listing->delete();

        Audit::record('listings', 'deleted', "Deleted draft listing {$title}", null, $actor);

        return redirect()->route('admin.listings.index');
    }

    private function saveDetails(Request $request, Listing $listing): void
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')->where('is_active', true)],
            'suburb' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-35,-22'],
            'longitude' => ['nullable', 'numeric', 'between:16,33'],
            'summary' => ['nullable', 'string', 'max:600'],
            'description' => ['nullable', 'string', 'max:100000'],
            'video_url' => ['nullable', 'url', 'max:255', 'regex:#^https://(www\.)?(youtube\.com|youtu\.be|vimeo\.com)/#'],
        ], ['video_url.regex' => __('Use a YouTube or Vimeo link.')]);

        $data['description'] = HtmlCleaner::clean($data['description'] ?? null);
        $listing->fill($data);
    }

    private function saveFacts(Request $request, Listing $listing): void
    {
        $fields = $listing->category->attributeDefinitions;
        $input = (array) $request->input('facts', []);
        $errors = [];

        foreach ($fields as $field) {
            $value = $input[$field->id] ?? null;
            $value = is_string($value) ? trim($value) : $value;

            if ($value === null || $value === '') {
                $listing->attributeValues()->where('category_attribute_id', $field->id)->delete();

                continue;
            }

            $valid = match ($field->type->value) {
                'number' => is_numeric($value),
                'select' => in_array($value, $field->options ?? [], true),
                'boolean' => in_array($value, ['yes', 'no', '1', '0', true, false], true),
                'date' => strtotime((string) $value) !== false,
                default => mb_strlen((string) $value) <= 500,
            };

            if (! $valid) {
                $errors["facts.{$field->id}"] = __('Check “:field”.', ['field' => $field->label]);

                continue;
            }

            if ($field->type->value === 'boolean') {
                $value = in_array($value, ['yes', '1', true], true) ? 'yes' : 'no';
            }

            $listing->attributeValues()->updateOrCreate(['category_attribute_id' => $field->id], ['value' => (string) $value]);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function savePricing(Request $request, Listing $listing): void
    {
        $data = $request->validate([
            'deal_type' => ['required', Rule::enum(DealType::class)],
            'seller_net_price' => ['nullable', 'required_if:deal_type,commission', 'integer', 'min:1'],
            'seller_price' => ['nullable', 'required_if:deal_type,renovated', 'integer', 'min:1'],
            'renovation_cost' => ['nullable', 'integer', 'min:0'],
            'fee_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'minimum_price' => ['required', 'integer', 'min:1'],
            'deposit_amount' => ['required', 'integer', 'min:0'],
            'deposit_terms' => ['required', Rule::enum(DepositTerms::class)],
        ]);

        $deal = DealType::from($data['deal_type']);
        $feeBps = (int) round(((float) $data['fee_percent']) * 100);
        $floor = Pricing::floor($deal, $data['seller_net_price'] ?? null, $data['seller_price'] ?? null, (int) ($data['renovation_cost'] ?? 0), $feeBps);

        if ($floor !== null && (int) $data['minimum_price'] < $floor) {
            throw ValidationException::withMessages([
                'minimum_price' => __('Can’t be below R :floor.', ['floor' => number_format($floor, 0, '.', ' ')]),
            ]);
        }

        $listing->fill([
            'deal_type' => $deal,
            'seller_net_price' => $deal === DealType::Commission ? $data['seller_net_price'] : null,
            'seller_price' => $deal === DealType::Renovated ? $data['seller_price'] : null,
            'renovation_cost' => (int) ($data['renovation_cost'] ?? 0),
            'fee_bps' => $feeBps,
            'minimum_price' => (int) $data['minimum_price'],
            'deposit_amount' => (int) $data['deposit_amount'],
            'deposit_terms' => $data['deposit_terms'],
        ]);
    }

    private function saveAuction(Request $request, Listing $listing): void
    {
        $data = $request->validate([
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'auction_type' => ['required', Rule::enum(AuctionType::class)],
            'bidding_terms' => ['nullable', 'string', 'max:20000'],
        ]);

        $tz = (string) config('platform.display_timezone');
        $start = filled($data['starts_at'] ?? null) ? Carbon::parse($data['starts_at'], $tz)->utc() : null;
        $end = filled($data['ends_at'] ?? null)
            ? Carbon::parse($data['ends_at'], $tz)->utc()
            : $start?->copy()->addDays(app(AuctionSettings::class)->default_duration_days);

        $listing->fill([
            'starts_at' => $start,
            'ends_at' => $end,
            'auction_type' => $data['auction_type'],
            'bidding_terms' => HtmlCleaner::clean($data['bidding_terms'] ?? null),
        ]);
    }

    /**
     * Content is edited in Draft or Changes requested; after that only
     * reviewers can change content, and pricing and auction times lock
     * once live except for super admins (who must give a reason).
     */
    private function canEdit(Listing $listing, string $section): bool
    {
        $user = request()->user();
        if (! $user instanceof User || ! Gate::allows('listings.edit')) {
            return false;
        }

        if (in_array($section, ['pricing', 'auction'], true)) {
            return match ($listing->status) {
                ListingStatus::Draft, ListingStatus::ChangesRequested => true,
                ListingStatus::InReview, ListingStatus::Scheduled => Gate::allows('listings.publish'),
                ListingStatus::Live => $user->isSuperAdmin(),
                // After the close, pricing and times are part of the auction record.
                ListingStatus::Withdrawn, ListingStatus::Closed, ListingStatus::UnderOffer, ListingStatus::Sold, ListingStatus::NotSold, ListingStatus::NoBids => false,
            };
        }

        return $listing->status->isEditable() || Gate::allows('listings.publish');
    }

    /** @return array{id: int, url: string, alt: string, name: string} */
    private function media(Media $m): array
    {
        return [
            'id' => $m->id,
            'url' => $m->hasGeneratedConversion('thumb') ? $m->getUrl('thumb') : $m->getUrl(),
            'alt' => (string) $m->getCustomProperty('alt', ''),
            'name' => $m->file_name,
        ];
    }

    private function local(?CarbonInterface $time): ?string
    {
        return $time?->copy()->setTimezone((string) config('platform.display_timezone'))->format('Y-m-d\TH:i');
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title), 160, '') ?: 'listing';
        $slug = $base;
        $n = 2;
        while (Listing::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }

    public function relist(Listing $listing, Relist $relist, #[CurrentUser] User $actor): RedirectResponse
    {
        $relist->handle($listing, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Relisted as a draft. Set new dates and send it for review.')]);

        return back();
    }
}
