<?php

namespace App\Domains\Quotes\Actions;

use App\Domains\Audit\Support\Audit;
use App\Domains\Listings\Support\HtmlCleaner;
use App\Domains\Quotes\Models\Quote;
use App\Domains\Quotes\Support\QuoteMath;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validates and saves a whole quote. Line amounts and totals are always
 * recalculated here; figures sent by the browser are never trusted.
 */
class SaveQuote
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, Quote $quote, array $input): Quote
    {
        if (! $quote->isEditable()) {
            throw ValidationException::withMessages(['quote' => __('Sent quotes can’t be edited. Create a new version instead.')]);
        }

        $data = Validator::make($input, [
            'title' => ['required', 'string', 'max:160'],
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'listing_id' => ['nullable', 'integer', 'exists:listings,id'],
            'issue_date' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:issue_date'],
            'payment_method' => ['nullable', 'string', 'max:2000'],
            'scope' => ['nullable', 'string', 'max:50000'],
            'assumptions' => ['nullable', 'string', 'max:50000'],
            'exclusions' => ['nullable', 'string', 'max:50000'],
            'terms' => ['nullable', 'string', 'max:50000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['present', 'array', 'max:150'],
            'lines.*.kind' => ['required', Rule::in(['item', 'heading'])],
            'lines.*.frequency' => ['required', Rule::in(['once', 'monthly', 'annual'])],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'lines.*.unit' => ['nullable', 'string', 'max:20'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:100000000000'],
            'lines.*.discount_type' => ['nullable', Rule::in(['percent', 'amount'])],
            'lines.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'lines.*.vat_applicable' => ['boolean'],
            'milestones' => ['present', 'array', 'max:50'],
            'milestones.*.name' => ['required', 'string', 'max:160'],
            'milestones.*.starts_on' => ['nullable', 'date'],
            'milestones.*.ends_on' => ['nullable', 'date'],
            'milestones.*.description' => ['nullable', 'string', 'max:500'],
            'payments' => ['present', 'array', 'max:20'],
            'payments.*.label' => ['required', 'string', 'max:160'],
            'payments.*.percent' => ['required', 'numeric', 'gt:0', 'max:100'],
            'payments.*.due' => ['nullable', 'string', 'max:160'],
        ], [], ['lines.*.description' => 'line description', 'payments.*.percent' => 'payment percentage'])->validate();

        $paymentBps = array_sum(array_map(fn (array $p): int => (int) round(((float) $p['percent']) * 100), $data['payments']));
        if ($data['payments'] !== [] && $paymentBps !== 10000) {
            throw ValidationException::withMessages(['payments' => __('Payment percentages must add up to 100% (now :total%).', ['total' => $paymentBps / 100])]);
        }

        $rate = $quote->vat_rate_bps;
        $lines = [];
        foreach (array_values($data['lines']) as $i => $l) {
            $isItem = $l['kind'] === 'item';
            $qty = $isItem ? (int) round(((float) ($l['quantity'] ?? 1)) * 100) : 0;
            $price = $isItem ? QuoteMath::cents($l['unit_price'] ?? 0) : 0;
            $type = $isItem ? ($l['discount_type'] ?? null) : null;
            $value = match ($type) {
                'percent' => (int) round(min(100, (float) ($l['discount_value'] ?? 0)) * 100),
                'amount' => QuoteMath::cents($l['discount_value'] ?? 0),
                default => 0,
            };
            $vat = $isItem && (bool) ($l['vat_applicable'] ?? true);
            $calc = $isItem ? QuoteMath::line($qty, $price, $type, $value, $vat, $rate) : ['gross' => 0, 'discount' => 0, 'net' => 0, 'vat' => 0];

            $lines[] = [
                'row' => [
                    'position' => $i, 'kind' => $l['kind'], 'frequency' => $isItem ? $l['frequency'] : 'once',
                    'description' => strip_tags((string) $l['description']), 'quantity' => $qty, 'unit' => $l['unit'] ?? null,
                    'unit_price' => $price, 'discount_type' => $type, 'discount_value' => $value, 'vat_applicable' => $vat,
                    'net' => $calc['net'], 'vat' => $calc['vat'],
                ],
                'calc' => $calc + ['frequency' => $l['frequency'], 'kind' => $l['kind']],
            ];
        }

        $totals = QuoteMath::totals(array_column($lines, 'calc'));

        DB::transaction(function () use ($quote, $data, $lines, $totals): void {
            $quote->fill([
                'title' => $data['title'], 'client_id' => $data['client_id'], 'listing_id' => $data['listing_id'] ?? null,
                'issue_date' => Carbon::parse($data['issue_date']), 'valid_until' => Carbon::parse($data['valid_until']),
                'payment_method' => isset($data['payment_method']) ? strip_tags((string) $data['payment_method']) : null,
                'scope' => HtmlCleaner::clean($data['scope'] ?? null), 'assumptions' => HtmlCleaner::clean($data['assumptions'] ?? null),
                'exclusions' => HtmlCleaner::clean($data['exclusions'] ?? null), 'terms' => HtmlCleaner::clean($data['terms'] ?? null),
                'internal_notes' => $data['internal_notes'] ?? null,
            ] + $totals)->save();

            $quote->lines()->delete();
            $quote->lines()->createMany(array_column($lines, 'row'));

            $quote->milestones()->delete();
            $quote->milestones()->createMany(array_map(fn (array $m, int $i): array => [
                'position' => $i, 'name' => $m['name'], 'starts_on' => $m['starts_on'] ?? null, 'ends_on' => $m['ends_on'] ?? null,
                'description' => $m['description'] ?? null,
            ], array_values($data['milestones']), array_keys(array_values($data['milestones']))));

            $quote->payments()->delete();
            $quote->payments()->createMany(array_map(fn (array $p, int $i): array => [
                'position' => $i, 'label' => $p['label'], 'percent_bps' => (int) round(((float) $p['percent']) * 100), 'due' => $p['due'] ?? null,
            ], array_values($data['payments']), array_keys(array_values($data['payments']))));
        });

        Audit::record('quotes', 'saved', "Saved quotation {$quote->reference()}", $quote, $actor, [
            'attributes' => ['once_total' => $totals['once_total'], 'monthly_total' => $totals['monthly_total'], 'annual_total' => $totals['annual_total']],
        ]);

        return $quote;
    }
}
