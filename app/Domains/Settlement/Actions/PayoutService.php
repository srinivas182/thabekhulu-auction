<?php

namespace App\Domains\Settlement\Actions;

use App\Domains\Audit\Support\Audit;
use App\Domains\Quotes\Settings\QuotationSettings;
use App\Domains\Settlement\Models\Invoice;
use App\Domains\Settlement\Models\Payout;
use App\Domains\Settlement\Models\SettlementCase;
use App\Domains\Verification\Support\SecureFiles;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Seller payouts (S6), platform-fee tax invoices (S7) and transfer stages (S8),
 * all after settlement is complete. Cents throughout, rounding half up.
 */
class PayoutService
{
    /** Half-up rounding of amount × rate (basis points). */
    public static function pct(int $cents, int $bps): int
    {
        return intdiv($cents * $bps + 5000, 10000);
    }

    /**
     * Commission sale: fee = price × fee %, VAT on the fee, seller receives price − fee − VAT.
     * Thabekhulu-renovated: seller receives the fixed seller price; no fee invoice.
     *
     * @return array{deal: string, price: int, fee: int, vat: int, amount: int, vatRate: int}
     */
    public static function figures(SettlementCase $case): array
    {
        $listing = $case->listing;
        $price = $case->price * 100;
        $vatRate = app(QuotationSettings::class)->vat_rate_bps;

        if ($listing->deal_type->value === 'renovated') {
            return ['deal' => 'renovated', 'price' => $price, 'fee' => 0, 'vat' => 0, 'amount' => (int) $listing->seller_price * 100, 'vatRate' => $vatRate];
        }
        $fee = self::pct($price, (int) $listing->fee_bps);
        $vat = self::pct($fee, $vatRate);

        return ['deal' => 'commission', 'price' => $price, 'fee' => $fee, 'vat' => $vat, 'amount' => $price - $fee - $vat, 'vatRate' => $vatRate];
    }

    public function prepare(SettlementCase $case, string $payee, ?string $reference, User $by): Payout
    {
        $this->completed($case);
        if (Payout::where('settlement_case_id', $case->id)->exists()) {
            throw ValidationException::withMessages(['payout' => __('A payout is already prepared for this sale.')]);
        }
        $f = self::figures($case);
        $payout = Payout::create([
            'settlement_case_id' => $case->id, 'deal_type' => $f['deal'], 'price' => $f['price'], 'fee' => $f['fee'], 'vat' => $f['vat'],
            'amount' => $f['amount'], 'payee' => $payee, 'payee_reference' => $reference, 'status' => 'prepared', 'prepared_by' => $by->id,
        ]);
        Audit::record('payouts', 'prepared', 'Prepared seller payout of R '.number_format($f['amount'] / 100, 2, '.', ' ')." for {$case->listing->title}", $payout, $by);

        return $payout;
    }

    public function approve(Payout $payout, User $by): void
    {
        if ($payout->status !== 'prepared') {
            throw ValidationException::withMessages(['payout' => __('This payout is not waiting for approval.')]);
        }
        if ($payout->prepared_by === $by->id) {
            throw ValidationException::withMessages(['payout' => __('A payout must be approved by someone other than the person who prepared it.')]);
        }
        $payout->forceFill(['status' => 'approved', 'approved_by' => $by->id, 'approved_at' => now()])->save();
        Audit::record('payouts', 'approved', "Approved seller payout for {$payout->case->listing->title}", $payout, $by);
    }

    public function markPaid(Payout $payout, UploadedFile $proof, User $by): void
    {
        if ($payout->status !== 'approved') {
            throw ValidationException::withMessages(['payout' => __('Approve the payout before recording payment.')]);
        }
        $payout->forceFill(['status' => 'paid', 'paid_by' => $by->id, 'paid_at' => now(), 'proof_path' => SecureFiles::put($proof, "payouts/{$payout->id}")])->save();
        Audit::record('payouts', 'paid', "Recorded payment of the seller payout for {$payout->case->listing->title}", $payout, $by);
    }

    public function issueInvoice(SettlementCase $case, string $recipient, ?string $address, ?string $vatNumber, User $by): Invoice
    {
        $this->completed($case);
        $f = self::figures($case);
        if ($f['deal'] !== 'commission') {
            throw ValidationException::withMessages(['invoice' => __('Thabekhulu-renovated sales have no platform-fee invoice.')]);
        }

        return DB::transaction(function () use ($case, $recipient, $address, $vatNumber, $by, $f) {
            if (Invoice::where('settlement_case_id', $case->id)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['invoice' => __('An invoice was already issued for this sale.')]);
            }
            $invoice = Invoice::create([
                'number' => $this->nextNumber(), 'settlement_case_id' => $case->id, 'recipient' => $recipient, 'recipient_address' => $address,
                'recipient_vat_number' => $vatNumber, 'description' => "Platform fee: auction sale of {$case->listing->title}",
                'fee' => $f['fee'], 'vat_rate_bps' => $f['vatRate'], 'vat' => $f['vat'], 'total' => $f['fee'] + $f['vat'], 'issued_by' => $by->id, 'issued_at' => now(),
            ]);
            Audit::record('payouts', 'invoice_issued', "Issued tax invoice {$invoice->number} for {$case->listing->title}", $invoice, $by);

            return $invoice;
        });
    }

    public function recordStage(SettlementCase $case, string $stage, string $doneOn, ?string $note, User $by): void
    {
        $this->completed($case);
        $allowed = $case->listing->category->slug === 'mining' ? SettlementCase::MINING_STAGES : SettlementCase::TRANSFER_STAGES;
        if (! array_key_exists($stage, $allowed)) {
            throw ValidationException::withMessages(['stage' => __('That stage does not apply to this sale.')]);
        }
        DB::table('transfer_stages')->updateOrInsert(['settlement_case_id' => $case->id, 'stage' => $stage], [
            'done_on' => $doneOn, 'note' => $note, 'recorded_by' => $by->id, 'updated_at' => now(), 'created_at' => now(),
        ]);
        Audit::record('settlement', 'transfer_stage', $allowed[$stage]." for {$case->listing->title} ({$doneOn})", $case, $by);
    }

    /** Gap-free per year, under a row lock (call inside a transaction). */
    private function nextNumber(): string
    {
        $year = (int) now((string) config('platform.display_timezone'))->format('Y');
        $row = DB::table('invoice_sequences')->where('year', $year)->lockForUpdate()->first();
        $n = $row === null ? 1 : (int) $row->last_number + 1;
        $row === null ? DB::table('invoice_sequences')->insert(['year' => $year, 'last_number' => 1]) : DB::table('invoice_sequences')->where('year', $year)->update(['last_number' => $n]);

        return sprintf('TDG-INV-%d-%04d', $year, $n);
    }

    private function completed(SettlementCase $case): void
    {
        if ($case->status !== 'completed') {
            throw ValidationException::withMessages(['payout' => __('Complete the settlement first.')]);
        }
    }
}
