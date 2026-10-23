<?php

namespace App\Domains\Quotes\Support;

use App\Domains\Quotes\Models\Quote;

/**
 * SHA-256 of everything the client agrees to (decision Q3): number,
 * version, client, dates, every line, schedules, totals and texts. If any
 * of these change, the fingerprint changes and acceptance is refused.
 */
final class QuoteFingerprint
{
    public static function of(Quote $quote): string
    {
        $quote->loadMissing(['lines', 'milestones', 'payments']);

        $content = [
            'number' => $quote->number, 'version' => $quote->version, 'client' => $quote->client_id, 'title' => $quote->title,
            'issue_date' => $quote->issue_date->toDateString(), 'valid_until' => $quote->valid_until->toDateString(),
            'vat' => $quote->vat_rate_bps,
            'lines' => $quote->lines->map(fn ($l) => [$l->kind, $l->frequency, $l->description, $l->quantity, $l->unit, $l->unit_price, $l->discount_type, $l->discount_value, $l->vat_applicable, $l->net, $l->vat])->all(),
            'milestones' => $quote->milestones->map(fn ($m) => [$m->name, $m->starts_on?->toDateString(), $m->ends_on?->toDateString(), $m->description])->all(),
            'payments' => $quote->payments->map(fn ($p) => [$p->label, $p->percent_bps, $p->due])->all(),
            'totals' => [$quote->once_subtotal, $quote->once_discount, $quote->once_vat, $quote->once_total, $quote->monthly_total, $quote->annual_total],
            'texts' => [$quote->payment_method, $quote->scope, $quote->assumptions, $quote->exclusions, $quote->terms],
        ];

        return hash('sha256', (string) json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
