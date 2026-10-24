import { Head, Link, router, usePoll } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { rand } from '@/lib/money';
import { cn } from '@/lib/utils';
import { voidMethod as voidRoute } from '@/routes/admin/bids';
import { bidders, biddersRecord, edit, index } from '@/routes/admin/listings';
import { pause } from '@/routes/admin/listings/auction';
import tenderRoutes from '@/routes/admin/listings/tender';

type Props = {
    listing: { ulid: string; title: string; minimum: number };
    state: {
        current: number | null;
        leader: string | null;
        endsAt: string;
        extensions: number;
        paused: boolean;
        closed: boolean;
    };
    bids: {
        id: number;
        number: string;
        name: string;
        amount: number;
        kind: string;
        time: string;
        ip: string | null;
        voided: boolean;
        voidReason: string | null;
    }[];
    can: { pause: boolean; void: boolean; tender: boolean };
    kind: string;
    status: string;
    result: {
        outcome: string;
        winner: string | null;
        amount: number | null;
        runnerUp: string | null;
        runnerUpAmount: number | null;
        bidders: number;
        closedAt: string;
    } | null;
    tender: {
        count: number;
        deadlinePassed: boolean;
        requestedBy: string | null;
        requestedByMe: boolean;
        opened: boolean;
        offers: {
            bidder: string;
            amount: number;
            at: string;
            revisions: number;
        }[];
    } | null;
    flags: { kind: string; detail: string; bidders: string[] }[];
};

export default function AuctionMonitor({
    listing,
    state,
    bids,
    can,
    kind,
    status,
    result,
    tender,
    flags,
}: Props) {
    usePoll(5000, { only: ['state', 'bids'] }, { autoStart: !state.closed });
    const doVoid = (id: number) => {
        const reason = window.prompt(
            'Why is this bid being voided? (recorded and sent to the bidder)',
        );
        if (reason)
            router.post(
                voidRoute(id).url,
                { reason },
                { preserveScroll: true },
            );
    };

    return (
        <>
            <Head title={`Auction: ${listing.title}`} />
            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <Link
                            href={edit(listing.ulid)}
                            className="text-sm underline"
                        >
                            {listing.title}
                        </Link>
                        <h1 className="font-display text-3xl">Live auction</h1>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Link
                            href={bidders(listing.ulid)}
                            className="inline-flex h-9 items-center rounded-md border border-input px-3 text-sm font-medium"
                        >
                            Bidders
                        </Link>
                        <a
                            href={biddersRecord(listing.ulid).url}
                            className="inline-flex h-9 items-center rounded-md border border-input px-3 text-sm font-medium"
                        >
                            Bidders’ record (CSV)
                        </a>
                        {can.pause && !state.closed && (
                            <Button
                                variant={state.paused ? 'default' : 'outline'}
                                onClick={() =>
                                    window.confirm(
                                        state.paused
                                            ? 'Resume bidding? The close moves by the paused time.'
                                            : 'Pause bidding now?',
                                    ) &&
                                    router.post(
                                        pause(listing.ulid).url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {state.paused
                                    ? 'Resume bidding'
                                    : 'Pause bidding'}
                            </Button>
                        )}
                    </div>
                </div>

                <dl className="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-3">
                    <div className="rounded-xl border border-border bg-card p-4">
                        <dt className="text-sm text-muted-foreground">
                            Current bid
                        </dt>
                        <dd className="tabular text-2xl font-bold">
                            {state.current
                                ? rand(state.current)
                                : `No bids (min ${rand(listing.minimum)})`}
                        </dd>
                    </div>
                    <div className="rounded-xl border border-border bg-card p-4">
                        <dt className="text-sm text-muted-foreground">
                            Leading
                        </dt>
                        <dd className="text-lg font-semibold">
                            {state.leader ?? '—'}
                        </dd>
                    </div>
                    <div className="rounded-xl border border-border bg-card p-4">
                        <dt className="text-sm text-muted-foreground">
                            Closes (SAST)
                        </dt>
                        <dd className="text-lg font-semibold">
                            {state.endsAt}
                            {state.extensions > 0 && (
                                <span className="text-sm font-normal text-muted-foreground">
                                    {' '}
                                    · extended {state.extensions}×
                                </span>
                            )}
                        </dd>
                    </div>
                    <div className="rounded-xl border border-border bg-card p-4">
                        <dt className="text-sm text-muted-foreground">
                            Status
                        </dt>
                        <dd
                            className={cn(
                                'text-lg font-semibold',
                                state.paused
                                    ? 'text-status-closing'
                                    : state.closed
                                      ? ''
                                      : 'text-status-live',
                            )}
                        >
                            {state.paused
                                ? 'Paused'
                                : state.closed
                                  ? 'Closed'
                                  : 'Open'}
                        </dd>
                    </div>
                </dl>

                {result && (
                    <section
                        aria-labelledby="res-h"
                        className="grid gap-2 rounded-xl border border-border bg-card p-5"
                    >
                        <h2 id="res-h" className="font-semibold">
                            Result{' '}
                            <span className="font-normal text-muted-foreground">
                                · {status} · closed {result.closedAt} ·{' '}
                                {result.bidders} bidders
                            </span>
                        </h2>
                        {result.outcome === 'sold' ? (
                            <dl className="grid gap-1 text-sm sm:grid-cols-2">
                                <div>
                                    <dt className="text-muted-foreground">
                                        Winner
                                    </dt>
                                    <dd className="font-semibold">
                                        {result.winner} ·{' '}
                                        {rand(result.amount ?? 0)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Runner-up (deposit held)
                                    </dt>
                                    <dd>
                                        {result.runnerUp
                                            ? `${result.runnerUp} · ${rand(result.runnerUpAmount ?? 0)}`
                                            : 'None'}
                                    </dd>
                                </div>
                            </dl>
                        ) : (
                            <p className="text-sm">
                                Closed without a valid bid.
                            </p>
                        )}
                        <p className="text-sm text-muted-foreground">
                            Unsuccessful bidders’ deposits are scheduled for
                            refund in Finance → Refunds.
                        </p>
                    </section>
                )}

                {kind === 'sealed' && tender && (
                    <section
                        aria-labelledby="ten-h"
                        className="grid gap-3 rounded-xl border border-border bg-card p-5"
                    >
                        <h2 id="ten-h" className="font-semibold">
                            Sealed tender
                        </h2>
                        {!tender.opened ? (
                            <>
                                <p className="text-sm">
                                    {tender.count} sealed{' '}
                                    {tender.count === 1 ? 'offer' : 'offers'}.
                                    Amounts stay unreadable until two people
                                    open the tender after the deadline.
                                </p>
                                {can.tender &&
                                    tender.deadlinePassed &&
                                    !tender.requestedBy && (
                                        <Button
                                            className="w-fit"
                                            onClick={() =>
                                                router.post(
                                                    tenderRoutes.request(
                                                        listing.ulid,
                                                    ).url,
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Request opening
                                        </Button>
                                    )}
                                {tender.requestedBy &&
                                    (tender.requestedByMe ? (
                                        <p className="rounded-md bg-muted px-3 py-2 text-sm">
                                            You requested the opening. A second
                                            person must confirm it.
                                        </p>
                                    ) : (
                                        can.tender && (
                                            <Button
                                                className="w-fit"
                                                onClick={() =>
                                                    window.confirm(
                                                        'Open the tender now? This reveals every offer and records the result.',
                                                    ) &&
                                                    router.post(
                                                        tenderRoutes.confirm(
                                                            listing.ulid,
                                                        ).url,
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                Confirm opening (requested by{' '}
                                                {tender.requestedBy})
                                            </Button>
                                        )
                                    ))}
                                {!tender.deadlinePassed && (
                                    <p className="text-sm text-muted-foreground">
                                        The tender can be opened after its
                                        deadline.
                                    </p>
                                )}
                            </>
                        ) : (
                            <ol className="grid gap-1 text-sm">
                                {tender.offers.map((o, i) => (
                                    <li
                                        key={i}
                                        className="flex justify-between gap-3"
                                    >
                                        <span>
                                            {i + 1}. {o.bidder}
                                            {o.revisions
                                                ? ` (raised ${o.revisions}×)`
                                                : ''}
                                        </span>
                                        <span className="tabular font-semibold">
                                            {rand(o.amount)}
                                        </span>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </section>
                )}

                <section
                    aria-labelledby="flags-h"
                    className="grid gap-2 rounded-xl border border-border bg-card p-5"
                >
                    <h2 id="flags-h" className="font-semibold">
                        Bidding integrity checks
                    </h2>
                    {flags.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No patterns to review.
                        </p>
                    ) : (
                        <ul className="grid gap-2 text-sm">
                            {flags.map((f, i) => (
                                <li
                                    key={i}
                                    className="rounded-md border border-status-closing px-3 py-2"
                                >
                                    <strong>{f.kind}:</strong> {f.detail}.{' '}
                                    <span className="text-muted-foreground">
                                        {f.bidders.join(', ')}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                    <p className="text-xs text-muted-foreground">
                        These are flags for a person to review, not findings.
                    </p>
                </section>
                <p className="text-sm text-muted-foreground">
                    Names are shown here for staff only; the public feed shows
                    bidder numbers. Bidders’ maximums are private and not shown.
                </p>

                <div className="overflow-x-auto rounded-xl border border-border bg-card">
                    <table className="w-full min-w-[720px] text-sm">
                        <thead className="text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-2 font-medium">Time</th>
                                <th className="font-medium">Bidder</th>
                                <th className="text-right font-medium">
                                    Amount
                                </th>
                                <th className="pl-6 font-medium">Type</th>
                                <th className="font-medium">IP address</th>
                                <th className="px-4" />
                            </tr>
                        </thead>
                        <tbody>
                            {bids.map((b) => (
                                <tr
                                    key={b.id}
                                    className={cn(
                                        'border-t border-border',
                                        b.voided &&
                                            'text-muted-foreground line-through',
                                    )}
                                >
                                    <td className="tabular px-4 py-2">
                                        {b.time}
                                    </td>
                                    <td>
                                        #{b.number} {b.name}
                                    </td>
                                    <td className="tabular text-right font-semibold">
                                        {rand(b.amount)}
                                    </td>
                                    <td className="pl-6">
                                        {b.kind === 'proxy'
                                            ? 'Automatic (maximum)'
                                            : 'Bid'}
                                    </td>
                                    <td className="font-mono text-xs">
                                        {b.ip}
                                    </td>
                                    <td className="px-4 text-right">
                                        {b.voided ? (
                                            <span className="text-xs no-underline">
                                                Voided: {b.voidReason}
                                            </span>
                                        ) : (
                                            can.void &&
                                            !state.closed && (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() => doVoid(b.id)}
                                                >
                                                    Void
                                                </Button>
                                            )
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {bids.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No bids yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

AuctionMonitor.layout = { breadcrumbs: [{ title: 'Listings', href: index() }] };
