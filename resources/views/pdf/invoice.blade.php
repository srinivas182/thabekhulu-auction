<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $invoice->number }}</title>
<style>
    @page { margin: 22mm 18mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #14201c; }
    .head { border-bottom: 2px solid #1d4a3c; padding-bottom: 8px; margin-bottom: 18px; }
    .company { font-size: 15pt; font-weight: bold; color: #1d4a3c; }
    .muted { color: #55635d; }
    h1 { font-size: 18pt; margin: 0 0 4px; }
    table { width: 100%; border-collapse: collapse; margin-top: 14px; }
    td, th { padding: 7px 0; border-bottom: 1px solid #d9dfdb; text-align: left; vertical-align: top; }
    .num { text-align: right; }
    .total td { font-weight: bold; font-size: 12pt; border-bottom: 2px solid #1d4a3c; }
</style>
</head>
<body>
    <div class="head">
        <div class="company">{{ $company->company_name }}</div>
        <div class="muted">{{ $company->address }}</div>
        <div class="muted">@if ($company->registration_number) Reg. {{ $company->registration_number }} @endif @if ($company->vat_number) · VAT {{ $company->vat_number }} @endif</div>
    </div>

    <h1>Tax invoice</h1>
    <div class="muted">{{ $invoice->number }} · {{ $invoice->issued_at->setTimezone($tz)->format('j F Y') }}</div>

    <table>
        <tr><td style="width:40%" class="muted">To</td><td>{{ $invoice->recipient }}@if ($invoice->recipient_address)<br>{{ $invoice->recipient_address }}@endif @if ($invoice->recipient_vat_number)<br>VAT {{ $invoice->recipient_vat_number }}@endif</td></tr>
    </table>

    <table>
        <tr><th>Description</th><th class="num">Amount</th></tr>
        <tr><td>{{ $invoice->description }}</td><td class="num">R {{ number_format($invoice->fee / 100, 2, '.', ' ') }}</td></tr>
        <tr><td class="muted">VAT at {{ rtrim(rtrim(number_format($invoice->vat_rate_bps / 100, 2, '.', ''), '0'), '.') }}%</td><td class="num">R {{ number_format($invoice->vat / 100, 2, '.', ' ') }}</td></tr>
        <tr class="total"><td>Total</td><td class="num">R {{ number_format($invoice->total / 100, 2, '.', ' ') }}</td></tr>
    </table>
    <p class="muted" style="margin-top:18px">This fee is deducted from the sale proceeds paid to you; no separate payment is due.</p>
</body>
</html>
