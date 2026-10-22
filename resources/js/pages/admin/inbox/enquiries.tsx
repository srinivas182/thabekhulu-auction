import { Head, Link } from '@inertiajs/react';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/admin/enquiries';

type E = {
    id: number;
    name: string;
    subject: string;
    status: string;
    createdAt: string | null;
};
const labels: Record<string, string> = {
    new: 'New',
    answered: 'Answered',
    closed: 'Closed',
};

export default function Enquiries({
    enquiries,
    filter,
}: {
    enquiries: { data: E[] };
    filter: string | null;
}) {
    return (
        <>
            <Head title="Enquiries" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <h1 className="font-display text-3xl">Enquiries</h1>
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
                    {Object.entries(labels).map(([k, v]) => (
                        <Link
                            key={k}
                            href={index({ query: { status: k } })}
                            className={cn(
                                'rounded-full border px-3 py-1',
                                filter === k &&
                                    'border-primary bg-primary text-primary-foreground',
                            )}
                        >
                            {v}
                        </Link>
                    ))}
                </nav>
                <ul className="divide-y divide-border rounded-xl border border-border bg-card">
                    {enquiries.data.map((e) => (
                        <li
                            key={e.id}
                            className="flex flex-wrap items-center justify-between gap-2 px-5 py-3"
                        >
                            <div>
                                <Link
                                    href={show(e.id)}
                                    className="font-medium underline-offset-4 hover:underline"
                                >
                                    {e.subject}
                                </Link>
                                <p className="text-sm text-muted-foreground">
                                    {e.name}
                                </p>
                            </div>
                            <p className="text-sm">
                                <span
                                    className={cn(
                                        'font-semibold',
                                        e.status === 'new' &&
                                            'text-status-closing',
                                    )}
                                >
                                    {labels[e.status]}
                                </span>{' '}
                                <span className="text-muted-foreground">
                                    · {formatDateTime(e.createdAt)}
                                </span>
                            </p>
                        </li>
                    ))}
                    {enquiries.data.length === 0 && (
                        <li className="px-5 py-6 text-muted-foreground">
                            No enquiries here.
                        </li>
                    )}
                </ul>
            </div>
        </>
    );
}

Enquiries.layout = { breadcrumbs: [{ title: 'Enquiries', href: index() }] };
