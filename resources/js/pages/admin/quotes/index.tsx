import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import QuotesNav from '@/components/quotes-nav';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { randCents } from '@/lib/cents';
import { edit, index, store } from '@/routes/admin/quotes';

type Q = {
    ulid: string;
    reference: string;
    title: string;
    client: string;
    status: string;
    statusLabel: string;
    total: number;
    validUntil: string;
};
type Props = {
    quotes: { data: Q[] };
    filters: { status: string | null; q: string | null };
    statuses: Record<string, string>;
    clients: { id: number; name: string; company: string | null }[];
    templates: { id: number; name: string; needs_review: boolean }[];
};
const select = 'h-9 rounded-md border border-input bg-background px-3 text-sm';

export default function QuotesIndex({
    quotes,
    filters,
    statuses,
    clients,
    templates,
}: Props) {
    return (
        <>
            <Head title="Quotations" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <h1 className="font-display text-3xl">Quotations</h1>
                <QuotesNav current="Quotations" />

                {clients.length === 0 ? (
                    <p className="rounded-xl border border-border bg-card p-5">
                        Add a client first under{' '}
                        <Link
                            href="/admin/quotes/clients"
                            className="underline"
                        >
                            Clients
                        </Link>
                        .
                    </p>
                ) : (
                    <Form
                        {...store.form()}
                        className="grid gap-3 rounded-xl border border-border bg-card p-5 lg:grid-cols-[1fr_1fr_1fr_auto] lg:items-end"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="q-title">Title</Label>
                                    <Input
                                        id="q-title"
                                        name="title"
                                        required
                                        placeholder="e.g. Due diligence: coal right, Emalahleni"
                                    />
                                    <InputError message={errors.title} />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="q-client">Client</Label>
                                    <select
                                        id="q-client"
                                        name="client_id"
                                        required
                                        className={select}
                                    >
                                        {clients.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.company
                                                    ? `${c.company} (${c.name})`
                                                    : c.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="q-template">
                                        Start from
                                    </Label>
                                    <select
                                        id="q-template"
                                        name="template_id"
                                        className={select}
                                    >
                                        <option value="">
                                            Blank quotation
                                        </option>
                                        {templates.map((t) => (
                                            <option key={t.id} value={t.id}>
                                                {t.name}
                                                {t.needs_review
                                                    ? ' (needs review)'
                                                    : ''}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <Button disabled={processing}>
                                    New quotation
                                </Button>
                            </>
                        )}
                    </Form>
                )}

                <Form
                    action={index().url}
                    method="get"
                    className="flex flex-wrap items-end gap-3"
                >
                    <div className="grid gap-1.5">
                        <Label htmlFor="search">Search</Label>
                        <Input
                            id="search"
                            name="q"
                            defaultValue={filters.q ?? ''}
                            placeholder="Number, title or client"
                            className="w-64"
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="status">Status</Label>
                        <select
                            id="status"
                            name="status"
                            defaultValue={filters.status ?? ''}
                            className={select}
                        >
                            <option value="">All</option>
                            {Object.entries(statuses).map(([k, v]) => (
                                <option key={k} value={k}>
                                    {v}
                                </option>
                            ))}
                        </select>
                    </div>
                    <Button variant="outline">Filter</Button>
                </Form>

                <div className="overflow-x-auto rounded-xl border border-border bg-card">
                    <table className="w-full min-w-[760px] text-left text-sm">
                        <thead className="border-b border-border text-muted-foreground">
                            <tr>
                                <th className="px-5 py-3 font-medium">
                                    Quotation
                                </th>
                                <th className="px-5 py-3 font-medium">
                                    Client
                                </th>
                                <th className="px-5 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-5 py-3 text-right font-medium">
                                    Total
                                </th>
                                <th className="px-5 py-3 font-medium">
                                    Valid until
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {quotes.data.map((q) => (
                                <tr key={q.ulid}>
                                    <td className="px-5 py-3">
                                        <Link
                                            href={edit(q.ulid)}
                                            className="font-medium underline-offset-4 hover:underline"
                                        >
                                            {q.reference}
                                        </Link>
                                        <p className="text-muted-foreground">
                                            {q.title}
                                        </p>
                                    </td>
                                    <td className="px-5 py-3">{q.client}</td>
                                    <td className="px-5 py-3">
                                        {q.statusLabel}
                                    </td>
                                    <td className="tabular px-5 py-3 text-right">
                                        {randCents(q.total)}
                                    </td>
                                    <td className="px-5 py-3">
                                        {q.validUntil}
                                    </td>
                                </tr>
                            ))}
                            {quotes.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-5 py-8 text-center text-muted-foreground"
                                    >
                                        No quotations yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

QuotesIndex.layout = { breadcrumbs: [{ title: 'Quotations', href: index() }] };
