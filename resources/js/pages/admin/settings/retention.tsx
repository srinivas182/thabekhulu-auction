import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { retention } from '@/routes/admin/settings';
import retentionRoutes from '@/routes/admin/settings/retention';

type Props = {
    categories: {
        key: string;
        label: string;
        kept: string;
        basis: string;
        due: number;
    }[];
    approval: { enabled: boolean; reference: string; by: string; at: string };
};

export default function Retention({ categories, approval }: Props) {
    const [on, setOn] = useState(approval.enabled);

    return (
        <>
            <Head title="Data retention" />
            <div className="flex max-w-4xl flex-1 flex-col gap-5 p-4 sm:p-6">
                <div>
                    <h1 className="font-display text-3xl">Data retention</h1>
                    <p className="mt-1 text-muted-foreground">
                        How long personal data is kept. The schedule is a
                        proposal until Thabekhulu’s attorney approves it; until
                        then the weekly job only reports. Financial records
                        (ledger, receipts, invoices, payouts) are never removed
                        by this job.
                    </p>
                </div>
                <div className="overflow-x-auto rounded-xl border border-border bg-card">
                    <table className="w-full min-w-[640px] text-sm">
                        <thead className="text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-2 font-medium">Data</th>
                                <th className="font-medium">Kept</th>
                                <th className="font-medium">Basis</th>
                                <th className="px-4 text-right font-medium">
                                    Due now
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {categories.map((c) => (
                                <tr
                                    key={c.key}
                                    className="border-t border-border"
                                >
                                    <td className="px-4 py-2 font-medium">
                                        {c.label}
                                    </td>
                                    <td>{c.kept}</td>
                                    <td className="text-muted-foreground">
                                        {c.basis}
                                    </td>
                                    <td className="tabular px-4 text-right">
                                        {c.due}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Form
                    action={retentionRoutes.update().url}
                    method="put"
                    options={{ preserveScroll: true }}
                    className="grid gap-3 rounded-xl border border-border bg-card p-5 text-sm"
                >
                    {({ processing, errors }) => (
                        <>
                            <h2 className="font-semibold">Purging</h2>
                            <p>
                                {approval.enabled
                                    ? `Enabled under approval “${approval.reference}”, recorded by ${approval.by} on ${approval.at.slice(0, 10)}.`
                                    : 'Report only: nothing is deleted.'}
                            </p>
                            <input
                                type="hidden"
                                name="enabled"
                                value={on ? '1' : '0'}
                            />
                            <label className="flex items-start gap-2">
                                <input
                                    type="checkbox"
                                    checked={on}
                                    onChange={(e) => setOn(e.target.checked)}
                                    className="mt-0.5 size-4 accent-primary"
                                />
                                <span>
                                    The attorney has approved this schedule.
                                    Apply it weekly (deletes and anonymises what
                                    is due).
                                </span>
                            </label>
                            {on && (
                                <label className="grid gap-1">
                                    Approval reference (letter or email, with
                                    date)
                                    <Input
                                        name="reference"
                                        defaultValue={approval.reference}
                                    />
                                </label>
                            )}
                            {errors.reference && (
                                <p className="text-destructive">
                                    {errors.reference}
                                </p>
                            )}
                            <Button disabled={processing} className="w-fit">
                                Save
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

Retention.layout = {
    breadcrumbs: [{ title: 'Data retention', href: retention() }],
};
