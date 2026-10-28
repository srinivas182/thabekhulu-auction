import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/lib/format';
import { rand } from '@/lib/money';
import { bidders, edit, index, store } from '@/routes/admin/listings';

type Row = {
    ulid: string;
    title: string;
    category: string;
    city: string | null;
    status: string;
    statusLabel: string;
    minimum: number | null;
    startsAt: string | null;
    applied: number;
    pending: number;
    approved: number;
    bidders: number;
    highestBid: number | null;
    sealed: boolean;
    endsAt: string | null;
};
type Props = {
    listings: {
        data: Row[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    filters: { q?: string; status?: string; category?: string };
    categories: { id: number; name: string; slug: string }[];
    statuses: { value: string; label: string }[];
    canCreate: boolean;
};

export const statusTone: Record<string, string> = {
    draft: 'text-muted-foreground border-border',
    in_review: 'text-status-processing border-status-processing',
    changes_requested: 'text-status-closing border-status-closing',
    closed: 'text-muted-foreground border-border',
    sold: 'text-status-live border-status-live',
    no_bids: 'text-muted-foreground border-border',
    scheduled: 'text-status-soon border-status-soon',
    live: 'text-status-live border-status-live',
    withdrawn: 'text-muted-foreground border-border',
};

const select = 'h-9 rounded-md border border-input bg-background px-3 text-sm';
const pageLabel = (label: string) =>
    label.replace('&laquo;', '‹').replace('&raquo;', '›').trim();

export default function ListingsIndex({
    listings,
    filters,
    categories,
    statuses,
    canCreate,
}: Props) {
    return (
        <>
            <Head title="Listings" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <h1 className="font-display text-3xl">Listings</h1>

                {canCreate && (
                    <Form
                        {...store.form()}
                        className="flex flex-col gap-3 rounded-xl border border-border bg-card p-5 lg:flex-row lg:items-end"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="new-category">
                                        Category
                                    </Label>
                                    <select
                                        id="new-category"
                                        name="category_id"
                                        required
                                        className={select}
                                    >
                                        {categories.map((c) => (
                                            <option key={c.slug} value={c.id}>
                                                {c.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="grid flex-1 gap-2">
                                    <Label htmlFor="new-title">Title</Label>
                                    <Input
                                        id="new-title"
                                        name="title"
                                        required
                                        placeholder="e.g. Coal prospecting right, 640 ha"
                                    />
                                    <InputError message={errors.title} />
                                </div>
                                <Button disabled={processing}>
                                    Create draft
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
                        <Label htmlFor="q">Search</Label>
                        <Input
                            id="q"
                            name="q"
                            defaultValue={filters.q}
                            className="w-64"
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="f-status">Status</Label>
                        <select
                            id="f-status"
                            name="status"
                            defaultValue={filters.status ?? ''}
                            className={select}
                        >
                            <option value="">All</option>
                            {statuses.map((s) => (
                                <option key={s.value} value={s.value}>
                                    {s.label}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="f-category">Category</Label>
                        <select
                            id="f-category"
                            name="category"
                            defaultValue={filters.category ?? ''}
                            className={select}
                        >
                            <option value="">All</option>
                            {categories.map((c) => (
                                <option key={c.slug} value={c.slug}>
                                    {c.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <Button variant="outline">Filter</Button>
                </Form>

                <div className="overflow-x-auto rounded-xl border border-border bg-card">
                    <table className="w-full min-w-[820px] text-left text-sm">
                        <thead className="border-b border-border text-muted-foreground">
                            <tr>
                                <th className="px-5 py-3 font-medium">
                                    Listing
                                </th>
                                <th className="px-5 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-5 py-3 font-medium">
                                    Applications
                                </th>
                                <th className="px-5 py-3 font-medium">
                                    Bidding
                                </th>
                                <th className="px-5 py-3 font-medium">
                                    Minimum
                                </th>
                                <th className="px-5 py-3 font-medium">Opens</th>
                                <th className="px-5 py-3 font-medium">
                                    Closes
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {listings.data.map((l) => (
                                <tr key={l.ulid}>
                                    <td className="px-5 py-3">
                                        <Link
                                            href={edit(l.ulid)}
                                            className="font-medium text-foreground underline-offset-4 hover:underline"
                                        >
                                            {l.title}
                                        </Link>
                                        <p className="text-muted-foreground">
                                            {l.category}
                                            {l.city ? ` · ${l.city}` : ''}
                                        </p>
                                    </td>
                                    <td className="px-5 py-3">
                                        <span
                                            className={`inline-flex rounded-full border px-2.5 py-0.5 text-xs font-semibold ${statusTone[l.status] ?? ''}`}
                                        >
                                            {l.statusLabel}
                                        </span>
                                    </td>
                                    <td className="px-5 py-3 whitespace-nowrap">
                                        {l.applied === 0 ? (
                                            <span className="text-muted-foreground">
                                                None yet
                                            </span>
                                        ) : (
                                            <>
                                                <span className="font-medium">
                                                    {l.applied} applied
                                                </span>
                                                {l.pending > 0 && (
                                                    <Link
                                                        href={bidders(l.ulid)}
                                                        className="block text-sm font-semibold text-amber-700 underline-offset-2 hover:underline dark:text-amber-400"
                                                    >
                                                        {l.pending} waiting
                                                    </Link>
                                                )}
                                            </>
                                        )}
                                    </td>
                                    <td className="px-5 py-3 whitespace-nowrap">
                                        {l.approved === 0 ? (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        ) : (
                                            <>
                                                <span className="font-medium">
                                                    {l.approved} approved
                                                </span>
                                                <span className="block text-sm text-muted-foreground">
                                                    {l.sealed
                                                        ? 'Sealed tender'
                                                        : `${l.bidders} bid${l.highestBid !== null ? ` · ${rand(l.highestBid)}` : ''}`}
                                                </span>
                                            </>
                                        )}
                                    </td>
                                    <td className="tabular px-5 py-3 whitespace-nowrap">
                                        {rand(l.minimum)}
                                    </td>
                                    <td className="px-5 py-3 text-muted-foreground">
                                        {formatDateTime(l.startsAt)}
                                    </td>
                                    <td className="px-5 py-3 text-muted-foreground">
                                        {formatDateTime(l.endsAt)}
                                    </td>
                                </tr>
                            ))}
                            {listings.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-5 py-8 text-center text-muted-foreground"
                                    >
                                        No listings match.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {listings.links.length > 3 && (
                    <nav aria-label="Pages" className="flex flex-wrap gap-1">
                        {listings.links.map((link, i) =>
                            link.url ? (
                                <Link
                                    key={i}
                                    href={link.url}
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

ListingsIndex.layout = {
    breadcrumbs: [{ title: 'Listings', href: index() }],
};
