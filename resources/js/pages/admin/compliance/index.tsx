import { Head, Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/admin/compliance';

type Props = {
    status: string;
    counts: Record<string, number>;
    items: {
        ulid: string;
        source: string;
        summary: string;
        listing: string | null;
        person: string | null;
        detected: string;
    }[];
    statuses: Record<string, string>;
};
const sources: Record<string, string> = {
    integrity: 'Bidding integrity',
    screening: 'Screening',
    downloads: 'Data room',
    bank_line: 'Bank line',
    default: 'Default',
    lockout: 'Sign-in lockouts',
    bid_code: 'Bid code',
};

export default function ComplianceQueue({
    status,
    counts,
    items,
    statuses,
}: Props) {
    return (
        <>
            <Head title="Suspicious activity" />
            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <div>
                    <h1 className="font-display text-3xl">
                        Suspicious activity
                    </h1>
                    <p className="mt-1 max-w-3xl text-muted-foreground">
                        Signals gathered every hour for a person to review. The
                        platform never files reports: any suspicious transaction
                        report to the FIC is made by Thabekhulu’s compliance
                        officer, using the evidence exported here.
                    </p>
                </div>
                <nav
                    aria-label="Status"
                    className="flex gap-x-6 border-b border-border text-sm"
                >
                    {Object.entries(statuses).map(([k, label]) => (
                        <Link
                            key={k}
                            href={index({ query: { status: k } })}
                            aria-current={status === k ? 'page' : undefined}
                            className={cn(
                                '-mb-px border-b-2 py-2.5',
                                status === k
                                    ? 'border-primary font-semibold'
                                    : 'border-transparent text-muted-foreground',
                            )}
                        >
                            {label} ({counts[k] ?? 0})
                        </Link>
                    ))}
                </nav>
                <ul className="divide-y divide-border rounded-xl border border-border bg-card">
                    {items.map((i) => (
                        <li key={i.ulid} className="px-5 py-3">
                            <Link
                                href={show(i.ulid)}
                                className="font-medium underline-offset-4 hover:underline"
                            >
                                {i.summary}
                            </Link>
                            <p className="text-sm text-muted-foreground">
                                {sources[i.source] ?? i.source} · {i.detected}
                                {i.person ? ` · ${i.person}` : ''}
                                {i.listing ? ` · ${i.listing}` : ''}
                            </p>
                        </li>
                    ))}
                    {items.length === 0 && (
                        <li className="px-5 py-8 text-center text-muted-foreground">
                            Nothing here.
                        </li>
                    )}
                </ul>
            </div>
        </>
    );
}

ComplianceQueue.layout = {
    breadcrumbs: [{ title: 'Suspicious activity', href: index() }],
};
