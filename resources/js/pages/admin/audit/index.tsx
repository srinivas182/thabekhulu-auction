import { Form, Head, Link } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/lib/format';
import { exportMethod as exportCsv, index } from '@/routes/admin/audit';

type Entry = {
    id: number;
    at: string | null;
    module: string | null;
    event: string | null;
    description: string;
    actor: string;
    actorEmail: string | null;
    ip: string | null;
    changes: Record<string, { old: unknown; new: unknown }>;
};

type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};

type Filters = {
    user: string;
    module: string;
    action: string;
    from: string;
    to: string;
};

type Props = {
    entries: Paginated<Entry>;
    filters: Filters;
    modules: string[];
    canExport: boolean;
};

const show = (value: unknown): string => {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'string') {
        return value;
    }

    if (typeof value === 'number' || typeof value === 'boolean') {
        return value.toString();
    }

    return JSON.stringify(value);
};

// Laravel's pagination labels contain HTML entities; show plain text instead.
const pageLabel = (label: string): string =>
    label.replace('&laquo;', '‹').replace('&raquo;', '›').trim();

const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';

export default function AuditIndex({
    entries,
    filters,
    modules,
    canExport,
}: Props) {
    const query = Object.fromEntries(
        Object.entries(filters).filter(([, v]) => v !== ''),
    );

    return (
        <>
            <Head title="Audit log" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="font-display text-3xl">Audit log</h1>
                        <p className="mt-1 max-w-2xl text-muted-foreground">
                            A permanent record of sign-ins and changes. Entries
                            can’t be edited or deleted. Times are South African.
                        </p>
                    </div>
                    {canExport && (
                        <Button variant="outline" asChild>
                            <a href={exportCsv({ query }).url}>
                                <Download aria-hidden="true" />
                                Export CSV
                            </a>
                        </Button>
                    )}
                </div>

                <Form
                    action={index().url}
                    method="get"
                    className="grid gap-3 rounded-xl border border-border bg-card p-4 sm:grid-cols-2 lg:grid-cols-6 lg:items-end"
                >
                    <div className="grid gap-1.5 lg:col-span-2">
                        <Label htmlFor="f-user">Person (name or email)</Label>
                        <Input
                            id="f-user"
                            name="user"
                            defaultValue={filters.user}
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="f-module">Module</Label>
                        <select
                            id="f-module"
                            name="module"
                            defaultValue={filters.module}
                            className={selectClass}
                        >
                            <option value="">All</option>
                            {modules.map((m) => (
                                <option key={m} value={m}>
                                    {m}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="f-action">Action</Label>
                        <Input
                            id="f-action"
                            name="action"
                            defaultValue={filters.action}
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="f-from">From</Label>
                        <Input
                            id="f-from"
                            name="from"
                            type="date"
                            defaultValue={filters.from}
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="f-to">To</Label>
                        <Input
                            id="f-to"
                            name="to"
                            type="date"
                            defaultValue={filters.to}
                        />
                    </div>
                    <div className="flex gap-2 lg:col-span-6">
                        <Button>Apply filters</Button>
                        <Button variant="ghost" asChild>
                            <Link href={index()}>Clear</Link>
                        </Button>
                    </div>
                </Form>

                <p className="tabular text-sm text-muted-foreground">
                    {entries.total} {entries.total === 1 ? 'entry' : 'entries'}
                </p>

                <ol className="divide-y divide-border rounded-xl border border-border bg-card">
                    {entries.data.map((entry) => (
                        <li
                            key={entry.id}
                            className="grid gap-1 px-5 py-3 sm:grid-cols-[11rem_1fr]"
                        >
                            <time
                                className="tabular text-sm text-muted-foreground"
                                dateTime={entry.at ?? undefined}
                            >
                                {formatDateTime(entry.at)}
                            </time>
                            <div>
                                <p>
                                    <span className="font-medium">
                                        {entry.description}
                                    </span>{' '}
                                    <span className="text-sm text-muted-foreground">
                                        by {entry.actor}
                                        {entry.ip && ` from ${entry.ip}`}
                                    </span>
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {entry.module}
                                    {entry.event && ` / ${entry.event}`}
                                </p>
                                {Object.keys(entry.changes).length > 0 && (
                                    <dl className="mt-2 grid gap-1 text-sm">
                                        {Object.entries(entry.changes).map(
                                            ([key, change]) => (
                                                <div
                                                    key={key}
                                                    className="flex flex-wrap gap-x-2"
                                                >
                                                    <dt className="font-medium">
                                                        {key}:
                                                    </dt>
                                                    <dd className="text-muted-foreground">
                                                        <del className="decoration-destructive/60">
                                                            {show(change.old)}
                                                        </del>
                                                        {' → '}
                                                        <ins className="text-foreground no-underline">
                                                            {show(change.new)}
                                                        </ins>
                                                    </dd>
                                                </div>
                                            ),
                                        )}
                                    </dl>
                                )}
                            </div>
                        </li>
                    ))}
                    {entries.data.length === 0 && (
                        <li className="px-5 py-8 text-center text-muted-foreground">
                            No entries match these filters.
                        </li>
                    )}
                </ol>

                {entries.links.length > 3 && (
                    <nav aria-label="Pages" className="flex flex-wrap gap-1">
                        {entries.links.map((link, i) =>
                            link.url ? (
                                <Link
                                    key={i}
                                    href={link.url}
                                    preserveScroll
                                    className={
                                        'rounded-md px-3 py-1.5 text-sm ' +
                                        (link.active
                                            ? 'bg-primary text-primary-foreground'
                                            : 'hover:bg-accent')
                                    }
                                >
                                    {pageLabel(link.label)}
                                </Link>
                            ) : (
                                <span
                                    key={i}
                                    className="px-3 py-1.5 text-sm text-muted-foreground"
                                >
                                    {pageLabel(link.label)}
                                </span>
                            ),
                        )}
                    </nav>
                )}
            </div>
        </>
    );
}

AuditIndex.layout = {
    breadcrumbs: [{ title: 'Audit log', href: index() }],
};
