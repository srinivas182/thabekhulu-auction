import { Head, Link, router, usePoll } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { rand } from '@/lib/money';
import { cn } from '@/lib/utils';
import { voidMethod as voidRoute } from '@/routes/admin/bids';
import { bidders, biddersRecord, edit, index } from '@/routes/admin/listings';
import { pause } from '@/routes/admin/listings/auction';

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
    can: { pause: boolean; void: boolean };
};

export default function AuctionMonitor({ listing, state, bids, can }: Props) {
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
                                <th className="font-medium">Type</th>
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
                                    <td>
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
