import { Head, Link, router } from '@inertiajs/react';
import { Check, Copy, Download } from 'lucide-react';
import { useState } from 'react';
import { randCents as rand } from '@/lib/cents';
import { rand as randWhole } from '@/lib/money';
import { cn } from '@/lib/utils';
import { usePage } from '@inertiajs/react';
import { open, pay } from '@/routes/deposits';

type Props = {
    listing: {
        ulid: string;
        title: string;
        url: string;
        amount: number;
        terms: string;
    };
    approval: string | null;
    gateOpen: boolean;
    deposit: {
        reference: string;
        status: string;
        statusLabel: string;
        amount: number;
        paid: number;
        receipts: { number: string; amount: number; url: string }[];
    } | null;
    bank: { bank: string; name: string; number: string; branch: string } | null;
    escrow: { key: string; name: string } | null;
    returned: string | null;
};

function Copyable({
    label,
    value,
    big,
}: {
    label: string;
    value: string;
    big?: boolean;
}) {
    const [done, setDone] = useState(false);

    return (
        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border py-3 last:border-0">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="flex items-center gap-2">
                <span
                    className={cn(
                        'tabular font-semibold',
                        big && 'font-mono text-xl tracking-wide text-primary',
                    )}
                >
                    {value}
                </span>
                <button
                    type="button"
                    aria-label={`Copy ${label}`}
                    onClick={() => {
                        void navigator.clipboard?.writeText(value);
                        setDone(true);
                        setTimeout(() => setDone(false), 1500);
                    }}
                    className="inline-flex size-9 items-center justify-center rounded-md border border-input"
                >
                    {done ? (
                        <Check className="size-4 text-status-live" />
                    ) : (
                        <Copy className="size-4" />
                    )}
                </button>
            </dd>
        </div>
    );
}

export default function DepositPage({
    listing,
    approval,
    gateOpen,
    deposit,
    bank,
    escrow,
    returned,
}: Props) {
    const payError = ((usePage().props.errors ?? {}) as Record<string, string>)
        .pay;
    const received =
        deposit &&
        ['received', 'refund_scheduled', 'refunded', 'applied'].includes(
            deposit.status,
        );

    return (
        <>
            <Head title={`Deposit: ${listing.title}`} />
            <section className="mx-auto flex max-w-[760px] flex-col gap-6 px-5 py-12 sm:px-8">
                <div>
                    <Link href={listing.url} className="text-sm underline">
                        {listing.title}
                    </Link>
                    <h1 className="mt-1 font-display text-4xl">Your deposit</h1>
                    <p className="mt-2 text-lg text-muted-foreground">
                        {listing.terms === 'security'
                            ? 'A refundable security deposit, held separately and refunded in full if you don’t win. It is not part of the purchase price.'
                            : 'If you win, this deposit counts toward the purchase price. If you don’t, it is refunded in full.'}
                    </p>
                </div>

                {!approval && (
                    <p className="rounded-[10px] bg-muted p-4">
                        Apply to bid on this listing first.
                    </p>
                )}
                {approval && !gateOpen && !deposit && (
                    <p role="status" className="rounded-[10px] bg-muted p-4">
                        Deposits for this listing open once our legal checks are
                        complete. We’ll email you.
                    </p>
                )}

                {approval && gateOpen && !deposit && (
                    <div className="grid gap-3 rounded-[18px] border border-border bg-card p-6">
                        <p className="text-lg">
                            Deposit:{' '}
                            <strong>{randWhole(listing.amount)}</strong>
                        </p>
                        <button
                            onClick={() =>
                                router.post(
                                    open(listing.ulid).url,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                            className="min-h-12 w-fit rounded-[10px] bg-primary px-6 font-semibold text-primary-foreground"
                        >
                            Get payment details
                        </button>
                    </div>
                )}

                {deposit && received && (
                    <div
                        role="status"
                        className="grid gap-2 rounded-[18px] bg-status-live/10 p-6"
                    >
                        <p className="inline-flex items-center gap-2 text-lg font-semibold text-status-live">
                            <Check className="size-5" aria-hidden="true" />
                            {deposit.statusLabel}: {rand(deposit.paid)}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Your refund bank account is locked until this
                            deposit is refunded or applied.
                        </p>
                    </div>
                )}

                {deposit && !received && (
                    <section
                        aria-labelledby="pay-h"
                        className="grid gap-4 rounded-[18px] border border-border bg-card p-6"
                    >
                        <h2 id="pay-h" className="font-display text-2xl">
                            Pay by bank transfer (EFT)
                        </h2>
                        {returned === 'success' && (
                            <p
                                role="status"
                                className="rounded-[10px] bg-brand-accent-soft p-3 text-brand-accent"
                            >
                                Thank you. We’re confirming your payment with{' '}
                                {escrow?.name ?? 'the provider'} and will email
                                your receipt.
                            </p>
                        )}
                        {escrow && (
                            <div className="grid gap-2 rounded-[14px] border border-border p-4">
                                <p className="font-semibold">
                                    Pay securely with {escrow.name}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Your deposit is held in escrow by an
                                    independent party until it is refunded or
                                    applied. EFT and instant EFT are available
                                    on the next page.
                                </p>
                                <button
                                    type="button"
                                    onClick={() =>
                                        router.post(
                                            pay(listing.ulid).url,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                    className="min-h-12 w-fit rounded-[10px] bg-primary px-6 font-semibold text-primary-foreground"
                                >
                                    Pay with {escrow.name}
                                </button>
                                {payError && (
                                    <p className="text-sm text-destructive">
                                        {payError}
                                    </p>
                                )}
                                <p className="pt-1 text-sm text-muted-foreground">
                                    Or pay by EFT directly into our trust
                                    account:
                                </p>
                            </div>
                        )}
                        {deposit.status === 'part_paid' && (
                            <p className="rounded-[10px] bg-brand-accent-soft p-3 text-brand-accent">
                                We’ve received {rand(deposit.paid)}. Please pay
                                the remaining{' '}
                                {rand(deposit.amount - deposit.paid)}.
                            </p>
                        )}
                        {bank ? (
                            <dl className="text-[15px]">
                                <Copyable
                                    label="Reference (required)"
                                    value={deposit.reference}
                                    big
                                />
                                <Copyable
                                    label="Amount"
                                    value={rand(deposit.amount - deposit.paid)}
                                />
                                <Copyable label="Bank" value={bank.bank} />
                                <Copyable
                                    label="Account name"
                                    value={bank.name}
                                />
                                <Copyable
                                    label="Account number"
                                    value={bank.number}
                                />
                                <Copyable
                                    label="Branch code"
                                    value={bank.branch}
                                />
                            </dl>
                        ) : (
                            <p className="rounded-[10px] bg-muted p-3">
                                Thabekhulu’s trust account details will appear
                                here. Your reference is{' '}
                                <strong className="font-mono">
                                    {deposit.reference}
                                </strong>
                                .
                            </p>
                        )}
                        <p className="text-sm text-muted-foreground">
                            Use the reference exactly as shown so we can match
                            your payment. We confirm deposits once they reach
                            our account, usually within one working day, and
                            email your receipt.
                        </p>
                    </section>
                )}

                {deposit && deposit.receipts.length > 0 && (
                    <ul className="divide-y divide-border rounded-[18px] border border-border bg-card">
                        {deposit.receipts.map((r) => (
                            <li
                                key={r.number}
                                className="flex items-center justify-between px-5 py-3"
                            >
                                <span>
                                    {r.number} · {rand(r.amount)}
                                </span>
                                <a
                                    href={r.url}
                                    className="inline-flex items-center gap-1.5 text-sm font-semibold underline"
                                >
                                    <Download
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Receipt
                                </a>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </>
    );
}
