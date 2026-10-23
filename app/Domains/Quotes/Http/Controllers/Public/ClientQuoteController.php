<?php

namespace App\Domains\Quotes\Http\Controllers\Public;

use App\Domains\Quotes\Actions\QuoteWorkflow;
use App\Domains\Quotes\Http\Controllers\QuoteController;
use App\Domains\Quotes\Models\Quote;
use App\Domains\Quotes\Models\QuoteLine;
use App\Domains\Quotes\Models\QuoteMilestone;
use App\Domains\Quotes\Models\QuotePayment;
use App\Domains\Quotes\Settings\QuotationSettings;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The client's view of a quotation, reached through a signed, expiring
 * link (decision Q2). Internal notes are never included.
 */
class ClientQuoteController extends Controller
{
    public function __construct(private readonly QuoteWorkflow $workflow) {}

    public function show(Request $request, Quote $quote, QuotationSettings $settings): Response
    {
        abort_if(in_array($quote->status, ['draft', 'awaiting_approval', 'approved'], true), 404);

        $user = $request->user();
        if (! ($user instanceof User && $user->isStaff())) {
            $this->workflow->markViewed($quote, $request->ip());
        }

        $quote->load(['lines', 'milestones', 'payments', 'client']);
        $expires = $quote->valid_until->copy()->endOfDay()->addDay();
        $open = in_array($quote->status, ['sent', 'viewed'], true) && ! $quote->valid_until->endOfDay()->isPast();

        return Inertia::render('public/quote', [
            'quote' => [
                'reference' => $quote->reference(), 'title' => $quote->title, 'status' => $quote->status,
                'statusLabel' => Quote::STATUSES[$quote->status] ?? $quote->status, 'open' => $open,
                'client' => $quote->client->company ?: $quote->client->name, 'issueDate' => $quote->issue_date->format('j F Y'),
                'validUntil' => $quote->valid_until->format('j F Y'), 'vatRate' => $quote->vat_rate_bps / 100,
                'scope' => $quote->scope, 'assumptions' => $quote->assumptions, 'exclusions' => $quote->exclusions, 'terms' => $quote->terms,
                'paymentMethod' => $quote->payment_method,
                'lines' => $quote->lines->map(fn (QuoteLine $l): array => [
                    'kind' => $l->kind, 'frequency' => $l->frequency, 'description' => $l->description, 'quantity' => $l->quantity / 100,
                    'unit' => $l->unit, 'unitPrice' => $l->unit_price, 'net' => $l->net, 'vat' => $l->vat,
                ]),
                'milestones' => $quote->milestones->map(fn (QuoteMilestone $m): array => ['name' => $m->name, 'start' => $m->starts_on?->format('j M Y'), 'end' => $m->ends_on?->format('j M Y')]),
                'payments' => $quote->payments->map(fn (QuotePayment $p): array => ['label' => $p->label, 'percent' => $p->percent_bps / 100, 'amount' => intdiv($quote->once_total * $p->percent_bps + 5000, 10000), 'due' => $p->due]),
                'totals' => $quote->only(['once_subtotal', 'once_discount', 'once_vat', 'once_total', 'monthly_total', 'annual_total']),
                'accepted' => $quote->accepted_at ? ['name' => $quote->accepted_name, 'at' => $quote->accepted_at->setTimezone((string) config('platform.display_timezone'))->format('j F Y, H:i'), 'fingerprint' => $quote->fingerprint] : null,
            ],
            'company' => $settings->company_name,
            'links' => [
                'pdf' => URL::temporarySignedRoute('quotes.public.pdf', $expires, ['quote' => $quote->ulid]),
                'accept' => URL::temporarySignedRoute('quotes.public.accept', $expires, ['quote' => $quote->ulid]),
                'decline' => URL::temporarySignedRoute('quotes.public.decline', $expires, ['quote' => $quote->ulid]),
            ],
        ]);
    }

    public function pdf(Quote $quote, QuotationSettings $settings): HttpResponse
    {
        abort_if(in_array($quote->status, ['draft', 'awaiting_approval', 'approved'], true), 404);

        return app(QuoteController::class)->renderPdf($quote, $settings)->download("{$quote->reference()}.pdf");
    }

    public function accept(Request $request, Quote $quote): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:160'],
            'agree' => ['accepted'],
        ], ['agree.accepted' => __('Tick the box to confirm you accept this quotation.')]);

        $this->workflow->accept($quote, strip_tags($data['name']), $request->ip(), $request->userAgent());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Thank you. Your acceptance has been recorded and a copy emailed to you.')]);

        return back();
    }

    public function decline(Request $request, Quote $quote): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:500']])['reason'] ?? null;
        $this->workflow->decline($quote, $reason, $request->ip());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Thank you for letting us know.')]);

        return back();
    }
}
