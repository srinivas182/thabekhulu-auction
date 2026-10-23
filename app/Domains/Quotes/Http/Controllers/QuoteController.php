<?php

namespace App\Domains\Quotes\Http\Controllers;

use App\Domains\Audit\Support\Audit;
use App\Domains\Listings\Models\Listing;
use App\Domains\Quotes\Actions\SaveQuote;
use App\Domains\Quotes\Models\CatalogueItem;
use App\Domains\Quotes\Models\Client;
use App\Domains\Quotes\Models\Quote;
use App\Domains\Quotes\Models\QuoteLine;
use App\Domains\Quotes\Models\QuoteMilestone;
use App\Domains\Quotes\Models\QuotePayment;
use App\Domains\Quotes\Models\QuoteTemplate;
use App\Domains\Quotes\Settings\QuotationSettings;
use App\Domains\Quotes\Support\QuoteNumber;
use App\Http\Controllers\Controller;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class QuoteController extends Controller
{
    public function index(Request $request, #[CurrentUser] User $user): Response
    {
        $status = $request->string('status')->toString() ?: null;
        $q = $request->string('q')->toString() ?: null;

        $quotes = $this->visible($user)->with('client:id,name,company')
            ->when($status, fn (Builder $b, string $s) => $b->where('status', $s))
            ->when($q, fn (Builder $b, string $term) => $b->where(fn (Builder $w) => $w->where('number', 'like', "%{$term}%")->orWhere('title', 'like', "%{$term}%")
                ->orWhereHas('client', fn (Builder $c) => $c->where('name', 'like', "%{$term}%")->orWhere('company', 'like', "%{$term}%"))))
            ->latest('updated_at')->paginate(25)->withQueryString()
            ->through(fn (Quote $quote): array => [
                'ulid' => $quote->ulid, 'reference' => $quote->reference(), 'title' => $quote->title,
                'client' => $quote->client->company ?: $quote->client->name, 'status' => $quote->status,
                'statusLabel' => Quote::STATUSES[$quote->status] ?? $quote->status, 'total' => $quote->once_total,
                'validUntil' => $quote->valid_until->toDateString(),
            ]);

        return Inertia::render('admin/quotes/index', [
            'quotes' => $quotes,
            'filters' => ['status' => $status, 'q' => $q],
            'statuses' => Quote::STATUSES,
            'clients' => Client::orderBy('name')->get(['id', 'name', 'company']),
            'templates' => QuoteTemplate::orderBy('name')->get(['id', 'name', 'needs_review']),
        ]);
    }

    public function store(Request $request, #[CurrentUser] User $actor, QuotationSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'template_id' => ['nullable', 'integer', 'exists:quote_templates,id'],
        ]);
        $template = isset($data['template_id']) ? QuoteTemplate::query()->whereKey((int) $data['template_id'])->first() : null;
        $content = $template !== null ? $template->content : [];

        $quote = Quote::create([
            'number' => QuoteNumber::next(), 'version' => 1, 'status' => 'draft', 'title' => $data['title'],
            'client_id' => $data['client_id'], 'created_by' => $actor->id, 'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays($settings->validity_days)->toDateString(), 'vat_rate_bps' => $settings->vat_rate_bps,
            'payment_method' => $content['payment_method'] ?? ($settings->bank_details ?: null),
            'scope' => $content['scope'] ?? null, 'assumptions' => $content['assumptions'] ?? null,
            'exclusions' => $content['exclusions'] ?? null, 'terms' => $content['terms'] ?? null,
        ]);
        foreach (['lines', 'milestones', 'payments'] as $relation) {
            foreach ((array) ($content[$relation] ?? []) as $i => $row) {
                $quote->{$relation}()->create(['position' => $i] + (array) $row);
            }
        }

        Audit::record('quotes', 'created', "Created quotation {$quote->number}".($template ? " from template {$template->name}" : ''), $quote, $actor);

        return redirect()->route('admin.quotes.edit', $quote);
    }

    public function edit(Quote $quote, #[CurrentUser] User $user): Response
    {
        $this->authorizeView($quote, $user);
        $quote->load(['lines', 'milestones', 'payments', 'client', 'creator:id,first_name,last_name']);

        return Inertia::render('admin/quotes/edit', [
            'quote' => [
                'ulid' => $quote->ulid, 'reference' => $quote->reference(), 'status' => $quote->status,
                'statusLabel' => Quote::STATUSES[$quote->status] ?? $quote->status, 'editable' => $quote->isEditable(),
                'title' => $quote->title, 'client_id' => $quote->client_id, 'listing_id' => $quote->listing_id,
                'issue_date' => $quote->issue_date->toDateString(), 'valid_until' => $quote->valid_until->toDateString(),
                'vatRate' => $quote->vat_rate_bps / 100, 'payment_method' => $quote->payment_method,
                'scope' => $quote->scope, 'assumptions' => $quote->assumptions, 'exclusions' => $quote->exclusions,
                'terms' => $quote->terms, 'internal_notes' => $quote->internal_notes, 'createdBy' => $quote->creator?->name,
                'lines' => $quote->lines->map(fn (QuoteLine $l): array => [
                    'kind' => $l->kind, 'frequency' => $l->frequency, 'description' => $l->description,
                    'quantity' => $l->quantity / 100, 'unit' => $l->unit, 'unit_price' => $l->unit_price / 100,
                    'discount_type' => $l->discount_type,
                    'discount_value' => $l->discount_type === 'percent' ? $l->discount_value / 100 : $l->discount_value / 100,
                    'vat_applicable' => $l->vat_applicable,
                ]),
                'milestones' => $quote->milestones->map(fn (QuoteMilestone $m): array => [
                    'name' => $m->name, 'starts_on' => $m->starts_on?->toDateString(), 'ends_on' => $m->ends_on?->toDateString(), 'description' => $m->description,
                ]),
                'payments' => $quote->payments->map(fn (QuotePayment $p): array => ['label' => $p->label, 'percent' => $p->percent_bps / 100, 'due' => $p->due]),
                'totals' => $quote->only(['once_subtotal', 'once_discount', 'once_vat', 'once_total', 'monthly_total', 'annual_total']),
            ],
            'clients' => Client::orderBy('name')->get(['id', 'name', 'company']),
            'catalogue' => CatalogueItem::where('is_active', true)->orderBy('name')->get()->map(fn (CatalogueItem $c): array => [
                'id' => $c->id, 'name' => $c->name, 'description' => $c->description, 'unit' => $c->unit,
                'unit_price' => $c->unit_price / 100, 'vat_applicable' => $c->vat_applicable, 'frequency' => $c->frequency,
            ]),
            'listings' => Listing::orderBy('title')->get(['id', 'title']),
            'can' => ['templates' => Gate::allows('quotes.settings')],
        ]);
    }

    public function update(Request $request, Quote $quote, #[CurrentUser] User $user, SaveQuote $save): RedirectResponse
    {
        $this->authorizeView($quote, $user);
        $save->handle($user, $quote, $request->all());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quotation saved.')]);

        return back();
    }

    public function pdf(Quote $quote, #[CurrentUser] User $user, QuotationSettings $settings): HttpResponse
    {
        $this->authorizeView($quote, $user);
        Audit::record('quotes', 'pdf_downloaded', "Downloaded PDF of {$quote->reference()}", $quote, $user);

        return $this->renderPdf($quote, $settings)->stream("{$quote->reference()}.pdf");
    }

    public function destroy(Quote $quote, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeView($quote, $user);
        abort_unless($quote->status === 'draft' && $quote->version === 1, 403, __('Only unsent first drafts can be deleted.'));

        $reference = $quote->reference();
        $quote->delete();
        Audit::record('quotes', 'deleted', "Deleted draft quotation {$reference}", null, $user);

        return redirect()->route('admin.quotes.index');
    }

    public function saveAsTemplate(Request $request, Quote $quote, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeView($quote, $user);
        $name = $request->validate(['name' => ['required', 'string', 'max:120']])['name'];
        $quote->load(['lines', 'milestones', 'payments']);

        QuoteTemplate::create(['name' => $name, 'needs_review' => false, 'content' => [
            'payment_method' => $quote->payment_method, 'scope' => $quote->scope, 'assumptions' => $quote->assumptions,
            'exclusions' => $quote->exclusions, 'terms' => $quote->terms,
            'lines' => $quote->lines->map(fn (QuoteLine $l) => $l->only(['kind', 'frequency', 'description', 'quantity', 'unit', 'unit_price', 'discount_type', 'discount_value', 'vat_applicable', 'net', 'vat']))->all(),
            'milestones' => $quote->milestones->map(fn (QuoteMilestone $m) => $m->only(['name', 'description']))->all(),
            'payments' => $quote->payments->map(fn (QuotePayment $p) => $p->only(['label', 'percent_bps', 'due']))->all(),
        ]]);

        Audit::record('quotes', 'template_saved', "Saved template {$name} from {$quote->reference()}", null, $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template saved.')]);

        return back();
    }

    public function renderPdf(Quote $quote, QuotationSettings $settings): \Barryvdh\DomPDF\PDF
    {
        $quote->load(['lines', 'milestones', 'payments', 'client', 'listing']);

        return Pdf::loadView('pdf.quote', ['quote' => $quote, 'company' => $settings])->setPaper('a4');
    }

    /**
     * @return Builder<Quote>
     */
    private function visible(User $user): Builder
    {
        return Gate::allows('quotes.view_all') ? Quote::query() : Quote::query()->where('created_by', $user->id);
    }

    private function authorizeView(Quote $quote, User $user): void
    {
        abort_unless(Gate::allows('quotes.view_all') || $quote->created_by === $user->id, 403);
    }
}
