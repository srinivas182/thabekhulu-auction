<?php

namespace App\Domains\Quotes\Http\Controllers;

use App\Domains\Audit\Support\Audit;
use App\Domains\Quotes\Models\Quote;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Pipeline reports (quotes.view_all). Superseded versions are left out of counts. */
class QuoteReportController extends Controller
{
    public function index(): Response
    {
        $current = Quote::where('status', '!=', 'superseded');
        $byStatus = (clone $current)->selectRaw('status, count(*) as n, sum(once_total) as total')->groupBy('status')->get()
            ->mapWithKeys(fn (Quote $r) => [$r->status => ['count' => (int) $r->getAttribute('n'), 'total' => (int) $r->getAttribute('total')]]);

        $months = collect(range(5, 0))->map(function (int $ago): array {
            $start = now()->subMonthsNoOverflow($ago)->startOfMonth();
            $sent = Quote::whereNotNull('sent_at')->whereBetween('sent_at', [$start, $start->copy()->endOfMonth()])->where('status', '!=', 'superseded');
            $total = (clone $sent)->count();
            $accepted = (clone $sent)->where('status', 'accepted')->count();

            return ['month' => $start->format('M Y'), 'sent' => $total, 'accepted' => $accepted, 'rate' => $total > 0 ? round($accepted / $total * 100) : null];
        });

        return Inertia::render('admin/quotes/reports', [
            'statuses' => Quote::STATUSES,
            'byStatus' => $byStatus,
            'inProgress' => (int) (clone $current)->whereIn('status', ['sent', 'viewed'])->sum('once_total'),
            'months' => $months,
            'expiring' => Quote::with('client:id,name,company')->whereIn('status', ['sent', 'viewed'])
                ->whereBetween('valid_until', [now()->toDateString(), now()->addDays(7)->toDateString()])->orderBy('valid_until')->get()
                ->map(fn (Quote $q): array => ['ulid' => $q->ulid, 'reference' => $q->reference(), 'client' => $q->client->company ?: $q->client->name, 'total' => $q->once_total, 'validUntil' => $q->valid_until->toDateString()]),
        ]);
    }

    public function export(#[CurrentUser] User $user): StreamedResponse
    {
        Audit::record('quotes', 'exported', 'Exported quotations to CSV', null, $user);

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['Reference', 'Title', 'Client', 'Status', 'Issued', 'Valid until', 'Once-off total (R)', 'Monthly (R)', 'Annual (R)', 'Sent', 'Accepted by', 'Accepted at']);
            Quote::with('client:id,name,company')->orderBy('number')->orderBy('version')->each(function (Quote $q) use ($out): void {
                fputcsv($out, [
                    $q->reference(), $q->title, $q->client->company ?: $q->client->name, Quote::STATUSES[$q->status] ?? $q->status,
                    $q->issue_date->toDateString(), $q->valid_until->toDateString(), number_format($q->once_total / 100, 2, '.', ''),
                    number_format($q->monthly_total / 100, 2, '.', ''), number_format($q->annual_total / 100, 2, '.', ''),
                    $q->sent_at?->toDateTimeString(), $q->accepted_name, $q->accepted_at?->toDateTimeString(),
                ]);
            });
            fclose($out);
        }, 'quotations-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
