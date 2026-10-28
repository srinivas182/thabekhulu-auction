import { Form, Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { register } from '@/routes';
import { apply } from '@/routes/auctions';

export type BidderState = {
    verification: string | null;
    approval: string | null;
    bidderNumber: string | null;
    reason: string | null;
    needsFunds: boolean;
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
            <div
                role="status"
                className="grid gap-1 rounded-[10px] bg-status-live/10 p-4"
            >
                <p className="inline-flex items-center gap-1.5 font-semibold text-status-live">
                    <Check className="size-4" aria-hidden="true" />
                    Approved to bid as bidder #{state.bidderNumber}
                </p>
                <p className="text-sm text-muted-foreground">
                    Next: pay the deposit when online payments open.
                </p>
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
