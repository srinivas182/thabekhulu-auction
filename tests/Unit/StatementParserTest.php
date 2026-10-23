<?php

use App\Domains\Payments\Support\DepositReference;
use App\Domains\Payments\Support\StatementParser;

it('reads South African amounts in their common formats', function (string $in, int $cents) {
    expect(StatementParser::cents($in))->toBe($cents);
})->with([
    ['1 200 000.00', 120000000], ['1200000,00', 120000000], ['R1,200,000.50', 120000050], ['-50.00', -5000], ['', 0], ['abc', 0],
]);

it('parses a CSV with a credit column and skips debits', function () {
    $csv = "Account statement\nDate,Description,Debit,Credit,Balance\n2026/10/26,EFT CR TDG-D-4821-ABCDE N DUBE,,1 200 000.00,1 300 000.00\n2026/10/26,BANK FEES,25.00,,1 299 975.00\n";
    $lines = StatementParser::parse($csv, 'fnb.csv');

    expect($lines)->toHaveCount(1)->and($lines[0])->toBe(['date' => '2026-10-26', 'amount' => 120000000, 'description' => 'EFT CR TDG-D-4821-ABCDE N DUBE']);
});

it('parses a semicolon CSV with a signed amount column and day-first dates', function () {
    $csv = "Date;Narrative;Amount\n26/10/2026;Payment tdg d 4821;5000,00\n27/10/2026;Debit order;-100,00\n";

    expect(StatementParser::parse($csv, 'x.csv'))->toBe([['date' => '2026-10-26', 'amount' => 500000, 'description' => 'Payment tdg d 4821']]);
});

it('parses OFX credits', function () {
    $ofx = "OFXHEADER:100\n<OFX><BANKTRANLIST><STMTTRN><TRNTYPE>CREDIT<DTPOSTED>20261026120000<TRNAMT>1200000.00<FITID>9981<NAME>N DUBE<MEMO>TDG-D-4821-ABCDE</STMTTRN>"
        .'<STMTTRN><TRNTYPE>DEBIT<DTPOSTED>20261026<TRNAMT>-30.00<FITID>9982<NAME>FEE</STMTTRN></BANKTRANLIST></OFX>';

    expect(StatementParser::parse($ofx, 's.ofx'))->toBe([['date' => '2026-10-26', 'amount' => 120000000, 'description' => 'N DUBE TDG-D-4821-ABCDE 9981']]);
});

it('explains a file without recognisable columns', function () {
    expect(fn () => StatementParser::parse("foo,bar\n1,2\n", 'x.csv'))->toThrow(RuntimeException::class);
});

it('makes references that survive any single-character typo check', function () {
    foreach (range(1, 50) as $_) {
        $ref = DepositReference::make('4821');
        expect(DepositReference::isValid($ref))->toBeTrue()
            ->and(DepositReference::find('eft '.strtolower(str_replace('-', ' ', $ref)).' thanks'))->toBe($ref);
    }
});
