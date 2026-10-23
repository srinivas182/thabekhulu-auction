<?php

namespace App\Domains\Payments\Http\Controllers;

use App\Domains\Audit\Support\Audit;
use App\Domains\Payments\Actions\RefundService;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Payments\Models\Refund;
use App\Domains\Payments\Models\RefundBatch;
use App\Domains\Payments\Support\Reconciliation;
use App\Domains\Verification\Models\BankAccount;
use App\Domains\Verification\Support\SecureFiles;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Finance → Refunds (refunds.view; refunds.issue to schedule/prepare/record; refunds.approve to approve). */
class RefundController extends Controller
{
    public function index(Request $request): Response
    {
        $tab = $request->string('tab')->toString() ?: 'scheduled';

        return Inertia::render('admin/finance/refunds', [
            'tab' => $tab,
            'held' => $tab === 'scheduled' ? Deposit::with(['listing:id,title', 'user:id,first_name,last_name'])->where('status', 'received')->latest('received_at')->limit(200)->get()
                ->map(fn (Deposit $d) => ['reference' => $d->reference, 'listing' => $d->listing->title, 'bidder' => $d->user->name, 'held' => $d->balance(), 'method' => $d->method]) : [],
            'scheduled' => $tab === 'scheduled' ? Refund::with(['deposit.listing:id,title', 'deposit.user:id,first_name,last_name'])->whereIn('status', ['scheduled', 'failed'])->orderBy('due_on')->get()
                ->map(fn (Refund $r) => ['id' => $r->id, 'reference' => $r->deposit->reference, 'listing' => $r->deposit->listing->title, 'bidder' => $r->deposit->user->name,
                    'held' => $r->deposit->balance(), 'reason' => Refund::REASONS[$r->reason], 'dueOn' => $r->due_on->toDateString(), 'due' => ! $r->due_on->isFuture(),
                    'status' => $r->status, 'failure' => $r->failure]) : [],
            'batches' => $tab === 'batches' ? RefundBatch::with(['preparer:id,first_name,last_name', 'approver:id,first_name,last_name'])->latest('id')->limit(50)->get()
                ->map(fn (RefundBatch $b) => ['id' => $b->id, 'number' => $b->number, 'status' => $b->status, 'statusLabel' => RefundBatch::STATUSES[$b->status], 'total' => $b->total, 'count' => $b->count,
                    'preparedBy' => $b->preparer->name, 'approvedBy' => $b->approver?->name, 'preparedAt' => $b->prepared_at->toIso8601String()]) : [],
            'reconciliation' => $tab === 'reconciliation' ? Reconciliation::run() : null,
            'reasons' => Refund::REASONS,
            'can' => ['issue' => Gate::allows('refunds.issue'), 'approve' => Gate::allows('refunds.approve')],
        ]);
    }

    public function schedule(Request $request, RefundService $refunds, #[CurrentUser] User $actor): RedirectResponse
    {
        $data = $request->validate(['reference' => ['required', 'string', 'exists:deposits,reference'], 'reason' => ['required', Rule::in(array_keys(Refund::REASONS))], 'due_on' => ['nullable', 'date', 'after_or_equal:today']]);
        $refunds->schedule(Deposit::where('reference', $data['reference'])->firstOrFail(), $data['reason'], $actor, isset($data['due_on']) ? CarbonImmutable::parse($data['due_on']) : null);

        return $this->done('Refund scheduled.');
    }

    public function cancel(Refund $refund, #[CurrentUser] User $actor): RedirectResponse
    {
        abort_unless(in_array($refund->status, ['scheduled', 'failed'], true), 422);
        DB::transaction(function () use ($refund) {
            $refund->forceFill(['status' => 'cancelled', 'refund_batch_id' => null])->save();
            $refund->deposit->forceFill(['status' => 'received'])->save();
        });
        Audit::record('payments', 'refund_cancelled', "Cancelled refund of {$refund->deposit->reference}", $refund->deposit, $actor);

        return $this->done('Refund cancelled; the deposit is held again.');
    }

    public function prepare(RefundService $refunds, #[CurrentUser] User $actor): RedirectResponse
    {
        $r = $refunds->prepare($actor);
        $held = $r['held'] === [] ? '' : ' '.__('Held back: :list', ['list' => implode('; ', $r['held'])]);

        return $this->done($r['batch'] ? __(':number prepared with :n refunds. Another finance user must approve it.', ['number' => $r['batch']->number, 'n' => $r['batch']->count]).$held : __('No refunds are due.').$held);
    }

    public function batch(RefundBatch $batch): Response
    {
        $batch->load(['refunds.deposit.listing:id,title', 'refunds.deposit.user:id,first_name,last_name', 'preparer:id,first_name,last_name', 'approver:id,first_name,last_name']);

        return Inertia::render('admin/finance/refund-batch', [
            'batch' => ['id' => $batch->id, 'number' => $batch->number, 'status' => $batch->status, 'statusLabel' => RefundBatch::STATUSES[$batch->status], 'total' => $batch->total,
                'preparedBy' => $batch->preparer->name, 'preparedById' => $batch->prepared_by, 'approvedBy' => $batch->approver?->name, 'hasProof' => $batch->proof_path !== null],
            'refunds' => $batch->refunds->map(function (Refund $r) {
                $bank = BankAccount::where('user_id', $r->deposit->user_id)->latest('id')->first();

                return ['reference' => $r->deposit->reference, 'listing' => $r->deposit->listing->title, 'bidder' => $r->deposit->user->name, 'amount' => $r->amount, 'channel' => $r->channel,
                    'status' => $r->status, 'failure' => $r->failure, 'account' => $r->channel === 'eft' && $bank ? "{$bank->holder_name} · {$bank->bank} ·••• {$bank->account_last4}" : null];
            }),
            'can' => ['issue' => Gate::allows('refunds.issue'), 'approve' => Gate::allows('refunds.approve')],
        ]);
    }

    public function approve(RefundBatch $batch, RefundService $refunds, #[CurrentUser] User $actor): RedirectResponse
    {
        $b = $refunds->approve($batch, $actor);

        return $this->done($b->status === 'paid' ? __(':number approved and paid through the escrow provider.', ['number' => $b->number]) : __(':number approved. Pay the EFT refunds at the bank using the payment file, then record the payment.', ['number' => $b->number]));
    }

    public function recordPayment(Request $request, RefundBatch $batch, RefundService $refunds, #[CurrentUser] User $actor): RedirectResponse
    {
        $request->validate(['proof' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,csv,txt', 'max:10240']], ['proof.required' => __('Upload the bank’s payment confirmation.')]);
        /** @var UploadedFile $proof */
        $proof = $request->file('proof');
        $refunds->recordBankPayment($batch, SecureFiles::put($proof, "refund-proof/{$batch->number}"), $actor);

        return $this->done('Payment recorded; receipts emailed and refund accounts unlocked.');
    }

    /** Bank payment file for the EFT refunds of an approved batch (generic CSV; adapt to the bank's import format). */
    public function bankFile(RefundBatch $batch): StreamedResponse
    {
        abort_unless(in_array($batch->status, ['approved', 'paid'], true), 422, __('Approve the batch first.'));

        return response()->streamDownload(function () use ($batch) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['Beneficiary name', 'Bank', 'Branch code', 'Account number', 'Account type', 'Amount', 'Beneficiary reference', 'Batch'], ',', '"', '\\');
            foreach ($batch->refunds()->with('deposit')->where('channel', 'eft')->get() as $r) {
                $bank = BankAccount::where('user_id', $r->deposit->user_id)->where('status', 'verified')->latest('id')->firstOrFail();
                fputcsv($out, [$bank->holder_name, $bank->bank, $bank->branch_code, $bank->account_number, $bank->account_type, number_format($r->amount / 100, 2, '.', ''), 'REFUND '.$r->deposit->reference, $batch->number], ',', '"', '\\');
            }
            fclose($out);
        }, "{$batch->number}.csv", ['Content-Type' => 'text/csv', 'Cache-Control' => 'no-store, private']);
    }

    /** Full ledger for the accountant. */
    public function ledgerExport(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['Date', 'Deposit reference', 'Listing', 'Bidder', 'Type', 'Amount (R)', 'Source', 'Source reference', 'Note'], ',', '"', '\\');
            DB::table('ledger_entries as e')->join('deposits as d', 'd.id', '=', 'e.deposit_id')->join('listings as l', 'l.id', '=', 'd.listing_id')->join('users as u', 'u.id', '=', 'd.user_id')
                ->orderBy('e.id')->select(['e.created_at', 'd.reference', 'l.title', 'u.first_name', 'u.last_name', 'e.type', 'e.amount', 'e.source', 'e.source_ref', 'e.note'])
                ->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $r) {
                        fputcsv($out, [(string) $r->created_at, $r->reference, $r->title, trim("{$r->first_name} {$r->last_name}"), $r->type, number_format($r->amount / 100, 2, '.', ''), $r->source, $r->source_ref, $r->note], ',', '"', '\\');
                    }
                });
            fclose($out);
        }, 'thabekhulu-deposit-ledger-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv', 'Cache-Control' => 'no-store, private']);
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
