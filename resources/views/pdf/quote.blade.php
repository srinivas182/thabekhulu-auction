@php
    $money = fn (int $cents): string => 'R ' . number_format($cents / 100, 2, '.', ' ');
    $qty = fn (int $h): string => rtrim(rtrim(number_format($h / 100, 2, '.', ' '), '0'), '.');
    $date = fn ($d) => $d?->format('j F Y');
    $theme = \App\Domains\Theme\Support\ThemeRepository::published('website');
    $primary = $theme['light']['primary'];
    $accent = $theme['light']['accent'];
    $once = $quote->lines->where('frequency', 'once');
    $recurring = $quote->lines->where('kind', 'item')->whereIn('frequency', ['monthly', 'annual']);
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $quote->reference() }}</title>
<style>
    @page { margin: 28mm 16mm 22mm 16mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #14201c; line-height: 1.45; }
    header { position: fixed; top: -20mm; left: 0; right: 0; height: 14mm; border-bottom: 2px solid {{ $primary }}; }
    footer { position: fixed; bottom: -14mm; left: 0; right: 0; font-size: 7.5pt; color: #5e6e68; border-top: 1px solid #d5dbd7; padding-top: 2mm; }
    .brand { font-family: 'DejaVu Serif', serif; font-size: 18pt; color: {{ $primary }}; }
    .muted { color: #5e6e68; }
    h1 { font-family: 'DejaVu Serif', serif; font-weight: normal; font-size: 18pt; margin: 0 0 2mm; }
    h2 { font-size: 11pt; margin: 7mm 0 2mm; color: {{ $primary }}; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; font-size: 8pt; color: #5e6e68; border-bottom: 1px solid #14201c; padding: 2mm 1.5mm; }
    td { padding: 2mm 1.5mm; border-bottom: 1px solid #e3e7e4; vertical-align: top; }
    .num { text-align: right; white-space: nowrap; }
    .heading td { font-weight: bold; background: #f2f3ee; }
    .totals td { border: none; padding: 1mm 1.5mm; }
    .grand td { font-weight: bold; font-size: 11pt; border-top: 2px solid {{ $primary }}; }
    .box { border: 1px solid #d5dbd7; padding: 3mm; }
    .accepted { border: 2px solid {{ $accent }}; padding: 3mm; margin-top: 6mm; }
    .section { page-break-inside: avoid; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    .prose p { margin: 0 0 2mm; } .prose ul, .prose ol { margin: 0 0 2mm 5mm; padding: 0; }
</style>
</head>
<body>
<header>
    <table><tr>
        <td style="border:none;padding:0"><span class="brand">{{ $company->company_name }}</span></td>
        <td style="border:none;padding:0;text-align:right" class="muted">Quotation {{ $quote->reference() }}</td>
    </tr></table>
</header>
<footer>
    {{ $company->company_name }}
    @if ($company->registration_number) · Reg. {{ $company->registration_number }} @endif
    @if ($company->vat_number) · VAT {{ $company->vat_number }} @endif
    @if ($company->address) · {{ str_replace("\n", ', ', $company->address) }} @endif
</footer>

<table>
    <tr>
        <td style="border:none;padding:0;width:58%">
            <h1>{{ $quote->title }}</h1>
            <div class="muted">Quotation {{ $quote->reference() }}</div>
            @if ($quote->listing) <div class="muted">Property: {{ $quote->listing->title }}</div> @endif
        </td>
        <td style="border:none;padding:0">
            <div class="box">
                <strong>Prepared for</strong><br>
                {{ $quote->client->company ?: $quote->client->name }}<br>
                @if ($quote->client->company) {{ $quote->client->name }}<br> @endif
                {{ $quote->client->email }}
                @if ($quote->client->vat_number)<br>VAT {{ $quote->client->vat_number }} @endif
                <br><br>
                Date: {{ $date($quote->issue_date) }}<br>
                <strong>Valid until: {{ $date($quote->valid_until) }}</strong>
            </div>
        </td>
    </tr>
</table>

@if ($quote->scope)
    <h2>Scope</h2><div class="prose">{!! $quote->scope !!}</div>
@endif

@if ($once->isNotEmpty())
    <h2>Once-off costs</h2>
    <table>
        <thead><tr><th>Description</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Discount</th><th class="num">Amount (excl. VAT)</th></tr></thead>
        <tbody>
        @foreach ($once as $l)
            @if ($l->kind === 'heading')
                <tr class="heading"><td colspan="5">{{ $l->description }}</td></tr>
            @else
                <tr>
                    <td>{{ $l->description }}@if (! $l->vat_applicable) <span class="muted">(no VAT)</span>@endif</td>
                    <td class="num">{{ $qty($l->quantity) }} {{ $l->unit }}</td>
                    <td class="num">{{ $money($l->unit_price) }}</td>
                    <td class="num">{{ $l->discount_type === 'percent' ? ($l->discount_value / 100) . '%' : ($l->discount_type === 'amount' ? $money($l->discount_value) : '—') }}</td>
                    <td class="num">{{ $money($l->net) }}</td>
                </tr>
            @endif
        @endforeach
        </tbody>
    </table>
    <table class="totals" style="width:60%;margin-left:40%;margin-top:2mm">
        <tr><td>Subtotal</td><td class="num">{{ $money($quote->once_subtotal) }}</td></tr>
        @if ($quote->once_discount > 0)<tr><td>Discount</td><td class="num">− {{ $money($quote->once_discount) }}</td></tr>@endif
        <tr><td>VAT ({{ $quote->vat_rate_bps / 100 }}%)</td><td class="num">{{ $money($quote->once_vat) }}</td></tr>
        <tr class="grand"><td>Total</td><td class="num">{{ $money($quote->once_total) }}</td></tr>
    </table>
@endif

@if ($recurring->isNotEmpty())
<div class="section">
    <h2>Ongoing costs</h2>
    <table>
        <thead><tr><th>Description</th><th>Billed</th><th class="num">Amount (excl. VAT)</th><th class="num">VAT</th></tr></thead>
        <tbody>
        @foreach ($recurring as $l)
            <tr><td>{{ $l->description }}</td><td>{{ $l->frequency === 'monthly' ? 'Monthly' : 'Annually' }}</td><td class="num">{{ $money($l->net) }}</td><td class="num">{{ $money($l->vat) }}</td></tr>
        @endforeach
        </tbody>
    </table>
    <table class="totals" style="width:60%;margin-left:40%;margin-top:2mm">
        @if ($quote->monthly_total > 0)<tr class="grand"><td>Per month (incl. VAT)</td><td class="num">{{ $money($quote->monthly_total) }}</td></tr>@endif
        @if ($quote->annual_total > 0)<tr class="grand"><td>Per year (incl. VAT)</td><td class="num">{{ $money($quote->annual_total) }}</td></tr>@endif
    </table>
</div>
@endif

@if ($quote->milestones->isNotEmpty())
<div class="section">
    <h2>Delivery schedule</h2>
    <table>
        <thead><tr><th>Milestone</th><th>Start</th><th>End</th><th>Notes</th></tr></thead>
        <tbody>@foreach ($quote->milestones as $m)<tr><td>{{ $m->name }}</td><td>{{ $date($m->starts_on) ?? '—' }}</td><td>{{ $date($m->ends_on) ?? '—' }}</td><td>{{ $m->description }}</td></tr>@endforeach</tbody>
    </table>
</div>
@endif

@if ($quote->payments->isNotEmpty())
<div class="section">
    <h2>Payment schedule</h2>
    <table>
        <thead><tr><th>Payment</th><th class="num">Share</th><th class="num">Amount (incl. VAT)</th><th>Due</th></tr></thead>
        <tbody>@foreach ($quote->payments as $p)<tr><td>{{ $p->label }}</td><td class="num">{{ $p->percent_bps / 100 }}%</td><td class="num">{{ $money(intdiv($quote->once_total * $p->percent_bps + 5000, 10000)) }}</td><td>{{ $p->due }}</td></tr>@endforeach</tbody>
    </table>
</div>
@endif

@if ($quote->payment_method)
    <div class="section"><h2>How to pay</h2><div class="box">{!! nl2br(e($quote->payment_method)) !!}</div></div>
@endif
@foreach (['assumptions' => 'Assumptions', 'exclusions' => 'Exclusions', 'terms' => 'Terms and conditions'] as $field => $label)
    @if ($quote->{$field})<h2>{{ $label }}</h2><div class="prose">{!! $quote->{$field} !!}</div>@endif
@endforeach

@if ($quote->accepted_at)
    <div class="accepted">
        <strong>Accepted</strong> by {{ $quote->accepted_name }} on {{ $quote->accepted_at->setTimezone(config('platform.display_timezone'))->format('j F Y, H:i') }} SAST.<br>
        <span class="muted">Fingerprint of the accepted version: {{ $quote->fingerprint }}</span>
    </div>
@endif
</body>
</html>
