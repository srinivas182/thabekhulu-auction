import { Head, router } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { rand } from '@/lib/money';
import { accept, decline } from '@/routes/account/purchases';

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
};

export default function Purchases({ cases }: { cases: C[] }) {
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
                                        {c.agreementDue}; balance or guarantees
                                        by {c.balanceDue}.
                                    </li>
                                </ul>
                            )}
                        </li>
                    ))}
                </ul>
            </section>
        </>
    );
}
