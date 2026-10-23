<?php

namespace App\Domains\Payments\Actions;

use App\Domains\Audit\Support\Audit;
use App\Domains\Payments\Models\BankLine;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Payments\Support\DepositReference;
use App\Domains\Payments\Support\StatementParser;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Imports a bank statement (P3): each credit line is fingerprinted so a line
 * seen in an earlier statement is ignored; lines with a valid reference and
 * the exact outstanding amount confirm the deposit; everything else goes to
 * the finance queue. Partial and over-payments are never confirmed automatically.
 */
class ImportStatement
{
    public function __construct(private readonly DepositService $deposits) {}

    /** @return array{lines: int, matched: int, queued: int, duplicates: int} */
    public function handle(UploadedFile $file, User $by): array
    {
        $content = (string) file_get_contents($file->getRealPath());
        $hash = hash('sha256', $content);

        if (DB::table('bank_imports')->where('file_hash', $hash)->exists()) {
            throw ValidationException::withMessages(['statement' => __('This statement was already imported.')]);
        }

        try {
            $lines = StatementParser::parse($content, $file->getClientOriginalName());
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['statement' => $e->getMessage()]);
        }

        $importId = DB::table('bank_imports')->insertGetId([
            'file_name' => $file->getClientOriginalName(), 'file_hash' => $hash, 'format' => str_ends_with(strtolower($file->getClientOriginalName()), '.ofx') ? 'ofx' : 'csv',
            'imported_by' => $by->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $counts = ['lines' => count($lines), 'matched' => 0, 'queued' => 0, 'duplicates' => 0];
        $seen = [];

        foreach ($lines as $line) {
            $base = $line['date'].'|'.$line['amount'].'|'.$line['description'];
            $seen[$base] = ($seen[$base] ?? 0) + 1;                       // two identical payments on one day stay distinct
            $lineHash = hash('sha256', $base.'|'.$seen[$base]);

            if (BankLine::where('line_hash', $lineHash)->exists()) {
                $counts['duplicates']++;

                continue;
            }

            $reference = DepositReference::find($line['description']);
            $deposit = $reference ? Deposit::where('reference', $reference)->first() : null;
            $outstanding = $deposit ? $deposit->amount - $deposit->balance() : null;

            $status = match (true) {
                $deposit === null => 'unmatched',
                $outstanding !== null && $line['amount'] === $outstanding => 'matched',
                $outstanding !== null && $line['amount'] < $outstanding => 'short',
                default => 'over',
            };

            $bankLine = BankLine::create([
                'bank_import_id' => $importId, 'line_hash' => $lineHash, 'posted_on' => $line['date'], 'amount' => $line['amount'],
                'description' => mb_substr($line['description'], 0, 255), 'reference_found' => $reference, 'deposit_id' => $deposit?->id, 'status' => $status,
            ]);

            if ($status === 'matched') {
                $this->deposits->receive($deposit, $line['amount'], 'bank_import', "line {$bankLine->id}", $by);
                $counts['matched']++;
            } else {
                $counts['queued']++;
            }
        }

        DB::table('bank_imports')->where('id', $importId)->update(['lines_total' => $counts['lines'], 'matched' => $counts['matched'], 'queued' => $counts['queued'], 'duplicates' => $counts['duplicates']]);
        Audit::record('payments', 'statement_imported', "Imported {$file->getClientOriginalName()}: {$counts['matched']} matched, {$counts['queued']} to check, {$counts['duplicates']} already seen", null, $by);

        return $counts;
    }
}
