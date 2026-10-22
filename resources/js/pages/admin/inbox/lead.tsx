import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/lib/format';
import { rand } from '@/lib/money';
import { index, photo, update } from '@/routes/admin/leads';

type Lead = {
    ulid: string;
    name: string;
    email: string;
    mobile: string;
    type: string;
    location: string;
    askingPrice: number | null;
    description: string;
    status: string;
    assignedTo: number | null;
    createdAt: string | null;
    photos: { id: number; name: string }[];
    notes: { note: string; by: string; at: string }[];
};
type Props = {
    lead: Lead;
    statuses: Record<string, string>;
    staff: { id: number; name: string }[];
    canManage: boolean;
};

export default function LeadShow({ lead, statuses, staff, canManage }: Props) {
    const [f, setF] = useState({
        status: lead.status,
        assigned_to: lead.assignedTo,
        note: '',
    });

    return (
        <>
            <Head title={`Lead: ${lead.name}`} />
            <div className="grid flex-1 gap-6 p-4 sm:p-6 xl:grid-cols-[1fr_360px]">
                <div className="grid h-fit gap-5">
                    <div>
                        <h1 className="font-display text-3xl">{lead.name}</h1>
                        <p className="text-muted-foreground">
                            Received {formatDateTime(lead.createdAt)}
                        </p>
                    </div>
                    <dl className="grid gap-3 rounded-xl border border-border bg-card p-5 sm:grid-cols-2">
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                Email
                            </dt>
                            <dd>
                                <a
                                    href={`mailto:${lead.email}`}
                                    className="underline"
                                >
                                    {lead.email}
                                </a>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                Mobile
                            </dt>
                            <dd>
                                <a
                                    href={`tel:${lead.mobile}`}
                                    className="underline"
                                >
                                    {lead.mobile}
                                </a>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                Property
                            </dt>
                            <dd>
                                {lead.type} in {lead.location}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                Asking price
                            </dt>
                            <dd className="tabular">
                                {rand(lead.askingPrice)}
                            </dd>
                        </div>
                        <div className="sm:col-span-2">
                            <dt className="text-sm text-muted-foreground">
                                About the property
                            </dt>
                            <dd className="whitespace-pre-line">
                                {lead.description}
                            </dd>
                        </div>
                    </dl>
                    {lead.photos.length > 0 && (
                        <section
                            className="rounded-xl border border-border bg-card p-5"
                            aria-label="Photos"
                        >
                            <h2 className="font-semibold">Photos (private)</h2>
                            <ul className="mt-2 flex flex-wrap gap-3 text-sm">
                                {lead.photos.map((p) => (
                                    <li key={p.id}>
                                        <a
                                            href={
                                                photo({
                                                    lead: lead.ulid,
                                                    media: p.id,
                                                }).url
                                            }
                                            target="_blank"
                                            rel="noreferrer"
                                            className="underline"
                                        >
                                            {p.name}
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}
                    <section
                        className="rounded-xl border border-border bg-card p-5"
                        aria-label="Notes"
                    >
                        <h2 className="font-semibold">Notes</h2>
                        <ul className="mt-2 grid gap-2 text-sm">
                            {lead.notes.map((n, i) => (
                                <li key={i} className="rounded-lg bg-muted p-3">
                                    <p className="whitespace-pre-line">
                                        {n.note}
                                    </p>
                                    <p className="mt-1 text-muted-foreground">
                                        {n.by}, {formatDateTime(n.at)}
                                    </p>
                                </li>
                            ))}
                        </ul>
                        {lead.notes.length === 0 && (
                            <p className="mt-2 text-sm text-muted-foreground">
                                No notes yet.
                            </p>
                        )}
                    </section>
                </div>
                {canManage && (
                    <aside
                        className="grid h-fit gap-4 rounded-xl border border-border bg-card p-5"
                        aria-label="Update lead"
                    >
                        <div className="grid gap-1.5">
                            <Label htmlFor="st">Status</Label>
                            <select
                                id="st"
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                value={f.status}
                                onChange={(e) =>
                                    setF({ ...f, status: e.target.value })
                                }
                            >
                                {Object.entries(statuses).map(([k, v]) => (
                                    <option key={k} value={k}>
                                        {v}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="as">Assigned to</Label>
                            <select
                                id="as"
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                value={f.assigned_to ?? ''}
                                onChange={(e) =>
                                    setF({
                                        ...f,
                                        assigned_to: e.target.value
                                            ? Number(e.target.value)
                                            : null,
                                    })
                                }
                            >
                                <option value="">Nobody</option>
                                {staff.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="nt">Add a note</Label>
                            <textarea
                                id="nt"
                                className="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                                value={f.note}
                                onChange={(e) =>
                                    setF({ ...f, note: e.target.value })
                                }
                            />
                        </div>
                        <Button
                            onClick={() =>
                                router.put(update(lead.ulid).url, f, {
                                    preserveScroll: true,
                                    onSuccess: () => setF({ ...f, note: '' }),
                                })
                            }
                        >
                            Save
                        </Button>
                    </aside>
                )}
            </div>
        </>
    );
}

LeadShow.layout = { breadcrumbs: [{ title: 'Seller leads', href: index() }] };
