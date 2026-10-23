import { Head } from '@inertiajs/react';
import { randCents } from '@/lib/cents';

type Q = {
    reference: string;
    title: string;
    status: string;
    total: number;
    validUntil: string;
    url: string;
};

export default function AccountQuotes({ quotes }: { quotes: Q[] }) {
    return (
        <>
            <Head title="My quotations" />
            <section className="mx-auto max-w-[1000px] px-5 py-12 sm:px-8">
                <h1 className="font-display text-4xl">My quotations</h1>
                {quotes.length === 0 ? (
                    <p className="mt-6 text-muted-foreground">
                        You have no quotations yet.
                    </p>
                ) : (
                    <ul className="mt-6 divide-y divide-border rounded-[18px] border border-border bg-card">
                        {quotes.map((q) => (
                            <li
                                key={q.reference}
                                className="flex flex-wrap items-center justify-between gap-3 px-5 py-4"
                            >
                                <div>
                                    <a
                                        href={q.url}
                                        className="font-semibold underline-offset-4 hover:underline"
                                    >
                                        {q.title}
                                    </a>
                                    <p className="text-sm text-muted-foreground">
                                        {q.reference} · {q.status} · valid until{' '}
                                        {q.validUntil}
                                    </p>
                                </div>
                                <p className="tabular font-semibold">
                                    {randCents(q.total)}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </>
    );
}
