import { Form, Link, router } from '@inertiajs/react';
import advanceRoutes from '@/routes/auctions/advance';
import { Check } from 'lucide-react';
import { useState } from 'react';
import { rand } from '@/lib/money';
import { register } from '@/routes';
import { advance, apply } from '@/routes/auctions';

export type BidderState = {
    verification: string | null;
    approval: string | null;
    bidderNumber: string | null;
    reason: string | null;
    needsFunds: boolean;
    depositPaid: boolean;
    depositsOpen: boolean;
    depositAmount: number;
    depositUrl: string;
    scheduled: boolean;
    sealed: boolean;
    opens: string | null;
    advanceMax: number | null;
    otpFrom: number | null;
    autoBid: boolean;
    entities: { ulid: string; name: string; verified: boolean }[];
} | null;

const btn =
    'flex min-h-[52px] items-center justify-center rounded-[10px] bg-primary px-5 text-[17px] font-semibold text-primary-foreground';
const field =
    'h-12 w-full min-w-0 rounded-[10px] border border-input bg-card px-3 text-base';

/** The buyer's next step for this listing, in the bid panel. */
export default function BidderPanel({
    state,
    ulid,
    minimum,
}: {
    state: BidderState;
    ulid: string;
    minimum: number | null;
}) {
    if (state === null) {
        return (
            <>
                <Link href={register()} className={btn}>
                    Register to bid
                </Link>
                <p className="text-[13px] leading-relaxed text-muted-foreground">
                    Already registered?{' '}
                    <Link href="/login" className="underline">
                        Log in
                    </Link>{' '}
                    to apply.
                </p>
            </>
        );
    }

    if (state.approval === 'approved') {
        return (
            <div className="grid gap-3 rounded-[10px] bg-status-live/10 p-4">
                <p className="inline-flex items-center gap-1.5 font-semibold text-status-live">
                    <Check className="size-4" aria-hidden="true" />
                    Approved to bid as bidder #{state.bidderNumber}
                </p>
                {!state.depositPaid &&
                state.depositAmount > 0 &&
                !state.depositsOpen ? (
                    <p className="text-sm">
                        Next: the <strong>{rand(state.depositAmount)}</strong>{' '}
                        deposit. Deposits for this listing open once our legal
                        checks are complete. We’ll email you as soon as they do.
                    </p>
                ) : !state.depositPaid && state.depositAmount > 0 ? (
                    <>
                        <p className="text-sm">
                            Next: pay the{' '}
                            <strong>{rand(state.depositAmount)}</strong>{' '}
                            deposit. Online payment confirms straight away; an
                            EFT once it reflects in our account. Bidding unlocks
                            as soon as it’s confirmed.
                        </p>
                        <Link
                            href={state.depositUrl}
                            className="inline-flex min-h-11 w-fit items-center rounded-[10px] bg-primary px-5 font-semibold text-primary-foreground"
                        >
                            Pay deposit
                        </Link>
                    </>
                ) : state.scheduled && !state.sealed && state.autoBid ? (
                    <AdvanceMaximum
                        ulid={ulid}
                        state={state}
                        minimum={minimum}
                    />
                ) : (
                    <p className="text-sm text-muted-foreground">
                        {state.sealed
                            ? 'Submit your confidential offer here once the tender opens.'
                            : 'Your deposit is received. You can bid here while the auction is live.'}
                    </p>
                )}
            </div>
        );
    }

    if (state.approval === 'pending') {
        return (
            <p role="status" className="rounded-[10px] bg-muted p-4 text-sm">
                <strong className="block">
                    You’ve applied to bid. This isn’t a bid yet.
                </strong>
                Next: we review your application, then you pay the deposit and
                can bid here while the auction is live. The highest bid wins
                when it closes.{' '}
                <Link href="/account/approvals" className="underline">
                    Follow it in My auctions
                </Link>
            </p>
        );
    }

    if (state.verification !== 'verified') {
        return (
            <>
                <Link href="/account/verification" className={btn}>
                    {state.verification === 'review' ||
                    state.verification === 'submitted'
                        ? 'Verification in progress'
                        : 'Verify your identity'}
                </Link>
                <p className="text-[13px] leading-relaxed text-muted-foreground">
                    Every bidder is verified once before applying to bid.
                </p>
            </>
        );
    }

    return (
        <Form
            action={apply(ulid).url}
            method="post"
            options={{ preserveScroll: true }}
            className="grid gap-3 [&_*]:min-w-0"
        >
            {({ processing, errors }) => (
                <>
                    {(state.approval === 'rejected' ||
                        state.approval === 'revoked') &&
                        state.reason && (
                            <p className="rounded-[10px] bg-muted p-3 text-sm">
                                Your last application wasn’t approved:{' '}
                                {state.reason}
                            </p>
                        )}
                    {state.entities.length > 0 && (
                        <label className="flex flex-col gap-1.5 text-sm font-semibold">
                            Bidding as
                            <select
                                name="entity"
                                className={field}
                                defaultValue=""
                            >
                                <option value="">Myself</option>
                                {state.entities.map((e) => (
                                    <option key={e.ulid} value={e.ulid}>
                                        {e.name}
                                        {e.verified ? '' : ' (being verified)'}
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}
                    {state.needsFunds && (
                        <>
                            <label className="flex flex-col gap-1.5 text-sm font-semibold">
                                Funds available (R)
                                <input
                                    name="funds_amount"
                                    inputMode="numeric"
                                    placeholder={minimum ? String(minimum) : ''}
                                    className={field}
                                />
                                {errors.funds_amount && (
                                    <span className="text-destructive">
                                        {errors.funds_amount}
                                    </span>
                                )}
                            </label>
                            <label className="flex flex-col gap-1.5 text-sm font-semibold">
                                Proof of funds
                                <input
                                    type="file"
                                    name="funds"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    className="text-sm font-normal"
                                />
                                <span className="font-normal text-muted-foreground">
                                    Bank letter or statement, under 90 days old.
                                    Seen only by our team.
                                </span>
                                {errors.funds && (
                                    <span className="text-destructive">
                                        {errors.funds}
                                    </span>
                                )}
                            </label>
                        </>
                    )}
                    {errors.apply && (
                        <p className="text-sm font-semibold text-destructive">
                            {errors.apply}
                        </p>
                    )}
                    <button disabled={processing} className={btn}>
                        Apply to bid
                    </button>
                    <p className="text-[13px] leading-relaxed text-muted-foreground">
                        Bidding for a company or trust?{' '}
                        <Link href="/account/entities" className="underline">
                            Add it first
                        </Link>
                        .
                    </p>
                </>
            )}
        </Form>
    );
}

/** Before bidding opens: the most the buyer will pay; the system bids for them from the start, only as much as needed. */
function AdvanceMaximum({
    ulid,
    state,
    minimum,
}: {
    ulid: string;
    state: NonNullable<BidderState>;
    minimum: number | null;
}) {
    const [editing, setEditing] = useState(state.advanceMax === null);
    const [value, setValue] = useState(
        state.advanceMax ? String(state.advanceMax) : '',
    );
    const amount = Number(value.replace(/\D/g, '')) || 0;

    if (!editing && state.advanceMax !== null) {
        return (
            <div className="grid gap-2 text-sm">
                <p>
                    Your advance maximum:{' '}
                    <strong>{rand(state.advanceMax)}</strong>. When bidding
                    opens {state.opens ? `on ${state.opens} SAST` : ''}, we’ll
                    bid for you, only as much as needed to keep you in the lead.
                    Only you can see it.
                </p>
                <div className="flex gap-2">
                    <button
                        type="button"
                        onClick={() => setEditing(true)}
                        className="rounded-[10px] border border-input bg-background px-4 py-2 font-semibold"
                    >
                        Change
                    </button>
                    <button
                        type="button"
                        onClick={() =>
                            router.delete(advanceRoutes.destroy(ulid).url, {
                                preserveScroll: true,
                            })
                        }
                        className="rounded-[10px] px-4 py-2 font-semibold underline"
                    >
                        Remove
                    </button>
                </div>
            </div>
        );
    }

    return (
        <Form
            action={advance(ulid).url}
            method="post"
            options={{ preserveScroll: true }}
            onSuccess={() => setEditing(false)}
            className="grid gap-2 text-sm"
        >
            {({ processing, errors }) => (
                <>
                    <p>
                        Bidding opens{' '}
                        {state.opens ? `on ${state.opens} SAST` : 'soon'}. You
                        can set an <strong>advance maximum</strong> now: the
                        most you’ll pay. From the opening, we bid for you, only
                        as much as needed to keep you in the lead.
                    </p>
                    <label htmlFor="advance" className="font-semibold">
                        Your maximum (R)
                    </label>
                    <input
                        id="advance"
                        name="maximum"
                        inputMode="numeric"
                        value={value}
                        onChange={(e) => setValue(e.target.value)}
                        placeholder={minimum ? `At least ${rand(minimum)}` : ''}
                        className="h-11 rounded-[10px] border border-input bg-background px-3"
                    />
                    {(errors.otp ||
                        (state.otpFrom !== null &&
                            amount >= state.otpFrom)) && (
                        <>
                            <label
                                htmlFor="advance-otp"
                                className="font-semibold"
                            >
                                Confirmation code
                            </label>
                            <input
                                id="advance-otp"
                                name="otp"
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                maxLength={6}
                                className="h-11 w-40 rounded-[10px] border border-input bg-background px-3"
                            />
                            <span className="text-muted-foreground">
                                {errors.otp ??
                                    'For larger amounts we email you a code. Save once to receive it.'}
                            </span>
                        </>
                    )}
                    {(errors.maximum || errors.bid) && (
                        <span className="text-destructive">
                            {errors.maximum ?? errors.bid}
                        </span>
                    )}
                    <button
                        disabled={processing || amount === 0}
                        className="w-fit rounded-[10px] bg-primary px-5 py-2.5 font-semibold text-primary-foreground"
                    >
                        Save advance maximum
                    </button>
                </>
            )}
        </Form>
    );
}
