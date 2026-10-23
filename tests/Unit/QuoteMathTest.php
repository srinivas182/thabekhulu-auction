<?php

use App\Domains\Quotes\Support\QuoteMath;

it('calculates a line in cents with discount and VAT', function () {
    expect(QuoteMath::line(150, 100000, 'percent', 1000, true, 1500))->toBe(['gross' => 150000, 'discount' => 15000, 'net' => 135000, 'vat' => 20250])
        ->and(QuoteMath::line(100, 99999, 'amount', 200000, true, 1500))->toBe(['gross' => 99999, 'discount' => 99999, 'net' => 0, 'vat' => 0])
        ->and(QuoteMath::line(333, 1001, null, 0, false, 1500)['vat'])->toBe(0);
});

it('keeps once-off and recurring totals apart and skips headings', function () {
    $lines = [
        ['frequency' => 'once', 'kind' => 'item', 'gross' => 100000, 'discount' => 10000, 'net' => 90000, 'vat' => 13500],
        ['frequency' => 'monthly', 'kind' => 'item', 'gross' => 50000, 'discount' => 0, 'net' => 50000, 'vat' => 7500],
        ['frequency' => 'annual', 'kind' => 'item', 'gross' => 120000, 'discount' => 0, 'net' => 120000, 'vat' => 0],
        ['frequency' => 'once', 'kind' => 'heading', 'gross' => 999, 'discount' => 0, 'net' => 999, 'vat' => 0],
    ];

    expect(QuoteMath::totals($lines))->toBe([
        'once_subtotal' => 100000, 'once_discount' => 10000, 'once_vat' => 13500, 'once_total' => 103500,
        'monthly_total' => 57500, 'annual_total' => 120000,
    ]);
});

it('parses rand amounts typed by staff', function () {
    expect(QuoteMath::cents('R 1 250 000,50'))->toBe(125000050)
        ->and(QuoteMath::cents('1250.5'))->toBe(125050)
        ->and(QuoteMath::cents('abc'))->toBe(0);
});
