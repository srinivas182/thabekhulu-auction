import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { AuctionLive } from '@/components/auctions/bid-form';
import { rand } from '@/lib/money';
import { tender } from '@/routes/auctions';

const field =
    'h-12 w-full min-w-0 rounded-[10px] border border-input bg-card px-3 text-lg tabular';
const btn =
    'flex min-h-[52px] w-full items-center justify-center rounded-[10px] bg-primary px-5 text-[17px] font-semibold text-primary-foreground disabled:opacity-60';

/** Sealed offer (B8): one per bidder, raise-only, unreadable by anyone until the two-person opening. */
export default function TenderForm({
    auction,
    ulid,
}: {
    auction: AuctionLive;
    ulid: string;
}) {
    const errors = (usePage().props.errors ?? {}) as Record<string, string>;
    const mine = auction.mine!;
    const [amount, setAmount] = useState('');
    const [review, setReview] = useState(false);
    const [otp, setOtp] = useState('');
    const value = Number(amount.replace(/\D/g, '')) || 0;
    const floor = Math.max(auction.minimum ?? 0, (mine.offer ?? 0) + 1);

    if (auction.closed)
        return (
            <p role="status" className="rounded-[10px] bg-muted p-4 text-sm">
                The tender has closed. Offers are opened by two of our staff,
                and every bidder is told the result by email.
            </p>
        );

    return (
        <div className="grid gap-3 [&_*]:min-w-0">
            <p className="font-semibold">You’re bidder #{mine.bidderNumber}</p>
            {mine.offer ? (
                <p className="rounded-[10px] bg-status-live/10 p-3 text-sm">
                    <strong>Your sealed offer: {rand(mine.offer)}.</strong> You
                    can raise it until the deadline, but not lower it.
                </p>
            ) : (
                <p className="text-sm text-muted-foreground">
                    Make one confidential offer. Nobody, including our staff,
                    can see it before the deadline.
                </p>
            )}
            {!review ? (
                <>
                    <label className="flex flex-col gap-1.5 text-sm font-semibold">
                        {mine.offer
                            ? 'New, higher offer (R)'
                            : 'Your offer (R)'}
                        <input
                            inputMode="numeric"
                            value={amount}
                            onChange={(e) => setAmount(e.target.value)}
                            className={field}
                            placeholder={String(floor)}
                        />
                        <span className="font-normal text-muted-foreground">
                            At least {rand(floor)}
                        </span>
                        {errors.amount && (
                            <span className="text-destructive">
                                {errors.amount}
                            </span>
                        )}
                    </label>
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
                        disabled={value < floor}
                        onClick={() => setReview(true)}
                    >
                        Review offer
                    </button>
                </>
            ) : (
                <section
                    aria-labelledby="t-review"
                    className="grid gap-3 rounded-[14px] border-2 border-primary p-4"
                >
                    <h3 id="t-review" className="font-display text-2xl">
                        Confirm your sealed offer
                    </h3>
                    <p className="tabular text-lg font-bold">{rand(value)}</p>
                    <p className="text-sm leading-relaxed">
                        Offers are <strong>binding</strong>. The highest offer
                        wins; if two offers are equal, the earlier one wins.
                    </p>
                    {(value >= (auction.otpFrom ?? Infinity) || errors.otp) && (
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
                            />
                            {errors.otp && (
                                <span className="font-normal text-brand-accent">
                                    {errors.otp}
                                </span>
                            )}
                        </label>
                    )}
                    {(errors.amount || errors.bid) && (
                        <p
                            role="alert"
                            className="text-sm font-semibold text-destructive"
                        >
                            {errors.amount ?? errors.bid}
                        </p>
                    )}
                    <button
                        type="button"
                        className={btn}
                        onClick={() =>
                            router.post(
                                tender(ulid).url,
                                { amount: value, otp: otp || null },
                                {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        setReview(false);
                                        setAmount('');
                                        setOtp('');
                                    },
                                },
                            )
                        }
                    >
                        Submit sealed offer
                    </button>
                    <button
                        type="button"
                        className="text-sm font-semibold underline"
                        onClick={() => setReview(false)}
                    >
                        Change offer
                    </button>
                </section>
            )}
        </div>
    );
}
