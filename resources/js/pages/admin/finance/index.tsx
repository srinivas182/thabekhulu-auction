import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatDateTime } from '@/lib/format';
import { randCents as rand } from '@/lib/cents';
import { cn } from '@/lib/utils';
import {
    importMethod as importRoute,
    index,
    resolve,
} from '@/routes/admin/finance';
import { settings as financeSettings } from '@/routes/admin/finance';
import providers from '@/routes/admin/finance/providers';
import refundRoutes from '@/routes/admin/finance/refunds';
import confirmRoutes from '@/routes/admin/confirmations';

type Balance = {
    ulid: string;
    reference: string;
    listing: string;
    buyer: string;
    due: number;
    received: number;
    status: string;
    statusLabel: string;
    balanceDue: string | null;
    proofUrl: string | null;
    proofAt: string | null;
};
type Confirmation = {
    id: number;
    kind: string;
    amount: number;
    bankReference: string;
    paidOn: string;
    note: string | null;
    by: string;
    mine: boolean;
    target: string;
};
type Props = {
    balances: Balance[];
    confirmations: Confirmation[];
    trustAccountReady: boolean;
    tab: string;
    counts: { queue: number };
    totals: { held: number; awaiting: number; received: number };
    deposits: {
        ulid: string;
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
    ['balances', 'Balances'],
    ['queue', 'To check'],
    ['imports', 'Statement imports'],
] as const;

export default function Finance({
    tab,
    counts,
    totals,
    deposits,
    balances,
    confirmations,
    trustAccountReady,
    queue,
    imports,
    can,
}: Props) {
    const perms =
        (usePage().props.auth as { permissions?: string[] }).permissions ?? [];
    const [rejectingConf, setRejectingConf] = useState<number | null>(null);
    const [rejectNote, setRejectNote] = useState('');
    const [confirming, setConfirming] = useState<{
        kind: 'deposit' | 'balance';
        target: string;
        label: string;
    } | null>(null);
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
                        <nav
                            aria-label="Finance setup"
                            className="flex flex-wrap gap-2"
                        >
                            {perms.includes('payments.providers') && (
                                <Link
                                    href={providers.index()}
                                    className="rounded-[10px] border border-input bg-card px-3 py-1.5 text-sm font-semibold hover:bg-muted"
                                >
                                    Payment providers
                                </Link>
                            )}
                            {perms.includes('settings.manage') && (
                                <Link
                                    href={financeSettings().url}
                                    className="rounded-[10px] border border-input bg-card px-3 py-1.5 text-sm font-semibold hover:bg-muted"
                                >
                                    Trust account &amp; settings
                                </Link>
                            )}
                            {perms.includes('refunds.view') && (
                                <Link
                                    href={refundRoutes.index()}
                                    className="rounded-[10px] border border-input bg-card px-3 py-1.5 text-sm font-semibold hover:bg-muted"
                                >
                                    Refunds
                                </Link>
                            )}
                        </nav>
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

                {!trustAccountReady && perms.includes('settings.manage') && (
                    <p
                        role="alert"
                        className="rounded-[10px] bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-100"
                    >
                        <strong>Buyers can’t see where to pay by EFT.</strong>{' '}
                        The trust account’s bank, account number or branch code
                        is missing.{' '}
                        <Link
                            href={financeSettings().url}
                            className="font-semibold underline"
                        >
                            Complete the trust account
                        </Link>
                    </p>
                )}
                {confirmations.length > 0 && (
                    <section
                        aria-labelledby="conf-h"
                        className="grid gap-2 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-50"
                    >
                        <h2 id="conf-h" className="font-semibold">
                            Waiting for a second approval
                        </h2>
                        <ul className="grid gap-2">
                            {confirmations.map((cf) => (
                                <li
                                    key={cf.id}
                                    className="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <span>
                                        {cf.kind === 'deposit'
                                            ? 'Deposit'
                                            : 'Balance'}{' '}
                                        {cf.target}:{' '}
                                        <strong>{rand(cf.amount)}</strong>, bank
                                        ref {cf.bankReference}, paid {cf.paidOn}
                                        , recorded by {cf.by}
                                        {cf.note ? ` (${cf.note})` : ''}
                                    </span>
                                    <span className="flex gap-2">
                                        <Button
                                            size="sm"
                                            disabled={cf.mine}
                                            title={
                                                cf.mine
                                                    ? 'A different person must approve this'
                                                    : undefined
                                            }
                                            onClick={() =>
                                                router.post(
                                                    confirmRoutes.approve(cf.id)
                                                        .url,
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Approve
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => {
                                                setRejectNote('');
                                                setRejectingConf(cf.id);
                                            }}
                                        >
                                            Reject
                                        </Button>
                                    </span>
                                </li>
                            ))}
                        </ul>
                        {errors.confirmation && (
                            <p className="text-destructive">
                                {errors.confirmation}
                            </p>
                        )}
                    </section>
                )}

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
                                            {can.reconcile &&
                                                [
                                                    'awaiting',
                                                    'part_paid',
                                                ].includes(d.status) && (
                                                    <button
                                                        type="button"
                                                        className="block text-xs font-semibold underline"
                                                        onClick={() =>
                                                            setConfirming({
                                                                kind: 'deposit',
                                                                target: d.ulid,
                                                                label: `deposit ${d.reference}`,
                                                            })
                                                        }
                                                    >
                                                        Confirm received
                                                    </button>
                                                )}
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

                {tab === 'balances' && (
                    <div className="overflow-x-auto rounded-xl border border-border bg-card">
                        <table className="w-full min-w-[820px] text-sm">
                            <thead className="text-left text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-2 font-medium">
                                        Reference
                                    </th>
                                    <th className="font-medium">Listing</th>
                                    <th className="font-medium">Buyer</th>
                                    <th className="text-right font-medium">
                                        Due
                                    </th>
                                    <th className="text-right font-medium">
                                        Received
                                    </th>
                                    <th className="px-4 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {balances.map((b) => (
                                    <tr
                                        key={b.reference}
                                        className="border-t border-border"
                                    >
                                        <td className="px-4 py-2 font-mono">
                                            {b.reference}
                                        </td>
                                        <td>{b.listing}</td>
                                        <td>{b.buyer}</td>
                                        <td className="tabular text-right">
                                            {rand(b.due)}
                                            <span className="block text-xs text-muted-foreground">
                                                by {b.balanceDue}
                                            </span>
                                        </td>
                                        <td className="tabular text-right">
                                            {rand(b.received)}
                                        </td>
                                        <td className="px-4">
                                            {b.statusLabel}
                                            {can.reconcile &&
                                                b.status !== 'received' && (
                                                    <button
                                                        type="button"
                                                        className="block text-xs font-semibold underline"
                                                        onClick={() =>
                                                            setConfirming({
                                                                kind: 'balance',
                                                                target: b.ulid,
                                                                label: `balance ${b.reference}`,
                                                            })
                                                        }
                                                    >
                                                        Confirm received
                                                    </button>
                                                )}
                                            {b.proofUrl && (
                                                <a
                                                    href={b.proofUrl}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="block text-xs font-semibold underline"
                                                >
                                                    Proof of payment (
                                                    {b.proofAt})
                                                </a>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                                {balances.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-4 py-8 text-center text-muted-foreground"
                                        >
                                            No balances yet. They appear when a
                                            winner opens their purchase.
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
            <Dialog
                open={confirming !== null}
                onOpenChange={(o) => !o && setConfirming(null)}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="font-display text-2xl">
                            Confirm money received
                        </DialogTitle>
                        <DialogDescription>
                            For {confirming?.label}. Record what you see in the
                            bank account; a second finance user must approve it
                            before it counts.
                        </DialogDescription>
                    </DialogHeader>
                    <Form
                        action={confirmRoutes.store().url}
                        method="post"
                        options={{ preserveScroll: true }}
                        onSuccess={() => setConfirming(null)}
                        className="grid gap-3 text-sm"
                    >
                        {({ processing, errors: e }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="kind"
                                    value={confirming?.kind ?? ''}
                                />
                                <input
                                    type="hidden"
                                    name="target"
                                    value={confirming?.target ?? ''}
                                />
                                <label className="grid gap-1 font-medium">
                                    Amount received (R)
                                    <input
                                        name="amount"
                                        inputMode="decimal"
                                        className="h-10 rounded-md border border-input bg-background px-3 font-normal"
                                    />
                                </label>
                                {e.amount && (
                                    <span className="text-destructive">
                                        {e.amount}
                                    </span>
                                )}
                                <label className="grid gap-1 font-medium">
                                    Bank reference or statement description
                                    <input
                                        name="bank_reference"
                                        className="h-10 rounded-md border border-input bg-background px-3 font-normal"
                                    />
                                </label>
                                {e.bank_reference && (
                                    <span className="text-destructive">
                                        {e.bank_reference}
                                    </span>
                                )}
                                <label className="grid gap-1 font-medium">
                                    Date paid
                                    <input
                                        type="date"
                                        name="paid_on"
                                        className="h-10 rounded-md border border-input bg-background px-3 font-normal"
                                    />
                                </label>
                                {e.paid_on && (
                                    <span className="text-destructive">
                                        {e.paid_on}
                                    </span>
                                )}
                                <label className="grid gap-1 font-medium">
                                    Note (optional)
                                    <input
                                        name="note"
                                        className="h-10 rounded-md border border-input bg-background px-3 font-normal"
                                    />
                                </label>
                                {e.confirmation && (
                                    <span className="text-destructive">
                                        {e.confirmation}
                                    </span>
                                )}
                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setConfirming(null)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button disabled={processing}>
                                        Record for approval
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
            <Dialog
                open={rejectingConf !== null}
                onOpenChange={(o) => !o && setRejectingConf(null)}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="font-display text-2xl">
                            Reject this confirmation
                        </DialogTitle>
                        <DialogDescription>
                            The payment stays outstanding. Say why, for the
                            record.
                        </DialogDescription>
                    </DialogHeader>
                    <textarea
                        aria-label="Reason"
                        rows={3}
                        value={rejectNote}
                        onChange={(e) => setRejectNote(e.target.value)}
                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setRejectingConf(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={rejectNote.trim() === ''}
                            onClick={() => {
                                if (rejectingConf !== null)
                                    router.post(
                                        confirmRoutes.reject(rejectingConf).url,
                                        { note: rejectNote.trim() },
                                        { preserveScroll: true },
                                    );
                                setRejectingConf(null);
                            }}
                        >
                            Reject
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

Finance.layout = { breadcrumbs: [{ title: 'Finance', href: index() }] };
