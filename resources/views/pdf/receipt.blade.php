<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $receipt->number }}</title>
<style>
    @page { margin: 22mm 18mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #14201c; }
    .head { border-bottom: 2px solid #1d4a3c; padding-bottom: 8px; margin-bottom: 18px; }
    .company { font-size: 15pt; font-weight: bold; color: #1d4a3c; }
    .muted { color: #55635d; }
    h1 { font-size: 18pt; margin: 0 0 4px; }
    table { width: 100%; border-collapse: collapse; margin-top: 14px; }
    td { padding: 7px 0; border-bottom: 1px solid #d9dfdb; vertical-align: top; }
    td.label { width: 42%; color: #55635d; }
    .amount { font-size: 16pt; font-weight: bold; }
    .note { margin-top: 22px; padding: 10px; background: #f1f4f2; font-size: 9pt; }
</style>
</head>
<body>
    <div class="head">
        <div class="company">{{ $company->company_name }}</div>
        <div class="muted">{{ $company->address }}</div>
        <div class="muted">
            @if ($company->registration_number) Reg. {{ $company->registration_number }} @endif
            @if ($company->vat_number) · VAT {{ $company->vat_number }} @endif
        </div>
    </div>

    <h1>{{ $receipt->kind === 'refund' ? 'Refund receipt' : 'Deposit receipt' }}</h1>
    <div class="muted">{{ $receipt->number }} · issued {{ $receipt->issued_at->setTimezone($tz)->format('j F Y, H:i') }} SAST</div>

    <table>
        <tr><td class="label">Received from</td><td>{{ $receipt->deposit->user->name }}</td></tr>
        <tr><td class="label">Property</td><td>{{ $receipt->deposit->listing->title }}</td></tr>
        <tr><td class="label">Deposit reference</td><td>{{ $receipt->deposit->reference }}</td></tr>
        <tr><td class="label">Deposit terms</td><td>{{ $receipt->deposit->listing->deposit_terms->value === 'security' ? 'Refundable security deposit, not part of the purchase price' : 'Counts toward the purchase price if you win' }}</td></tr>
        <tr><td class="label">Amount</td><td class="amount">R {{ number_format($receipt->amount / 100, 2, '.', ' ') }}</td></tr>
    </table>

    <div class="note">
        This deposit is held under the Rules of Auction for this property. If you do not win, it is refunded to your verified bank account in your own name. Keep this receipt for your records.
    </div>
</body>
</html>
