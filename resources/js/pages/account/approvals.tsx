import { Head, Link } from '@inertiajs/react';
import { Check, Circle, Clock } from 'lucide-react';
import { cn } from '@/lib/utils';

type A = {
    listing: string;
    url: string;
    status: string;
    statusLabel: string;
    bidderNumber: string | null;
    reason: string | null;
    biddingAs: string | null;
    checklist: { label: string; state: string }[];
};

export default function Approvals({ approvals }: { approvals: A[] }) {
    return (
        <>
            <Head title="My bidding approvals" />
            <section className="mx-auto max-w-[900px] px-5 py-12 sm:px-8">
                <h1 className="font-display text-4xl">My bidding approvals</h1>
                {approvals.length === 0 && (
                    <p className="mt-6 text-muted-foreground">
                        You haven’t applied to bid yet.{' '}
                        <Link href="/auctions" className="underline">
                            Browse auctions
                        </Link>
                    </p>
                )}
                <ul className="mt-8 grid gap-4">
                    {approvals.map((a) => (
                        <li
                            key={a.url}
                            className="rounded-[18px] border border-border bg-card p-5"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <Link
                                        href={a.url}
                                        className="font-display text-2xl underline-offset-4 hover:underline"
                                    >
                                        {a.listing}
                                    </Link>
                                    {a.biddingAs && (
                                        <p className="text-sm text-muted-foreground">
                                            Bidding for {a.biddingAs}
                                        </p>
                                    )}
                                </div>
                                <span
                                    className={cn(
                                        'rounded-full px-3 py-1 text-sm font-semibold',
                                        a.status === 'approved'
                                            ? 'bg-status-live/10 text-status-live'
                                            : a.status === 'pending'
                                              ? 'bg-status-closing/10 text-status-closing'
                                              : 'bg-muted',
                                    )}
                                >
                                    {a.statusLabel}
                                    {a.bidderNumber
                                        ? ` · bidder #${a.bidderNumber}`
                                        : ''}
                                </span>
                            </div>
                            {a.reason && (
                                <p className="mt-3 rounded-[10px] bg-muted p-3 text-sm">
                                    {a.reason}
                                </p>
                            )}
                            {a.checklist.length > 0 && (
                                <ul className="mt-4 grid gap-1.5 text-sm">
                                    {a.checklist.map((c) => (
                                        <li
                                            key={c.label}
                                            className="flex items-center gap-2"
                                        >
                                            {c.state === 'ok' ? (
                                                <Check
                                                    className="size-4 text-status-live"
                                                    aria-hidden="true"
                                                />
                                            ) : c.state === 'later' ? (
                                                <Clock
                                                    className="size-4 text-muted-foreground"
                                                    aria-hidden="true"
                                                />
                                            ) : (
                                                <Circle
                                                    className="size-4 text-status-closing"
                                                    aria-hidden="true"
                                                />
                                            )}
                                            <span
                                                className={
                                                    c.state === 'later'
                                                        ? 'text-muted-foreground'
                                                        : ''
                                                }
                                            >
                                                {c.label}
                                                {c.state === 'missing'
                                                    ? ' (still needed)'
                                                    : c.state === 'later'
                                                      ? ' (comes later)'
                                                      : ''}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </li>
                    ))}
                </ul>
            </section>
        </>
    );
}
