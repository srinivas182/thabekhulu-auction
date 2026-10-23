import { Head, Link } from '@inertiajs/react';
import QuotesNav from '@/components/quotes-nav';
import { randCents } from '@/lib/cents';
import { edit, index, reports } from '@/routes/admin/quotes';
import reportRoutes from '@/routes/admin/quotes/reports';

type Props = {
    statuses: Record<string, string>;
    byStatus: Record<string, { count: number; total: number }>;
    inProgress: number;
    months: {
        month: string;
        sent: number;
        accepted: number;
        rate: number | null;
    }[];
    expiring: {
        ulid: string;
        reference: string;
        client: string;
        total: number;
        validUntil: string;
    }[];
};

export default function QuoteReports({
    statuses,
    byStatus,
    inProgress,
    months,
    expiring,
}: Props) {
    return (
        <>
            <Head title="Quotation reports" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <h1 className="font-display text-3xl">Quotations</h1>
                    <a
                        href={reportRoutes.export().url}
                        className="inline-flex h-9 items-center rounded-md border border-input px-3 text-sm font-medium"
                    >
                        Export CSV
                    </a>
                </div>
                <QuotesNav current="Reports" />
                <dl className="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-4">
                    <div className="rounded-xl border border-border bg-card p-5">
                        <dt className="text-sm text-muted-foreground">
                            Value with clients now
                        </dt>
                        <dd className="tabular mt-1 text-2xl font-bold">
                            {randCents(inProgress)}
                        </dd>
                    </div>
                    {['sent', 'viewed', 'accepted', 'declined', 'expired'].map(
                        (k) => (
                            <div
                                key={k}
                                className="rounded-xl border border-border bg-card p-5"
                            >
                                <dt className="text-sm text-muted-foreground">
                                    {statuses[k]}
                                </dt>
                                <dd className="tabular mt-1 text-2xl font-bold">
                                    {byStatus[k]?.count ?? 0}
                                </dd>
                                <dd className="tabular text-sm text-muted-foreground">
                                    {randCents(byStatus[k]?.total ?? 0)}
                                </dd>
                            </div>
                        ),
                    )}
                </dl>
                <section
                    aria-labelledby="rate"
                    className="rounded-xl border border-border bg-card p-5"
                >
                    <h2 id="rate" className="font-semibold">
                        Acceptance by month sent
                    </h2>
                    <table className="mt-3 w-full text-sm">
                        <thead className="text-left text-muted-foreground">
                            <tr>
                                <th className="py-2 font-medium">Month</th>
                                <th className="font-medium">Sent</th>
                                <th className="font-medium">Accepted</th>
                                <th className="font-medium">Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            {months.map((m) => (
                                <tr
                                    key={m.month}
                                    className="border-t border-border"
                                >
                                    <td className="py-2">{m.month}</td>
                                    <td className="tabular">{m.sent}</td>
                                    <td className="tabular">{m.accepted}</td>
                                    <td className="tabular">
                                        {m.rate === null ? '—' : `${m.rate}%`}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
                <section
                    aria-labelledby="expiring"
                    className="rounded-xl border border-border bg-card p-5"
                >
                    <h2 id="expiring" className="font-semibold">
                        Expiring in the next 7 days
                    </h2>
                    {expiring.length === 0 ? (
                        <p className="mt-2 text-sm text-muted-foreground">
                            None.
                        </p>
                    ) : (
                        <ul className="mt-2 grid gap-1 text-sm">
                            {expiring.map((q) => (
                                <li key={q.ulid}>
                                    <Link
                                        href={edit(q.ulid)}
                                        className="underline"
                                    >
                                        {q.reference}
                                    </Link>{' '}
                                    · {q.client} · {randCents(q.total)} ·{' '}
                                    {q.validUntil}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

QuoteReports.layout = {
    breadcrumbs: [
        { title: 'Quotations', href: index() },
        { title: 'Reports', href: reports() },
    ],
};
