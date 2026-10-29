import { Head, Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { randCents } from '@/lib/cents';
import { rand } from '@/lib/money';
import { cn } from '@/lib/utils';

type Stage =
    | 'review'
    | 'declined'
    | 'deposit'
    | 'waiting'
    | 'bidding'
    | 'closed';
type Approval = {
    listing: string;
    url: string;
    sealed: boolean;
    closes: string | null;
    opens: string | null;
    status: string;
    statusLabel: string;
    bidderNumber: string | null;
    reason: string | null;
    biddingAs: string | null;
    stage: Stage;
    deposit: { status: string; amount: number } | null;
    depositAmount: number;
    depositUrl: string;
    depositsOpen: boolean;
    highest: number | null;
    bids: number | null;
    myBid: number | null;
    myMaximum: number | null;
    leading: boolean;
    won: boolean;
    advanceMax: number | null;
    checklist: { label: string; state: string }[];
};

const steps = ['Applied', 'Approved', 'Deposit paid', 'Bidding'];
const reached: Record<Stage, number> = {
    declined: 1,
    review: 2,
    deposit: 3,
    waiting: 4,
    bidding: 4,
    closed: 4,
};
const card = 'rounded-[18px] border border-border bg-card';
const button =
    'inline-flex min-h-11 items-center justify-center rounded-[10px] bg-primary px-5 font-semibold text-primary-foreground';
const quiet =
    'inline-flex min-h-11 items-center justify-center rounded-[10px] border border-input px-5 font-semibold';

/** My auctions: every auction applied for, where it stands, and the next thing to do. */
export default function MyAuctions({ approvals }: { approvals: Approval[] }) {
    return (
        <>
            <Head title="My auctions" />
            <div className="mx-auto grid w-full max-w-[1000px] gap-6 px-4 py-8 sm:px-6">
                <div>
                    <h1 className="font-display text-4xl">My auctions</h1>
                    <p className="mt-2 max-w-2xl text-muted-foreground">
                        Every auction you’ve applied for, where it stands, and
                        what to do next. You bid on the auction’s own page once
                        you’re approved and your deposit is paid.
                    </p>
                </div>

                <details className={cn(card, 'p-5')}>
                    <summary className="cursor-pointer font-semibold">
                        How bidding works
                    </summary>
                    <ol className="mt-3 grid list-decimal gap-2 pl-5 text-sm">
                        <li>
                            <strong>Apply to bid.</strong> This registers you
                            for the auction, like registering at an auction
                            house. It isn’t a bid.
                        </li>
                        <li>
                            <strong>Get approved.</strong> We check your
                            verification and give you a bidder number.
                        </li>
                        <li>
                            <strong>Pay the deposit</strong> shown on the
                            listing: online (confirmed straight away), or by EFT
                            with your unique reference (confirmed once it
                            reflects; you can upload your proof of payment).
                            Bidding unlocks as soon as it’s confirmed.
                        </li>
                        <li>
                            <strong>Optional: set an advance maximum</strong>{' '}
                            before bidding opens. From the opening, the system
                            bids for you, only as much as needed.
                        </li>
                        <li>
                            <strong>Bid while the auction is live.</strong>{' '}
                            Enter a bid, or set a maximum and the system bids
                            for you, only as much as needed to keep you in the
                            lead. Every bid is shown by bidder number.
                        </li>
                        <li>
                            <strong>The highest bid wins</strong> when the
                            auction closes. A bid in the final minutes extends
                            the closing time. Bids are binding: you can raise
                            your maximum while you’re leading, but not lower or
                            withdraw a bid.
                        </li>
                    </ol>
                </details>

                {approvals.length === 0 && (
                    <div
                        className={cn(
                            card,
                            'grid justify-items-start gap-3 p-6',
                        )}
                    >
                        <p className="font-semibold">
                            You haven’t applied for any auctions yet.
                        </p>
                        <Link href="/auctions" className={button}>
                            Browse auctions
                        </Link>
                    </div>
                )}

                {approvals.map((a) => (
                    <article
                        key={a.url}
                        className={cn(card, 'grid gap-5 p-5 sm:p-6')}
                    >
                        <header className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <Link
                                    href={a.url}
                                    className="font-display text-2xl underline-offset-4 hover:underline"
                                >
                                    {a.listing}
                                </Link>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {a.sealed
                                        ? 'Sealed tender'
                                        : 'Open auction'}
                                    {a.closes && ` · closes ${a.closes} SAST`}
                                    {a.bidderNumber &&
                                        ` · bidder number ${a.bidderNumber}`}
                                    {a.biddingAs &&
                                        ` · bidding for ${a.biddingAs}`}
                                </p>
                            </div>
                            {a.stage === 'bidding' &&
                                !a.sealed &&
                                a.myBid !== null && (
                                    <span
                                        className={cn(
                                            'rounded-full px-3 py-1 text-sm font-semibold',
                                            a.leading
                                                ? 'bg-status-live/15 text-status-live'
                                                : 'bg-destructive/10 text-destructive',
                                        )}
                                    >
                                        {a.leading
                                            ? 'You’re the highest bidder'
                                            : 'You’ve been outbid'}
                                    </span>
                                )}
                        </header>

                        {a.stage !== 'declined' && (
                            <ol
                                className="grid grid-cols-4 gap-2"
                                aria-label="Progress"
                            >
                                {steps.map((s, i) => {
                                    const done =
                                        i <
                                            reached[a.stage] -
                                                (a.stage === 'closed'
                                                    ? 0
                                                    : 1) || i === 0;
                                    const current =
                                        i === reached[a.stage] - 1 &&
                                        a.stage !== 'closed';

                                    return (
                                        <li key={s} className="grid gap-1.5">
                                            <span
                                                className={cn(
                                                    'h-1.5 rounded-full',
                                                    done
                                                        ? 'bg-primary'
                                                        : current
                                                          ? 'bg-primary/40'
                                                          : 'bg-muted',
                                                )}
                                            />
                                            <span
                                                className={cn(
                                                    'flex items-center gap-1 text-xs',
                                                    done || current
                                                        ? 'font-semibold'
                                                        : 'text-muted-foreground',
                                                )}
                                            >
                                                {done && (
                                                    <Check
                                                        className="size-3.5"
                                                        aria-hidden="true"
                                                    />
                                                )}
                                                {s}
                                            </span>
                                        </li>
                                    );
                                })}
                            </ol>
                        )}

                        {a.stage === 'review' && (
                            <div className="grid gap-2 text-sm">
                                <p>
                                    Your application is being reviewed. We’ll
                                    let you know when you’re approved.
                                </p>
                                {a.checklist.length > 0 && (
                                    <ul className="grid gap-1">
                                        {a.checklist.map((c) => (
                                            <li
                                                key={c.label}
                                                className="flex items-center gap-2"
                                            >
                                                <span
                                                    className={cn(
                                                        'size-2 rounded-full',
                                                        c.state === 'ok'
                                                            ? 'bg-status-live'
                                                            : 'bg-muted-foreground/40',
                                                    )}
                                                    aria-hidden="true"
                                                />
                                                {c.label}
                                                {c.state !== 'ok' && (
                                                    <span className="text-muted-foreground">
                                                        (still needed)
                                                    </span>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        )}

                        {a.stage === 'declined' && (
                            <p className="text-sm">
                                {a.statusLabel}
                                {a.reason ? `: ${a.reason}` : '.'}
                            </p>
                        )}

                        {a.stage === 'deposit' && (
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <p className="text-sm">
                                    You’re approved. Pay the{' '}
                                    <strong>{rand(a.depositAmount)}</strong>{' '}
                                    deposit: online is confirmed straight away;
                                    an EFT once it reflects (you can upload your
                                    proof of payment).
                                    {a.deposit?.status === 'part_paid' &&
                                        ` We’ve received ${randCents(a.deposit.amount)} so far.`}
                                </p>
                                {a.depositsOpen ? (
                                    <Link
                                        href={a.depositUrl}
                                        className={button}
                                    >
                                        Pay deposit
                                    </Link>
                                ) : (
                                    <span className="text-sm text-muted-foreground">
                                        Deposits open once our legal checks are
                                        complete. We’ll email you.
                                    </span>
                                )}
                            </div>
                        )}

                        {a.stage === 'waiting' && (
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <p className="text-sm">
                                    You’re all set. Bidding opens{' '}
                                    {a.opens ? `on ${a.opens} SAST` : 'soon'}.
                                    {a.advanceMax !== null && (
                                        <>
                                            {' '}
                                            Your advance maximum is{' '}
                                            <strong>
                                                {rand(a.advanceMax)}
                                            </strong>
                                            .
                                        </>
                                    )}
                                </p>
                                <Link
                                    href={a.url}
                                    className={a.sealed ? quiet : button}
                                >
                                    {a.sealed
                                        ? 'View auction'
                                        : a.advanceMax !== null
                                          ? 'Change advance maximum'
                                          : 'Set an advance maximum'}
                                </Link>
                            </div>
                        )}

                        {a.stage === 'bidding' && a.sealed && (
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <p className="text-sm">
                                    Submit or review your confidential offer on
                                    the auction page before it closes.
                                </p>
                                <Link href={a.url} className={button}>
                                    Go to sealed tender
                                </Link>
                            </div>
                        )}

                        {a.stage === 'bidding' && !a.sealed && (
                            <div className="grid gap-4">
                                <dl className="grid gap-3 sm:grid-cols-3">
                                    <div className="rounded-xl bg-muted/50 p-3">
                                        <dt className="text-xs text-muted-foreground">
                                            Highest bid
                                        </dt>
                                        <dd className="tabular text-xl font-semibold">
                                            {a.highest !== null
                                                ? rand(a.highest)
                                                : 'No bids yet'}
                                        </dd>
                                    </div>
                                    <div className="rounded-xl bg-muted/50 p-3">
                                        <dt className="text-xs text-muted-foreground">
                                            Your highest bid
                                        </dt>
                                        <dd className="tabular text-xl font-semibold">
                                            {a.myBid !== null
                                                ? rand(a.myBid)
                                                : '—'}
                                        </dd>
                                    </div>
                                    <div className="rounded-xl bg-muted/50 p-3">
                                        <dt className="text-xs text-muted-foreground">
                                            Your maximum
                                        </dt>
                                        <dd className="tabular text-xl font-semibold">
                                            {a.myMaximum !== null
                                                ? rand(a.myMaximum)
                                                : '—'}
                                        </dd>
                                    </div>
                                </dl>
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <p className="text-sm text-muted-foreground">
                                        {a.bids ?? 0} bids so far. Only you can
                                        see your maximum.
                                    </p>
                                    <Link href={a.url} className={button}>
                                        {a.myBid === null
                                            ? 'Place a bid'
                                            : a.leading
                                              ? 'Raise your maximum'
                                              : 'Bid again'}
                                    </Link>
                                </div>
                            </div>
                        )}

                        {a.stage === 'closed' && (
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <p className="text-sm">
                                    {a.won
                                        ? 'Congratulations: you’re the winning bidder. Your next steps and deadlines are in Purchases.'
                                        : 'This auction has closed. If you had a deposit here, it will be refunded to your verified bank account.'}
                                </p>
                                {a.won && (
                                    <Link
                                        href="/account/purchases"
                                        className={button}
                                    >
                                        View purchase
                                    </Link>
                                )}
                            </div>
                        )}
                    </article>
                ))}
            </div>
        </>
    );
}
