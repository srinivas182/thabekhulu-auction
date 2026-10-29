import { router, usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useEffect, useState } from 'react';
import { rand } from '@/lib/money';
import { bid } from '@/routes/auctions';

export type AuctionLive = {
    kind?: 'open' | 'sealed';
    outcome?: 'under_offer' | 'sold' | 'not_sold' | 'no_bids' | 'closed' | null;
    mining?: boolean;
    result?: {
        price: number;
        date: string;
        bidderNumber: string | null;
    } | null;
    minimum?: number;
    current: number | null;
    leaderNumber?: string | null;
    next: number;
    endsAt: string;
    endsAtText: string;
    extensions: number;
    bidCount: number;
    paused: boolean;
    closed: boolean;
    otpFrom: number;
    recent: { number: string; amount: number; kind: string; time: string }[];
    mine: {
        bidderNumber: string | null;
        canBid: boolean;
        leading: boolean;
        hasBid: boolean;
        offer?: number | null;
    } | null;
};

const field =
    'h-12 w-full min-w-0 rounded-[10px] border border-input bg-card px-3 text-lg tabular';
const btn =
    'flex min-h-[52px] w-full items-center justify-center rounded-[10px] bg-primary px-5 text-[17px] font-semibold text-primary-foreground disabled:opacity-60';

/** Enter → review → (code) → placed. Follows the approved "Bid confirmation" design. */
export default function BidForm({
    auction,
    ulid,
    depositTerms,
}: {
    auction: AuctionLive;
    ulid: string;
    depositTerms: string;
}) {
    const errors = (usePage().props.errors ?? {}) as Record<string, string>;
    const [amount, setAmount] = useState(String(auction.next));
    const [typed, setTyped] = useState(false);
    // Keep the suggested amount at the live minimum until the bidder types their own.
    useEffect(() => {
        if (!typed) setAmount(String(auction.next));
    }, [auction.next, typed]);
    const [useMax, setUseMax] = useState(false);
    const [maximum, setMaximum] = useState('');
    const [step, setStep] = useState<'enter' | 'review'>('enter');
    const [otp, setOtp] = useState('');
    const [busy, setBusy] = useState(false);
    const mine = auction.mine!;
    const value = Number(amount.replace(/\D/g, '')) || 0;
    const max = useMax ? Number(maximum.replace(/\D/g, '')) || 0 : null;
    const needsCode = Math.max(value, max ?? 0) >= auction.otpFrom;

    if (auction.paused)
        return (
            <p role="status" className="rounded-[10px] bg-muted p-4 text-sm">
                Bidding is paused by the auctioneer. It will resume shortly, and
                the closing time is extended by the pause.
            </p>
        );
    if (auction.closed)
        return (
            <p role="status" className="rounded-[10px] bg-muted p-4 text-sm">
                This auction has closed.
            </p>
        );

    const submit = () => {
        setBusy(true);
        router.post(
            bid(ulid).url,
            { amount: value, maximum: max, otp: otp || null },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setStep('enter');
                    setTyped(false);
                    setOtp('');
                    setUseMax(false);
                    setMaximum('');
                },
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <div className="grid gap-3 [&_*]:min-w-0">
            <p
                className={
                    mine.leading
                        ? 'inline-flex items-center gap-1.5 font-semibold text-status-live'
                        : 'font-semibold text-foreground'
                }
            >
                {mine.leading ? (
                    <>
                        <Check className="size-4" aria-hidden="true" />
                        You’re the highest bidder (#{mine.bidderNumber})
                    </>
                ) : mine.hasBid ? (
                    `You’ve been outbid (#${mine.bidderNumber})`
                ) : (
                    `You’re bidder #${mine.bidderNumber}`
                )}
            </p>

            {step === 'enter' ? (
                <>
                    <label className="flex flex-col gap-1.5 text-sm font-semibold">
                        Your bid (R)
                        <input
                            inputMode="numeric"
                            value={amount}
                            onChange={(e) => {
                                setTyped(true);
                                setAmount(e.target.value);
                            }}
                            className={field}
                            aria-describedby="next-hint"
                        />
                        <span
                            id="next-hint"
                            className="font-normal text-muted-foreground"
                        >
                            Lowest bid now: {rand(auction.next)}
                        </span>
                        {errors.amount && (
                            <span className="text-destructive">
                                {errors.amount}
                            </span>
                        )}
                    </label>
                    <label className="flex items-start gap-2.5 text-sm">
                        <input
                            type="checkbox"
                            checked={useMax}
                            onChange={(e) => setUseMax(e.target.checked)}
                            className="mt-0.5 size-5 accent-primary"
                        />
                        <span>
                            Set a maximum and let us bid for you, only as much
                            as needed, up to that amount. Nobody, including our
                            staff, can see your maximum.
                        </span>
                    </label>
                    {useMax && (
                        <label className="flex flex-col gap-1.5 text-sm font-semibold">
                            Your maximum (R)
                            <input
                                inputMode="numeric"
                                value={maximum}
                                onChange={(e) => setMaximum(e.target.value)}
                                className={field}
                            />
                            {errors.maximum && (
                                <span className="text-destructive">
                                    {errors.maximum}
                                </span>
                            )}
                        </label>
                    )}
                    {errors.bid && (
                        <p
                            role="alert"
                            className="text-sm font-semibold text-destructive"
                        >
                            {errors.bid}
                        </p>
                    )}
                    <button
                        type="button"
                        className={btn}
                        disabled={value < 1 || (useMax && (max ?? 0) < value)}
                        onClick={() => setStep('review')}
                    >
                        Review bid
                    </button>
                </>
            ) : (
                <section
                    aria-labelledby="review-h"
                    className="grid gap-3 rounded-[14px] border-2 border-primary p-4"
                >
                    <h3 id="review-h" className="font-display text-2xl">
                        Confirm your bid
                    </h3>
                    <dl className="grid gap-1 text-[15px]">
                        <div className="flex justify-between gap-3">
                            <dt className="text-muted-foreground">Bid</dt>
                            <dd className="tabular font-bold">{rand(value)}</dd>
                        </div>
                        {max !== null && (
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">
                                    Maximum
                                </dt>
                                <dd className="tabular font-bold">
                                    {rand(max)}
                                </dd>
                            </div>
                        )}
                    </dl>
                    <p className="text-sm leading-relaxed">
                        Bids are{' '}
                        <strong>binding and cannot be withdrawn</strong>. If you
                        win, you must complete the purchase under the Rules of
                        Auction.{' '}
                        {depositTerms === 'security'
                            ? 'Your security deposit is refunded if you don’t win.'
                            : 'Your deposit counts toward the price if you win.'}
                    </p>
                    {(needsCode || errors.otp) && (
                        <label className="flex flex-col gap-1.5 text-sm font-semibold">
                            Confirmation code
                            <input
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                maxLength={6}
                                value={otp}
                                onChange={(e) =>
                                    setOtp(e.target.value.replace(/\D/g, ''))
                                }
                                className={field}
                                placeholder={
                                    errors.otp
                                        ? ''
                                        : 'We’ll email it when you confirm'
                                }
                            />
                            {errors.otp && (
                                <span className="font-normal text-brand-accent">
                                    {errors.otp}
                                </span>
                            )}
                        </label>
                    )}
                    {(errors.amount || errors.bid || errors.maximum) && (
                        <p
                            role="alert"
                            className="text-sm font-semibold text-destructive"
                        >
                            {errors.amount ?? errors.bid ?? errors.maximum}
                        </p>
                    )}
                    <button
                        type="button"
                        className={btn}
                        disabled={busy}
                        onClick={submit}
                    >
                        {busy ? 'Placing bid…' : 'Place binding bid'}
                    </button>
                    <button
                        type="button"
                        className="text-sm font-semibold underline"
                        onClick={() => setStep('enter')}
                    >
                        Change bid
                    </button>
                </section>
            )}
        </div>
    );
}
