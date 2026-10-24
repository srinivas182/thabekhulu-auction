<?php

namespace App\Domains\Compliance\Http\Controllers;

use App\Domains\Audit\Support\Audit;
use App\Domains\Payments\Support\Reconciliation;
use App\Domains\Settlement\Models\Suspension;
use App\Domains\Verification\Models\Verification;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Investor register (T5) and financial reports (T6). */
class ReportsController extends Controller
{
    /**
     * One row per buyer; columns depend on the viewer's role (POPIA: only what each role needs).
     *
     * @return array<int, array<string, mixed>>
     */
    private function registerRows(): array
    {
        $kyc = Gate::allows('kyc.view');
        $money = Gate::allows('deposits.view');
        $winners = DB::table('auction_results')->whereNotNull('winner_approval_id')->pluck('winner_approval_id')->all();

        return User::where('type', 'buyer')->orderBy('last_name')->get()->map(function (User $u) use ($kyc, $money, $winners) {
            $approvals = DB::table('bidder_approvals')->where('user_id', $u->id);
            $verification = DB::table('verifications')->where('user_id', $u->id)->latest('id')->first();
            $row = [
                'name' => $u->name, 'email' => $u->email, 'joined' => $u->created_at?->toDateString(),
                'entities' => DB::table('bidding_entities')->where('user_id', $u->id)->pluck('name')->implode('; '),
                'verification' => $verification->status ?? 'not started',
                'approved_for' => (clone $approvals)->where('status', 'approved')->count(),
                'bids' => DB::table('bids')->whereIn('bidder_approval_id', (clone $approvals)->pluck('id'))->whereNull('voided_at')->count(),
                'wins' => count(array_intersect($winners, (clone $approvals)->pluck('id')->all())),
                'suspended_until' => Suspension::activeFor($u->id)?->until->toDateString(),
            ];
            if ($kyc) {
                $s = DB::table('screenings')->where('user_id', $u->id)->latest('screened_at')->first();
                $row['screening'] = $s ? "{$s->result} ({$s->screened_at})" : 'none';
                // The ID number is stored encrypted; KYC officers see only its last 4 digits here.
                $id = Verification::where('user_id', $u->id)->latest('id')->first()?->id_number;
                $row['id_last4'] = $id ? substr((string) $id, -4) : null;
            }
            if ($money) {
                $row['deposits_held_cents'] = (int) DB::table('ledger_entries')->whereIn('deposit_id', DB::table('deposits')->where('user_id', $u->id)->pluck('id'))->sum('amount');
            }

            return $row;
        })->values()->all();
    }

    public function register(): Response
    {
        return Inertia::render('admin/reports/register', ['rows' => $this->registerRows(), 'can' => ['kyc' => Gate::allows('kyc.view'), 'money' => Gate::allows('deposits.view'), 'export' => Gate::allows('reports.export')]]);
    }

    public function registerCsv(#[CurrentUser] User $actor): StreamedResponse
    {
        $rows = $this->registerRows();
        Audit::record('reports', 'register_exported', 'Exported the investor register ('.count($rows).' buyers)', null, $actor);

        return $this->csv('investor-register', $rows);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function period(Request $request): array
    {
        $tz = (string) config('platform.display_timezone');
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $from = isset($data['from']) ? CarbonImmutable::parse($data['from'], $tz)->startOfDay() : CarbonImmutable::now($tz)->startOfMonth();
        $to = isset($data['to']) ? CarbonImmutable::parse($data['to'], $tz)->endOfDay() : CarbonImmutable::now($tz)->endOfDay();

        return [$from->utc(), $to->utc()];
    }

    public function finance(Request $request): Response
    {
        [$from, $to] = $this->period($request);
        $tz = (string) config('platform.display_timezone');
        $ledger = fn (string $type) => (int) DB::table('ledger_entries')->where('type', $type)->whereBetween('created_at', [$from, $to])->sum('amount');
        $refundsPaid = (int) DB::table('refunds')->where('status', 'paid')->whereBetween('paid_at', [$from, $to])->sum('amount');
        $invoices = DB::table('invoices')->whereBetween('issued_at', [$from, $to]);
        $payouts = DB::table('payouts')->where('status', 'paid')->whereBetween('paid_at', [$from, $to]);

        $figures = [
            'received' => $ledger('received'), 'refunded' => -$ledger('refunded'), 'forfeited' => -$ledger('forfeited'), 'applied' => -$ledger('applied'),
            'adjustments' => $ledger('adjustment'), 'heldAtEnd' => (int) DB::table('ledger_entries')->where('created_at', '<=', $to)->sum('amount'),
            'invoices' => (clone $invoices)->count(), 'fees' => (int) (clone $invoices)->sum('fee'), 'vat' => (int) (clone $invoices)->sum('vat'), 'invoiced' => (int) (clone $invoices)->sum('total'),
            'payouts' => (clone $payouts)->count(), 'paidToSellers' => (int) (clone $payouts)->sum('amount'),
        ];

        return Inertia::render('admin/reports/finance', [
            'from' => $from->setTimezone($tz)->toDateString(), 'to' => $to->setTimezone($tz)->toDateString(), 'figures' => $figures,
            'checks' => [
                ['label' => 'Refunds paid equal the ledger’s refund entries', 'ok' => $refundsPaid === $figures['refunded'], 'detail' => 'R '.number_format($refundsPaid / 100, 2, '.', ' ').' paid vs R '.number_format($figures['refunded'] / 100, 2, '.', ' ').' in the ledger'],
                ['label' => 'Deposit reconciliation (all time) balances', 'ok' => Reconciliation::run()['balanced'], 'detail' => 'See Finance → Refunds → Reconciliation'],
            ],
            'canExport' => Gate::allows('reports.export'),
        ]);
    }

    public function financeCsv(Request $request, string $report, #[CurrentUser] User $actor): StreamedResponse
    {
        [$from, $to] = $this->period($request);
        $rows = match ($report) {
            'ledger' => DB::table('ledger_entries as e')->join('deposits as d', 'd.id', '=', 'e.deposit_id')->whereBetween('e.created_at', [$from, $to])->orderBy('e.id')
                ->get(['e.created_at', 'd.reference', 'e.type', 'e.amount', 'e.source', 'e.source_ref', 'e.note'])->map(fn ($r) => (array) $r + ['amount_rand' => number_format($r->amount / 100, 2, '.', '')])->all(),
            'invoices' => DB::table('invoices')->whereBetween('issued_at', [$from, $to])->orderBy('number')
                ->get(['number', 'issued_at', 'recipient', 'recipient_vat_number', 'description', 'fee', 'vat', 'total'])->map(fn ($r) => (array) $r)->all(),
            'payouts' => DB::table('payouts')->where('status', 'paid')->whereBetween('paid_at', [$from, $to])->orderBy('paid_at')
                ->get(['paid_at', 'payee', 'deal_type', 'price', 'fee', 'vat', 'amount'])->map(fn ($r) => (array) $r)->all(),
            default => abort(404),
        };
        Audit::record('reports', 'finance_exported', "Exported {$report} report", null, $actor, ['from' => $from->toDateString(), 'to' => $to->toDateString()]);

        return $this->csv("{$report}-{$from->toDateString()}-to-{$to->toDateString()}", $rows);
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function csv(string $name, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            if ($rows !== []) {
                fputcsv($out, array_keys($rows[0]), ',', '"', '\\');
            }
            foreach ($rows as $r) {
                fputcsv($out, array_map(fn ($v) => is_scalar($v) || $v === null ? $v : json_encode($v), array_values($r)), ',', '"', '\\');
            }
            fclose($out);
        }, "{$name}.csv", ['Content-Type' => 'text/csv', 'Cache-Control' => 'no-store, private']);
    }
}
