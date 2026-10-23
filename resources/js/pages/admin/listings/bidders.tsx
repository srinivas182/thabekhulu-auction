import { Head, Link, router, usePage } from '@inertiajs/react';
import { Check, Circle, Clock } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { decide } from '@/routes/admin/bidders';
import { edit, index } from '@/routes/admin/listings';

type Item = {
    key: string;
    label: string;
    state: string;
    detail: string | null;
};
type A = {
    id: number;
    name: string;
    email: string;
    biddingAs: string | null;
    status: string;
    statusLabel: string;
    bidderNumber: string | null;
    reason: string | null;
    appliedAt: string | null;
    checklist: Item[];
    fundsId: number | null;
};
type Props = {
    listing: {
        ulid: string;
        title: string;
        needsFunds: boolean;
        url: string | null;
    };
    approvals: A[];
    can: { approve: boolean };
    downloads: {
        rows: {
            name: string;
            bidderNumber: string | null;
            file: string;
            ip: string | null;
            at: string;
        }[];
        flagged: string[];
    };
};

const opts = { preserveScroll: true };

export default function Bidders({ listing, approvals, can, downloads }: Props) {
    const errors = (usePage().props.errors ?? {}) as Record<string, string>;
    const ask = (q: string, f: (t: string) => void) => {
        const t = window.prompt(q);
        if (t && t.trim()) f(t.trim());
    };

    return (
        <>
            <Head title={`Bidders: ${listing.title}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div>
                    <p className="text-sm">
                        <Link href={edit(listing.ulid)} className="underline">
                            {listing.title}
                        </Link>
                    </p>
                    <h1 className="font-display text-3xl">Bidders</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {listing.needsFunds
                            ? 'Proof of funds is required for this listing.'
                            : 'Proof of funds is not required for this listing.'}{' '}
                        Bidders appear publicly only by their number.
                    </p>
                </div>
                {errors.decision && (
                    <p
                        role="alert"
                        className="rounded-md border border-destructive px-4 py-3 text-sm text-destructive"
                    >
                        {errors.decision}
                    </p>
                )}

                <ul className="grid gap-4">
                    {approvals.map((a) => {
                        const ready = a.checklist.every(
                            (i) => i.state !== 'missing',
                        );

                        return (
                            <li
                                key={a.id}
                                className="rounded-xl border border-border bg-card p-5"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p className="font-semibold">
                                            {a.name}
                                            {a.biddingAs && (
                                                <span className="font-normal text-muted-foreground">
                                                    {' '}
                                                    for {a.biddingAs}
                                                </span>
                                            )}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {a.email} · applied{' '}
                                            {formatDateTime(a.appliedAt)}
                                        </p>
                                    </div>
                                    <span
                                        className={cn(
                                            'rounded-full px-3 py-1 text-xs font-semibold',
                                            a.status === 'approved'
                                                ? 'bg-status-live/10 text-status-live'
                                                : a.status === 'pending'
                                                  ? 'bg-status-closing/10 text-status-closing'
                                                  : 'bg-muted',
                                        )}
                                    >
                                        {a.statusLabel}
                                        {a.bidderNumber
                                            ? ` · #${a.bidderNumber}`
                                            : ''}
                                    </span>
                                </div>
                                <ul className="mt-3 grid gap-1.5 text-sm">
                                    {a.checklist.map((i) => (
                                        <li
                                            key={i.key}
                                            className="flex flex-wrap items-center gap-2"
                                        >
                                            {i.state === 'ok' ? (
                                                <Check
                                                    className="size-4 text-status-live"
                                                    aria-hidden="true"
                                                />
                                            ) : i.state === 'later' ? (
                                                <Clock
                                                    className="size-4 text-muted-foreground"
                                                    aria-hidden="true"
                                                />
                                            ) : (
                                                <Circle
                                                    className="size-4 text-destructive"
                                                    aria-hidden="true"
                                                />
                                            )}
                                            <span className="font-medium">
                                                {i.label}
                                            </span>
                                            {i.detail && (
                                                <span className="text-muted-foreground">
                                                    · {i.detail}
                                                </span>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                                {a.reason && (
                                    <p className="mt-3 rounded-md bg-muted p-3 text-sm">
                                        {a.reason}
                                    </p>
                                )}
                                {can.approve && (
                                    <div className="mt-4 flex flex-wrap gap-2">
                                        {a.status === 'pending' && (
                                            <Button
                                                size="sm"
                                                disabled={!ready}
                                                onClick={() =>
                                                    router.post(
                                                        decide(a.id).url,
                                                        { decision: 'approve' },
                                                        opts,
                                                    )
                                                }
                                            >
                                                Approve bidder
                                            </Button>
                                        )}
                                        {a.status === 'pending' && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    ask(
                                                        'Reason (emailed to the buyer)',
                                                        (reason) =>
                                                            router.post(
                                                                decide(a.id)
                                                                    .url,
                                                                {
                                                                    decision:
                                                                        'reject',
                                                                    reason,
                                                                },
                                                                opts,
                                                            ),
                                                    )
                                                }
                                            >
                                                Reject
                                            </Button>
                                        )}
                                        {a.status === 'approved' && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    ask(
                                                        'Why is this approval being withdrawn? (emailed to the buyer)',
                                                        (reason) =>
                                                            router.post(
                                                                decide(a.id)
                                                                    .url,
                                                                {
                                                                    decision:
                                                                        'revoke',
                                                                    reason,
                                                                },
                                                                opts,
                                                            ),
                                                    )
                                                }
                                            >
                                                Withdraw approval
                                            </Button>
                                        )}
                                        {a.status === 'pending' && !ready && (
                                            <span className="self-center text-sm text-muted-foreground">
                                                Complete the items marked red
                                                first.
                                            </span>
                                        )}
                                    </div>
                                )}
                            </li>
                        );
                    })}
                    {approvals.length === 0 && (
                        <li className="rounded-xl border border-border bg-card px-5 py-8 text-center text-muted-foreground">
                            No applications yet.
                        </li>
                    )}
                </ul>
                <section
                    aria-labelledby="dl-h"
                    className="rounded-xl border border-border bg-card p-5"
                >
                    <h2 id="dl-h" className="font-semibold">
                        Document downloads
                    </h2>
                    {downloads.flagged.length > 0 && (
                        <p
                            role="alert"
                            className="mt-2 rounded-md border border-status-closing px-3 py-2 text-sm text-status-closing"
                        >
                            Unusually many downloads in the last 24 hours:{' '}
                            {downloads.flagged.join(', ')}
                        </p>
                    )}
                    {downloads.rows.length === 0 ? (
                        <p className="mt-2 text-sm text-muted-foreground">
                            No downloads yet.
                        </p>
                    ) : (
                        <div className="mt-2 overflow-x-auto">
                            <table className="w-full min-w-[640px] text-sm">
                                <thead className="text-left text-muted-foreground">
                                    <tr>
                                        <th className="py-1.5 font-medium">
                                            When
                                        </th>
                                        <th className="font-medium">Bidder</th>
                                        <th className="font-medium">
                                            Document
                                        </th>
                                        <th className="font-medium">
                                            IP address
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {downloads.rows.map((r, i) => (
                                        <tr
                                            key={i}
                                            className="border-t border-border"
                                        >
                                            <td className="py-1.5">
                                                {formatDateTime(r.at)}
                                            </td>
                                            <td>
                                                {r.name}
                                                {r.bidderNumber
                                                    ? ` (#${r.bidderNumber})`
                                                    : ''}
                                            </td>
                                            <td>{r.file}</td>
                                            <td className="font-mono text-xs">
                                                {r.ip}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

Bidders.layout = { breadcrumbs: [{ title: 'Listings', href: index() }] };
