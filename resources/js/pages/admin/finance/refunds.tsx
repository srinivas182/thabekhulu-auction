import { Head, Link, router, usePage } from '@inertiajs/react';
import { CheckCircle2, TriangleAlert } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { randCents as rand } from '@/lib/cents';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index as financeIndex } from '@/routes/admin/finance';
import refunds from '@/routes/admin/finance/refunds';

type Props = {
    tab: string;
    held: {
        reference: string;
        listing: string;
        bidder: string;
        held: number;
        method: string;
    }[];
    scheduled: {
        id: number;
        reference: string;
        listing: string;
        bidder: string;
        held: number;
        reason: string;
        dueOn: string;
        due: boolean;
        status: string;
        failure: string | null;
    }[];
    batches: {
        id: number;
        number: string;
        status: string;
        statusLabel: string;
        total: number;
        count: number;
        preparedBy: string;
        approvedBy: string | null;
        preparedAt: string;
    }[];
    reconciliation: {
        totals: { received: number; refunded: number; held: number };
        listings: {
            listing: string;
            deposits: number;
            received: number;
            refunded: number;
            held: number;
        }[];
        issues: string[];
        balanced: boolean;
    } | null;
    reasons: Record<string, string>;
    can: { issue: boolean; approve: boolean };
};

const tabs = [
    ['scheduled', 'Scheduled'],
    ['batches', 'Batches'],
    ['reconciliation', 'Reconciliation'],
] as const;
const opts = { preserveScroll: true };

export default function Refunds({
    tab,
    held,
    scheduled,
    batches,
    reconciliation: rec,
    reasons,
    can,
}: Props) {
    const errors = (usePage().props.errors ?? {}) as Record<string, string>;
    const schedule = (reference: string) => {
        const reason = window.prompt(
            `Reason: ${Object.entries(reasons)
                .map(([k, v]) => `${k} (${v})`)
                .join(', ')}`,
            'unsuccessful',
        );
        if (reason)
            router.post(refunds.schedule().url, { reference, reason }, opts);
    };

    return (
        <>
            <Head title="Refunds" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <Link
                            href={financeIndex()}
                            className="text-sm underline"
                        >
                            Finance
                        </Link>
                        <h1 className="font-display text-3xl">Refunds</h1>
                    </div>
                    {can.issue && tab === 'scheduled' && (
                        <Button
                            onClick={() =>
                                router.post(refunds.prepare().url, {}, opts)
                            }
                        >
                            Prepare a batch of due refunds
                        </Button>
                    )}
                </div>
                {(errors.refund || errors.batch) && (
                    <p role="alert" className="text-sm text-destructive">
                        {errors.refund ?? errors.batch}
                    </p>
                )}

                <nav
                    aria-label="Refund sections"
                    className="flex gap-x-6 border-b border-border text-sm"
                >
                    {tabs.map(([t, label]) => (
                        <Link
                            key={t}
                            href={refunds.index({ query: { tab: t } })}
                            aria-current={tab === t ? 'page' : undefined}
                            className={cn(
                                '-mb-px border-b-2 py-2.5',
                                tab === t
                                    ? 'border-primary font-semibold'
                                    : 'border-transparent text-muted-foreground',
                            )}
                        >
                            {label}
                        </Link>
                    ))}
                </nav>

                {tab === 'scheduled' && (
                    <>
                        <section
                            aria-labelledby="sch"
                            className="rounded-xl border border-border bg-card"
                        >
                            <h2 id="sch" className="px-5 pt-4 font-semibold">
                                Scheduled refunds
                            </h2>
                            <ul className="mt-2 divide-y divide-border">
                                {scheduled.map((r) => (
                                    <li
                                        key={r.id}
                                        className="flex flex-wrap items-center justify-between gap-3 px-5 py-3"
                                    >
                                        <div>
                                            <p className="font-medium">
                                                <span className="font-mono">
                                                    {r.reference}
                                                </span>{' '}
                                                · {r.bidder} · {rand(r.held)}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {r.listing} · {r.reason} ·{' '}
                                                {r.due
                                                    ? 'due now'
                                                    : `due ${r.dueOn}`}
                                                {r.status === 'failed'
                                                    ? ' · failed'
                                                    : ''}
                                            </p>
                                            {r.failure && (
                                                <p className="text-sm text-status-closing">
                                                    {r.failure}
                                                </p>
                                            )}
                                        </div>
                                        {can.issue && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    window.confirm(
                                                        'Cancel this refund and hold the deposit again?',
                                                    ) &&
                                                    router.delete(
                                                        refunds.cancel(r.id)
                                                            .url,
                                                        opts,
                                                    )
                                                }
                                            >
                                                Cancel
                                            </Button>
                                        )}
                                    </li>
                                ))}
                                {scheduled.length === 0 && (
                                    <li className="px-5 py-6 text-center text-muted-foreground">
                                        No refunds scheduled.
                                    </li>
                                )}
                            </ul>
                        </section>
                        <section
                            aria-labelledby="held-h"
                            className="rounded-xl border border-border bg-card"
                        >
                            <h2 id="held-h" className="px-5 pt-4 font-semibold">
                                Deposits held
                            </h2>
                            <ul className="mt-2 divide-y divide-border">
                                {held.map((d) => (
                                    <li
                                        key={d.reference}
                                        className="flex flex-wrap items-center justify-between gap-3 px-5 py-3"
                                    >
                                        <p>
                                            <span className="font-mono">
                                                {d.reference}
                                            </span>{' '}
                                            · {d.bidder} · {rand(d.held)}{' '}
                                            <span className="text-sm text-muted-foreground">
                                                · {d.listing} ·{' '}
                                                {d.method === 'eft'
                                                    ? 'EFT'
                                                    : d.method}
                                            </span>
                                        </p>
                                        {can.issue && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    schedule(d.reference)
                                                }
                                            >
                                                Schedule refund
                                            </Button>
                                        )}
                                    </li>
                                ))}
                                {held.length === 0 && (
                                    <li className="px-5 py-6 text-center text-muted-foreground">
                                        No deposits held.
                                    </li>
                                )}
                            </ul>
                        </section>
                    </>
                )}

                {tab === 'batches' && (
                    <ul className="divide-y divide-border rounded-xl border border-border bg-card">
                        {batches.map((b) => (
                            <li
                                key={b.id}
                                className="flex flex-wrap items-center justify-between gap-3 px-5 py-3"
                            >
                                <div>
                                    <Link
                                        href={refunds.batch(b.id)}
                                        className="font-medium underline-offset-4 hover:underline"
                                    >
                                        {b.number}
                                    </Link>
                                    <p className="text-sm text-muted-foreground">
                                        {b.count} refunds · {rand(b.total)} ·
                                        prepared by {b.preparedBy}{' '}
                                        {formatDateTime(b.preparedAt)}
                                        {b.approvedBy
                                            ? ` · approved by ${b.approvedBy}`
                                            : ''}
                                    </p>
                                </div>
                                <span
                                    className={cn(
                                        'rounded-full px-3 py-1 text-xs font-semibold',
                                        b.status === 'paid'
                                            ? 'bg-status-live/10 text-status-live'
                                            : 'bg-status-closing/10 text-status-closing',
                                    )}
                                >
                                    {b.statusLabel}
                                </span>
                            </li>
                        ))}
                        {batches.length === 0 && (
                            <li className="px-5 py-8 text-center text-muted-foreground">
                                No batches yet.
                            </li>
                        )}
                    </ul>
                )}

                {tab === 'reconciliation' && rec && (
                    <div className="grid gap-5">
                        <p
                            role="status"
                            className={cn(
                                'inline-flex items-center gap-2 font-semibold',
                                rec.balanced
                                    ? 'text-status-live'
                                    : 'text-destructive',
                            )}
                        >
                            {rec.balanced ? (
                                <CheckCircle2
                                    className="size-5"
                                    aria-hidden="true"
                                />
                            ) : (
                                <TriangleAlert
                                    className="size-5"
                                    aria-hidden="true"
                                />
                            )}
                            {rec.balanced
                                ? 'Balanced to the cent'
                                : `${rec.issues.length} difference(s) to resolve`}
                        </p>
                        {rec.issues.length > 0 && (
                            <ul className="grid gap-1 rounded-xl border border-destructive p-4 text-sm">
                                {rec.issues.map((i) => (
                                    <li key={i}>{i}</li>
                                ))}
                            </ul>
                        )}
                        <dl className="grid grid-cols-[repeat(auto-fit,minmax(180px,1fr))] gap-3">
                            {(
                                [
                                    ['Received', rec.totals.received],
                                    ['Refunded', rec.totals.refunded],
                                    ['Held now', rec.totals.held],
                                ] as const
                            ).map(([l, v]) => (
                                <div
                                    key={l}
                                    className="rounded-xl border border-border bg-card p-4"
                                >
                                    <dt className="text-sm text-muted-foreground">
                                        {l}
                                    </dt>
                                    <dd className="tabular text-2xl font-bold">
                                        {rand(v)}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                        <div className="overflow-x-auto rounded-xl border border-border bg-card">
                            <table className="w-full min-w-[640px] text-sm">
                                <thead className="text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-2 font-medium">
                                            Listing
                                        </th>
                                        <th className="text-right font-medium">
                                            Deposits
                                        </th>
                                        <th className="text-right font-medium">
                                            Received
                                        </th>
                                        <th className="text-right font-medium">
                                            Refunded
                                        </th>
                                        <th className="px-4 text-right font-medium">
                                            Held
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rec.listings.map((l) => (
                                        <tr
                                            key={l.listing}
                                            className="border-t border-border"
                                        >
                                            <td className="px-4 py-2">
                                                {l.listing}
                                            </td>
                                            <td className="tabular text-right">
                                                {l.deposits}
                                            </td>
                                            <td className="tabular text-right">
                                                {rand(l.received)}
                                            </td>
                                            <td className="tabular text-right">
                                                {rand(l.refunded)}
                                            </td>
                                            <td className="tabular px-4 text-right">
                                                {rand(l.held)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <div>
                            <a
                                href={refunds.ledger().url}
                                className="text-sm font-semibold underline"
                            >
                                Export the full ledger (CSV)
                            </a>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

Refunds.layout = { breadcrumbs: [{ title: 'Refunds', href: refunds.index() }] };
