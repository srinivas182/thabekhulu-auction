import { Head, Link } from '@inertiajs/react';
import { rand } from '@/lib/money';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/admin/settlement';

type C = {
    ulid: string;
    listing: string;
    buyer: string;
    kind: string;
    price: number;
    status: string;
    statusLabel: string;
    missed: string | null;
    defaultPending: boolean;
};

export default function SettlementIndex({ cases }: { cases: C[] }) {
    return (
        <>
            <Head title="Settlement" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <h1 className="font-display text-3xl">Settlement</h1>
                <ul className="divide-y divide-border rounded-xl border border-border bg-card">
                    {cases.map((c) => (
                        <li
                            key={c.ulid}
                            className="flex flex-wrap items-center justify-between gap-3 px-5 py-3"
                        >
                            <div>
                                <Link
                                    href={show(c.ulid)}
                                    className="font-medium underline-offset-4 hover:underline"
                                >
                                    {c.listing}
                                </Link>
                                <p className="text-sm text-muted-foreground">
                                    {c.buyer} · {rand(c.price)}
                                    {c.kind === 'runner_up'
                                        ? ' · runner-up'
                                        : ''}
                                </p>
                                {c.missed && (
                                    <p className="text-sm font-semibold text-destructive">
                                        Deadline missed: {c.missed}
                                    </p>
                                )}
                                {c.defaultPending && (
                                    <p className="text-sm font-semibold text-status-closing">
                                        Default waiting for a second person
                                    </p>
                                )}
                            </div>
                            <span
                                className={cn(
                                    'rounded-full px-3 py-1 text-xs font-semibold',
                                    c.status === 'completed'
                                        ? 'bg-status-live/10 text-status-live'
                                        : c.status === 'defaulted'
                                          ? 'bg-destructive/10 text-destructive'
                                          : 'bg-muted',
                                )}
                            >
                                {c.statusLabel}
                            </span>
                        </li>
                    ))}
                    {cases.length === 0 && (
                        <li className="px-5 py-8 text-center text-muted-foreground">
                            No settlements yet. A case opens when an auction
                            closes with a winner.
                        </li>
                    )}
                </ul>
            </div>
        </>
    );
}

SettlementIndex.layout = {
    breadcrumbs: [{ title: 'Settlement', href: index() }],
};
