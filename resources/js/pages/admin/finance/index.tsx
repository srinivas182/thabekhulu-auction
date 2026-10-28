import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import { randCents as rand } from '@/lib/cents';
import { cn } from '@/lib/utils';
import {
    importMethod as importRoute,
    index,
    resolve,
} from '@/routes/admin/finance';
import providers from '@/routes/admin/finance/providers';
import refundRoutes from '@/routes/admin/finance/refunds';

type Props = {
    tab: string;
    counts: { queue: number };
    totals: { held: number; awaiting: number; received: number };
    deposits: {
        reference: string;
        listing: string;
        bidder: string;
        amount: number;
        paid: number;
        proofUrl: string | null;
        proofAt: string | null;
        status: string;
        statusLabel: string;
        receivedAt: string | null;
    }[];
    queue: {
        id: number;
        date: string;
        amount: number;
        description: string;
        reference: string | null;
        status: string;
        statusLabel: string;
    }[];
    imports: {
        file: string;
        lines: number;
        matched: number;
        queued: number;
        duplicates: number;
        by: string;
        at: string;
    }[];
    can: { reconcile: boolean };
};

const tabs = [
    ['deposits', 'Deposits'],
    ['queue', 'To check'],
    ['imports', 'Statement imports'],
] as const;

export default function Finance({
    tab,
    counts,
    totals,
    deposits,
    queue,
    imports,
    can,
}: Props) {
    const errors = (usePage().props.errors ?? {}) as Record<string, string>;
    const act = (
        id: number,
        action: 'apply' | 'ignore',
        reference?: string,
    ) => {
        const note = window.prompt(
            action === 'apply'
                ? 'Note (why this line belongs to that deposit)'
                : 'Note (e.g. not a deposit, returned to sender)',
        );
        if (note)
            router.post(
                resolve(id).url,
                { action, reference, note },
                { preserveScroll: true },
            );
    };

    return (
        <>
            <Head title="Finance" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="font-display text-3xl">Finance</h1>
                        {(
                            (usePage().props.auth as { permissions?: string[] })
                                .permissions ?? []
                        ).includes('payments.providers') && (
                            <Link
                                href={providers.index()}
                                className="text-sm underline"
                            >
                                Payment providers
                            </Link>
                        )}
                        {(
                            (usePage().props.auth as { permissions?: string[] })
                                .permissions ?? []
                        ).includes('refunds.view') && (
                            <Link
                                href={refundRoutes.index()}
                                className="text-sm underline"
                            >
                                Refunds
                            </Link>
                        )}
                    </div>
                    {can.reconcile && (
                        <Form
                            action={importRoute().url}
                            method="post"
                            className="flex flex-wrap items-center gap-2"
                        >
                            {({ processing }) => (
                                <>
                                    <input
                                        type="file"
                                        name="statement"
                                        accept=".csv,.ofx,.qfx,.txt"
                                        aria-label="Bank statement"
                                        className="text-sm"
                                    />
                                    <Button size="sm" disabled={processing}>
                                        Import statement
                                    </Button>
                                </>
                            )}
                        </Form>
                    )}
                </div>
                {errors.statement && (
                    <p role="alert" className="text-sm text-destructive">
                        {errors.statement}
                    </p>
                )}

                <dl className="grid grid-cols-[repeat(auto-fit,minmax(180px,1fr))] gap-3">
                    <div className="rounded-xl border border-border bg-card p-4">
                        <dt className="text-sm text-muted-foreground">
                            Deposits held
                        </dt>
                        <dd className="tabular text-2xl font-bold">
                            {rand(totals.held)}
                        </dd>
                    </div>
                    <div className="rounded-xl border border-border bg-card p-4">
                        <dt className="text-sm text-muted-foreground">
                            Received
                        </dt>
                        <dd className="tabular text-2xl font-bold">
                            {totals.received}
                        </dd>
                    </div>
                    <div className="rounded-xl border border-border bg-card p-4">
                        <dt className="text-sm text-muted-foreground">
                            Awaiting payment
                        </dt>
                        <dd className="tabular text-2xl font-bold">
                            {totals.awaiting}
                        </dd>
                    </div>
                </dl>

                <nav
                    aria-label="Finance sections"
                    className="flex gap-x-6 border-b border-border text-sm"
                >
                    {tabs.map(([t, label]) => (
                        <Link
                            key={t}
                            href={index({ query: { tab: t } })}
                            aria-current={tab === t ? 'page' : undefined}
                            className={cn(
                                '-mb-px border-b-2 py-2.5',
                                tab === t
                                    ? 'border-primary font-semibold'
                                    : 'border-transparent text-muted-foreground',
                            )}
                        >
                            {label}
                            {t === 'queue' && counts.queue > 0 && (
                                <span className="ml-1.5 rounded-full bg-status-closing/10 px-2 py-0.5 text-xs font-semibold text-status-closing">
                                    {counts.queue}
                                </span>
                            )}
                        </Link>
                    ))}
                </nav>

                {tab === 'deposits' && (
                    <div className="overflow-x-auto rounded-xl border border-border bg-card">
                        <table className="w-full min-w-[760px] text-sm">
                            <thead className="text-left text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-2 font-medium">
                                        Reference
                                    </th>
                                    <th className="font-medium">Listing</th>
                                    <th className="font-medium">Bidder</th>
                                    <th className="text-right font-medium">
                                        Due
                                    </th>
                                    <th className="text-right font-medium">
                                        Paid
                                    </th>
                                    <th className="px-4 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {deposits.map((d) => (
                                    <tr
                                        key={d.reference}
                                        className="border-t border-border"
                                    >
                                        <td className="px-4 py-2 font-mono">
                                            {d.reference}
                                        </td>
                                        <td>{d.listing}</td>
                                        <td>{d.bidder}</td>
                                        <td className="tabular text-right">
                                            {rand(d.amount)}
                                        </td>
                                        <td className="tabular text-right">
                                            {rand(d.paid)}
                                        </td>
                                        <td className="px-4">
                                            {d.statusLabel}
                                            {d.proofUrl && (
                                                <a
                                                    href={d.proofUrl}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="block text-xs font-semibold underline"
                                                >
                                                    Proof of payment (
                                                    {d.proofAt})
                                                </a>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                                {deposits.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-4 py-8 text-center text-muted-foreground"
                                        >
                                            No deposits yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                )}

                {tab === 'queue' && (
                    <ul className="divide-y divide-border rounded-xl border border-border bg-card">
                        {queue.map((l) => (
                            <li
                                key={l.id}
                                className="grid gap-2 px-5 py-3 sm:grid-cols-[1fr_auto] sm:items-center"
                            >
                                <div>
                                    <p className="font-medium">
                                        {rand(l.amount)} · {l.date}{' '}
                                        <span className="text-sm font-normal text-status-closing">
                                            {l.statusLabel}
                                        </span>
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {l.description}
                                        {l.reference && (
                                            <>
                                                {' '}
                                                · reference{' '}
                                                <span className="font-mono">
                                                    {l.reference}
                                                </span>
                                            </>
                                        )}
                                    </p>
                                </div>
                                {can.reconcile && (
                                    <div className="flex gap-2">
                                        <Button
                                            size="sm"
                                            onClick={() => {
                                                const ref =
                                                    l.reference ??
                                                    window.prompt(
                                                        'Deposit reference this payment belongs to',
                                                    );
                                                if (ref)
                                                    act(l.id, 'apply', ref);
                                            }}
                                        >
                                            Apply to deposit
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => act(l.id, 'ignore')}
                                        >
                                            Not a deposit
                                        </Button>
                                    </div>
                                )}
                            </li>
                        ))}
                        {queue.length === 0 && (
                            <li className="px-5 py-8 text-center text-muted-foreground">
                                Nothing to check.
                            </li>
                        )}
                    </ul>
                )}

                {tab === 'imports' && (
                    <ul className="divide-y divide-border rounded-xl border border-border bg-card">
                        {imports.map((i, k) => (
                            <li
                                key={k}
                                className="flex flex-wrap justify-between gap-2 px-5 py-3 text-sm"
                            >
                                <span className="font-medium">{i.file}</span>
                                <span className="text-muted-foreground">
                                    {i.lines} lines · {i.matched} confirmed ·{' '}
                                    {i.queued} to check · {i.duplicates} already
                                    seen · {i.by}, {formatDateTime(i.at)}
                                </span>
                            </li>
                        ))}
                        {imports.length === 0 && (
                            <li className="px-5 py-8 text-center text-muted-foreground">
                                No statements imported yet.
                            </li>
                        )}
                    </ul>
                )}
            </div>
        </>
    );
}

Finance.layout = { breadcrumbs: [{ title: 'Finance', href: index() }] };
