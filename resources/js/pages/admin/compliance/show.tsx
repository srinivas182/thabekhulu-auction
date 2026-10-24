import { Form, Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { evidence, index, update } from '@/routes/admin/compliance';

type Props = {
    item: {
        ulid: string;
        source: string;
        summary: string;
        status: string;
        details: Record<string, unknown> | null;
        listing: string | null;
        person: string | null;
        detected: string;
    };
    notes: { note: string; by: string; at: string }[];
    statuses: Record<string, string>;
};

/** Readable text for any detail value: lists joined, objects as JSON, never "[object Object]". */
function detail(v: unknown): string {
    if (v === null || v === undefined) return '';
    if (
        typeof v === 'string' ||
        typeof v === 'number' ||
        typeof v === 'boolean'
    )
        return String(v);
    if (Array.isArray(v)) return v.map(detail).join(', ');
    return JSON.stringify(v);
}

export default function ComplianceItem({ item, notes, statuses }: Props) {
    return (
        <>
            <Head title="Suspicious activity item" />
            <div className="flex max-w-4xl flex-1 flex-col gap-5 p-4 sm:p-6">
                <div>
                    <Link href={index()} className="text-sm underline">
                        Suspicious activity
                    </Link>
                    <h1 className="font-display text-2xl">{item.summary}</h1>
                    <p className="text-muted-foreground">
                        {statuses[item.status]} · detected {item.detected}
                        {item.person ? ` · ${item.person}` : ''}
                        {item.listing ? ` · ${item.listing}` : ''}
                    </p>
                </div>
                {item.details && Object.keys(item.details).length > 0 && (
                    <dl className="grid gap-1 rounded-xl border border-border bg-card p-5 text-sm">
                        {Object.entries(item.details).map(([k, v]) => (
                            <div key={k} className="flex flex-wrap gap-2">
                                <dt className="font-medium">
                                    {k.replace(/_/g, ' ')}:
                                </dt>
                                <dd className="min-w-0 break-words">
                                    {detail(v)}
                                </dd>
                            </div>
                        ))}
                    </dl>
                )}
                <section
                    aria-labelledby="n-h"
                    className="grid gap-2 rounded-xl border border-border bg-card p-5 text-sm"
                >
                    <h2 id="n-h" className="font-semibold">
                        Notes
                    </h2>
                    {notes.map((n, i) => (
                        <p key={i}>
                            <span className="text-muted-foreground">
                                {n.at} · {n.by}:
                            </span>{' '}
                            {n.note}
                        </p>
                    ))}
                    {notes.length === 0 && (
                        <p className="text-muted-foreground">No notes yet.</p>
                    )}
                    <Form
                        action={update(item.ulid).url}
                        method="put"
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        className="mt-2 grid gap-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <textarea
                                    name="note"
                                    aria-label="Note"
                                    placeholder="What you checked and what you decided"
                                    className="min-h-24 rounded-md border border-input bg-background px-3 py-2"
                                />
                                {errors.note && (
                                    <p className="text-destructive">
                                        {errors.note}
                                    </p>
                                )}
                                <div className="flex flex-wrap items-center gap-2">
                                    <select
                                        name="status"
                                        defaultValue={item.status}
                                        aria-label="Decision"
                                        className="h-9 rounded-md border border-input bg-background px-2"
                                    >
                                        {Object.entries(statuses).map(
                                            ([k, l]) => (
                                                <option key={k} value={k}>
                                                    {l}
                                                </option>
                                            ),
                                        )}
                                    </select>
                                    <Button size="sm" disabled={processing}>
                                        Save
                                    </Button>
                                    <a
                                        href={evidence(item.ulid).url}
                                        className="text-sm font-semibold underline"
                                    >
                                        Download evidence (JSON)
                                    </a>
                                </div>
                            </>
                        )}
                    </Form>
                </section>
            </div>
        </>
    );
}

ComplianceItem.layout = {
    breadcrumbs: [{ title: 'Suspicious activity', href: index() }],
};
