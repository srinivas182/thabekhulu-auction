import { Head, Link } from '@inertiajs/react';
import { formatDateTime } from '@/lib/format';
import { rand } from '@/lib/money';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/admin/leads';

type L = {
    ulid: string;
    name: string;
    location: string;
    type: string;
    askingPrice: number | null;
    status: string;
    statusLabel: string;
    createdAt: string | null;
};
type Props = {
    leads: { data: L[] };
    statuses: Record<string, string>;
    filter: string | null;
    counts: Record<string, number>;
};

export default function Leads({ leads, statuses, filter, counts }: Props) {
    return (
        <>
            <Head title="Seller leads" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div>
                    <h1 className="font-display text-3xl">Seller leads</h1>
                    <p className="mt-1 text-muted-foreground">
                        Owners who asked Thabekhulu to sell their property
                        through “Sell with us”.
                    </p>
                </div>
                <nav
                    aria-label="Filter by status"
                    className="flex flex-wrap gap-2 text-sm"
                >
                    <Link
                        href={index()}
                        className={cn(
                            'rounded-full border px-3 py-1',
                            !filter &&
                                'border-primary bg-primary text-primary-foreground',
                        )}
                    >
                        All
                    </Link>
                    {Object.entries(statuses).map(([k, v]) => (
                        <Link
                            key={k}
                            href={index({ query: { status: k } })}
                            className={cn(
                                'rounded-full border px-3 py-1',
                                filter === k &&
                                    'border-primary bg-primary text-primary-foreground',
                            )}
                        >
                            {v}{' '}
                            <span className="tabular">({counts[k] ?? 0})</span>
                        </Link>
                    ))}
                </nav>
                <ul className="divide-y divide-border rounded-xl border border-border bg-card">
                    {leads.data.map((l) => (
                        <li
                            key={l.ulid}
                            className="flex flex-wrap items-center justify-between gap-2 px-5 py-3"
                        >
                            <div>
                                <Link
                                    href={show(l.ulid)}
                                    className="font-medium underline-offset-4 hover:underline"
                                >
                                    {l.name}
                                </Link>
                                <p className="text-sm text-muted-foreground">
                                    {l.type} · {l.location}
                                    {l.askingPrice
                                        ? ` · asking ${rand(l.askingPrice)}`
                                        : ''}
                                </p>
                            </div>
                            <p className="text-sm">
                                <span className="font-semibold">
                                    {l.statusLabel}
                                </span>{' '}
                                <span className="text-muted-foreground">
                                    · {formatDateTime(l.createdAt)}
                                </span>
                            </p>
                        </li>
                    ))}
                    {leads.data.length === 0 && (
                        <li className="px-5 py-6 text-muted-foreground">
                            No leads here.
                        </li>
                    )}
                </ul>
            </div>
        </>
    );
}

Leads.layout = { breadcrumbs: [{ title: 'Seller leads', href: index() }] };
