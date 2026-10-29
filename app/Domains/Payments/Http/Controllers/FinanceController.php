<?php

namespace App\Domains\Payments\Http\Controllers;

use App\Domains\Audit\Support\Audit;
use App\Domains\Payments\Actions\DepositService;
use App\Domains\Payments\Actions\ImportStatement;
use App\Domains\Payments\Models\BankLine;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Payments\Settings\PaymentSettings;
use App\Domains\Payments\Support\DepositReference;
use App\Domains\Settlement\Models\SettlementPayment;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Finance: deposits (deposits.view), statement imports and the queue of
 * lines that need a person (deposits.reconcile), payment settings (settings.manage).
 */
class FinanceController extends Controller
{
    public function index(Request $request): Response
    {
        $tab = $request->string('tab')->toString() ?: 'deposits';

        return Inertia::render('admin/finance/index', [
            'tab' => $tab,
            'counts' => ['queue' => BankLine::whereIn('status', ['unmatched', 'short', 'over'])->count()],
            'totals' => [
                'held' => (int) DB::table('ledger_entries')->sum('amount'),
                'awaiting' => Deposit::whereIn('status', ['awaiting', 'part_paid'])->count(),
                'received' => Deposit::where('status', 'received')->count(),
            ],
            // After the award: balances paid to Thabekhulu.
            'balances' => $tab === 'balances' ? SettlementPayment::with(['case.listing:id,title', 'case.approval.user:id,first_name,last_name'])->latest()->limit(200)->get()->map(fn ($p) => [
                'ulid' => $p->ulid, 'reference' => $p->reference, 'listing' => $p->case->listing->title, 'buyer' => $p->case->approval->user->name,
                'due' => $p->amount_due, 'received' => $p->received, 'status' => $p->status, 'statusLabel' => SettlementPayment::STATUSES[$p->status],
                'balanceDue' => $p->case->balance_due?->format('j M Y'),
                'proofUrl' => $p->proof_path ? route('admin.balances.proof', $p) : null, 'proofAt' => $p->proof_uploaded_at?->setTimezone((string) config('platform.display_timezone'))->format('j M, H:i'),
            ]) : [],
            // Manual confirmations waiting for a second finance user.
            'confirmations' => DB::table('payment_confirmations as c')->join('users as u', 'u.id', '=', 'c.requested_by')->where('c.status', 'pending')->orderBy('c.id')
                ->get(['c.id', 'c.kind', 'c.target_id', 'c.amount', 'c.bank_reference', 'c.paid_on', 'c.note', 'c.requested_by', 'u.first_name', 'u.last_name'])
                ->map(fn ($c) => ['id' => $c->id, 'kind' => $c->kind, 'amount' => (int) $c->amount, 'bankReference' => $c->bank_reference, 'paidOn' => $c->paid_on, 'note' => $c->note,
                    'by' => trim("{$c->first_name} {$c->last_name}"), 'mine' => (int) $c->requested_by === $request->user()?->id,
                    'target' => $c->kind === 'deposit' ? (string) Deposit::whereKey($c->target_id)->value('reference') : (string) SettlementPayment::whereKey($c->target_id)->value('reference')]),
            'deposits' => $tab === 'deposits' ? Deposit::with(['listing:id,title', 'user:id,first_name,last_name'])->latest()->limit(200)->get()->map(fn (Deposit $d) => [
                'ulid' => $d->ulid, 'reference' => $d->reference, 'listing' => $d->listing->title, 'bidder' => $d->user->name, 'amount' => $d->amount, 'paid' => $d->balance(), 'proofUrl' => $d->proof_path ? route('admin.deposits.proof', $d) : null, 'proofAt' => $d->proof_uploaded_at?->setTimezone((string) config('platform.display_timezone'))->format('j M, H:i'),
                'status' => $d->status, 'statusLabel' => Deposit::STATUSES[$d->status], 'receivedAt' => $d->received_at?->toIso8601String(),
            ]) : [],
            'queue' => $tab === 'queue' ? BankLine::whereIn('status', ['unmatched', 'short', 'over'])->orderBy('posted_on')->get()->map(fn (BankLine $l) => [
                'id' => $l->id, 'date' => $l->posted_on->toDateString(), 'amount' => $l->amount, 'description' => $l->description,
                'reference' => $l->reference_found, 'status' => $l->status, 'statusLabel' => BankLine::STATUSES[$l->status],
            ]) : [],
            'imports' => $tab === 'imports' ? DB::table('bank_imports as i')->leftJoin('users as u', 'u.id', '=', 'i.imported_by')->orderByDesc('i.id')->limit(50)
                ->get(['i.file_name', 'i.lines_total', 'i.matched', 'i.queued', 'i.duplicates', 'i.created_at', 'u.first_name', 'u.last_name'])
                ->map(fn ($i) => ['file' => $i->file_name, 'lines' => $i->lines_total, 'matched' => $i->matched, 'queued' => $i->queued, 'duplicates' => $i->duplicates, 'by' => trim("{$i->first_name} {$i->last_name}"), 'at' => (string) $i->created_at]) : [],
            'can' => ['reconcile' => Gate::allows('deposits.reconcile')],
        ]);
    }

    public function import(Request $request, ImportStatement $importer, #[CurrentUser] User $actor): RedirectResponse
    {
        $request->validate(['statement' => ['required', 'file', 'mimes:csv,txt,ofx,qfx', 'max:5120']]);
        /** @var UploadedFile $file */
        $file = $request->file('statement');
        $r = $importer->handle($file, $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':matched confirmed, :queued to check, :dupes already imported.', ['matched' => $r['matched'], 'queued' => $r['queued'], 'dupes' => $r['duplicates']])]);

        return back();
    }

    public function resolve(Request $request, BankLine $line, DepositService $deposits, #[CurrentUser] User $actor): RedirectResponse
    {
        abort_unless(in_array($line->status, ['unmatched', 'short', 'over'], true), 422);
        $data = $request->validate([
            'action' => ['required', Rule::in(['apply', 'ignore'])],
            'reference' => ['nullable', 'required_if:action,apply', 'string', 'max:24'],
            'note' => ['required', 'string', 'max:500'],
        ], ['note.required' => __('Add a note explaining the decision.')]);

        if ($data['action'] === 'apply') {
            $ref = strtoupper(trim((string) $data['reference']));
            $deposit = DepositReference::isValid($ref) ? Deposit::where('reference', $ref)->first() : null;
            if ($deposit === null) {
                throw ValidationException::withMessages(['reference' => __('No deposit has that reference.')]);
            }
            $deposits->receive($deposit, $line->amount, 'bank_import', "line {$line->id}", $actor, $data['note']);
            $line->forceFill(['deposit_id' => $deposit->id, 'status' => 'resolved'])->save();
        } else {
            $line->forceFill(['status' => 'ignored'])->save();
        }

        $line->forceFill(['resolution' => $data['note'], 'resolved_by' => $actor->id])->save();
        Audit::record('payments', "line_{$data['action']}", ucfirst($data['action'])." bank line {$line->id}: {$data['note']}", null, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved.')]);

        return back();
    }

    public function settings(): Response
    {
        $s = app(PaymentSettings::class);

        return Inertia::render('admin/finance/settings', ['settings' => [
            'trust_bank' => $s->trust_bank, 'trust_account_name' => $s->trust_account_name, 'trust_account_number' => $s->trust_account_number,
            'trust_branch_code' => $s->trust_branch_code, 'refund_business_days' => $s->refund_business_days, 'runner_up_hold_days' => $s->runner_up_hold_days,
        ]]);
    }

    public function updateSettings(Request $request, #[CurrentUser] User $actor): RedirectResponse
    {
        $data = $request->validate([
            'trust_bank' => ['nullable', 'string', 'max:60'], 'trust_account_name' => ['required', 'string', 'max:160'],
            'trust_account_number' => ['nullable', 'regex:/^\d{6,16}$/'], 'trust_branch_code' => ['nullable', 'digits:6'],
            'refund_business_days' => ['required', 'integer', 'min:0', 'max:60'], 'runner_up_hold_days' => ['required', 'integer', 'min:0', 'max:120'],
        ]);
        $s = app(PaymentSettings::class);
        foreach ($data as $k => $v) {
            $s->{$k} = is_int($s->{$k}) ? (int) $v : (string) $v;
        }
        $s->save();

        Audit::record('payments', 'settings_updated', 'Updated payment settings', null, $actor, ['changed' => array_keys($data)]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment settings saved.')]);

        return back();
    }
}
