import { Form, Head, router } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { randCents } from '@/lib/cents';
import { rand } from '@/lib/money';
import purchases, { accept, decline } from '@/routes/account/purchases';

type C = {
    ulid: string;
    listing: string;
    kind: string;
    price: number;
    status: string;
    statusLabel: string;
    agreementDue: string | null;
    balanceDue: string | null;
    offerExpires: string | null;
    steps: { label: string; done: boolean }[];
    transfer: { key: string; label: string; doneOn: string | null }[];
    balance: {
        reference: string;
        price: number;
        deposit: number;
        due: number;
        received: number;
        status: string;
        proofUploadedAt: string | null;
    } | null;
    agreement: { status: string; reason: string | null } | null;
};
type Bank = {
    bank: string;
    name: string;
    number: string;
    branch: string;
} | null;

export default function Purchases({ cases, bank }: { cases: C[]; bank: Bank }) {
    return (
        <>
            <Head title="My purchases" />
            <section className="mx-auto max-w-[860px] px-5 py-12 sm:px-8">
                <h1 className="font-display text-4xl">My purchases</h1>
                {cases.length === 0 && (
                    <p className="mt-6 text-muted-foreground">
                        Nothing here yet. When you win an auction, its
                        settlement steps appear here.
                    </p>
                )}
                <ul className="mt-8 grid gap-4">
                    {cases.map((c) => (
                        <li
                            key={c.ulid}
                            className="grid gap-3 rounded-[18px] border border-border bg-card p-5"
                        >
                            <div className="flex flex-wrap justify-between gap-2">
                                <p className="font-display text-2xl">
                                    {c.listing}
                                </p>
                                <span className="text-sm font-semibold">
                                    {c.statusLabel}
                                </span>
                            </div>
                            <p className="text-muted-foreground">
                                {rand(c.price)}
                                {c.kind === 'runner_up'
                                    ? ' · your own highest bid'
                                    : ' · winning bid'}
                            </p>
                            {c.status === 'offered' && (
                                <div className="grid gap-3 rounded-[14px] border-2 border-primary p-4">
                                    <p>
                                        The winning bidder did not complete. You
                                        are offered the property at your own
                                        highest bid of{' '}
                                        <strong>{rand(c.price)}</strong>. Please
                                        reply by{' '}
                                        <strong>{c.offerExpires}</strong>; if
                                        you decline or don’t reply, your deposit
                                        is refunded.
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        <button
                                            className="min-h-12 rounded-[10px] bg-primary px-6 font-semibold text-primary-foreground"
                                            onClick={() =>
                                                window.confirm(
                                                    'Accept the offer? This is binding under the conditions of sale.',
                                                ) &&
                                                router.post(
                                                    accept(c.ulid).url,
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Accept offer
                                        </button>
                                        <button
                                            className="min-h-12 rounded-[10px] border border-input px-6 font-semibold"
                                            onClick={() =>
                                                window.confirm(
                                                    'Decline the offer? Your deposit will be refunded.',
                                                ) &&
                                                router.post(
                                                    decline(c.ulid).url,
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Decline
                                        </button>
                                    </div>
                                </div>
                            )}
                            {c.status === 'completed' &&
                                c.transfer.length > 0 && (
                                    <ul
                                        aria-label="Transfer progress"
                                        className="grid gap-1.5 text-sm"
                                    >
                                        {c.transfer.map((t) => (
                                            <li
                                                key={t.key}
                                                className="flex items-center gap-2"
                                            >
                                                {t.doneOn ? (
                                                    <Check
                                                        className="size-4 text-status-live"
                                                        aria-hidden="true"
                                                    />
                                                ) : (
                                                    <span className="inline-block size-4 rounded-full border border-input" />
                                                )}
                                                {t.label}
                                                {t.doneOn && (
                                                    <span className="text-muted-foreground">
                                                        · {t.doneOn}
                                                    </span>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            {c.status === 'open' && (
                                <ul className="grid gap-1.5 text-sm">
                                    {c.steps.map((s) => (
                                        <li
                                            key={s.label}
                                            className="flex items-center gap-2"
                                        >
                                            {s.done ? (
                                                <Check
                                                    className="size-4 text-status-live"
                                                    aria-hidden="true"
                                                />
                                            ) : (
                                                <span className="inline-block size-4 rounded-full border border-input" />
                                            )}
                                            {s.label}
                                        </li>
                                    ))}
                                    <li className="pt-1 text-muted-foreground">
                                        Sign the sale agreement by{' '}
                                        {c.agreementDue}; pay the balance by{' '}
                                        {c.balanceDue}.
                                    </li>
                                </ul>
                            )}
                            {c.status === 'open' && (
                                <PayAndUpload c={c} bank={bank} />
                            )}
                        </li>
                    ))}
                </ul>
            </section>
        </>
    );
}

/** The winner pays the balance to Thabekhulu, uploads proof, and uploads the signed sale agreement. */
function PayAndUpload({ c, bank }: { c: C; bank: Bank }) {
    const agreementDone =
        c.steps.find((s) => s.label.startsWith('Sale agreement'))?.done ??
        false;
    const box =
        'grid gap-2 rounded-[14px] border border-border bg-background p-4 text-sm';

    return (
        <div className="mt-4 grid gap-3 md:grid-cols-2">
            {c.balance && (
                <section className={box} aria-labelledby={`bal-${c.ulid}`}>
                    <h3 id={`bal-${c.ulid}`} className="font-semibold">
                        Balance to pay
                    </h3>
                    {c.balance.status === 'received' ? (
                        <p className="font-medium text-status-live">
                            Received. Thank you: we’ll pass your details to the
                            transfer attorney.
                        </p>
                    ) : (
                        <>
                            <dl className="grid grid-cols-[1fr_auto] gap-x-4 gap-y-1">
                                <dt>Purchase price</dt>
                                <dd className="tabular text-right">
                                    {randCents(c.balance.price)}
                                </dd>
                                {c.balance.deposit > 0 && (
                                    <>
                                        <dt>Less your deposit</dt>
                                        <dd className="tabular text-right">
                                            − {randCents(c.balance.deposit)}
                                        </dd>
                                    </>
                                )}
                                {c.balance.received > 0 && (
                                    <>
                                        <dt>Already received</dt>
                                        <dd className="tabular text-right">
                                            − {randCents(c.balance.received)}
                                        </dd>
                                    </>
                                )}
                                <dt className="font-semibold">
                                    To pay by {c.balanceDue}
                                </dt>
                                <dd className="tabular text-right font-semibold">
                                    {randCents(
                                        c.balance.due - c.balance.received,
                                    )}
                                </dd>
                            </dl>
                            {bank ? (
                                <p className="rounded-lg bg-muted/60 p-3">
                                    Pay by EFT to <strong>{bank.name}</strong>,{' '}
                                    {bank.bank}, account {bank.number}, branch{' '}
                                    {bank.branch}, using the reference{' '}
                                    <strong className="font-mono">
                                        {c.balance.reference}
                                    </strong>{' '}
                                    exactly.
                                </p>
                            ) : (
                                <p className="text-muted-foreground">
                                    Our team will send you the payment details.
                                </p>
                            )}
                            <Form
                                action={purchases.balanceProof(c.ulid).url}
                                method="post"
                                options={{ preserveScroll: true }}
                                resetOnSuccess
                                className="grid gap-2"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <label
                                            className="font-semibold"
                                            htmlFor={`proof-${c.ulid}`}
                                        >
                                            Upload your proof of payment
                                        </label>
                                        <input
                                            id={`proof-${c.ulid}`}
                                            type="file"
                                            name="proof"
                                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                                        />
                                        {errors.proof && (
                                            <span className="text-destructive">
                                                {errors.proof}
                                            </span>
                                        )}
                                        {c.balance?.proofUploadedAt && (
                                            <span className="text-status-live">
                                                Proof received on{' '}
                                                {c.balance.proofUploadedAt}.
                                                Confirmed once the payment
                                                reflects.
                                            </span>
                                        )}
                                        <button
                                            disabled={processing}
                                            className="w-fit rounded-[10px] border border-input px-4 py-2 font-semibold"
                                        >
                                            Upload proof
                                        </button>
                                    </>
                                )}
                            </Form>
                        </>
                    )}
                </section>
            )}
            <section className={box} aria-labelledby={`agr-${c.ulid}`}>
                <h3 id={`agr-${c.ulid}`} className="font-semibold">
                    Signed sale agreement
                </h3>
                {agreementDone ? (
                    <p className="font-medium text-status-live">
                        Received and confirmed.
                    </p>
                ) : (
                    <Form
                        action={purchases.agreement(c.ulid).url}
                        method="post"
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        className="grid gap-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                {c.agreement?.status === 'pending' && (
                                    <p>
                                        We’re checking the agreement you
                                        uploaded.
                                    </p>
                                )}
                                {c.agreement?.status === 'rejected' &&
                                    c.agreement.reason && (
                                        <p className="text-destructive">
                                            Please upload again:{' '}
                                            {c.agreement.reason}
                                        </p>
                                    )}
                                <label
                                    className="font-semibold"
                                    htmlFor={`agr-file-${c.ulid}`}
                                >
                                    Upload the signed agreement (PDF or photo)
                                </label>
                                <input
                                    id={`agr-file-${c.ulid}`}
                                    type="file"
                                    name="document"
                                    accept=".pdf,.jpg,.jpeg,.png,.webp"
                                />
                                {errors.document && (
                                    <span className="text-destructive">
                                        {errors.document}
                                    </span>
                                )}
                                <button
                                    disabled={processing}
                                    className="w-fit rounded-[10px] border border-input px-4 py-2 font-semibold"
                                >
                                    Upload agreement
                                </button>
                            </>
                        )}
                    </Form>
                )}
            </section>
        </div>
    );
}
