import { Head, router } from '@inertiajs/react';
import { CheckCircle2, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { randCents } from '@/lib/cents';
import { finance } from '@/routes/admin/reports';
import financeRoutes from '@/routes/admin/reports/finance';

type Props = {
    from: string;
    to: string;
    figures: {
        received: number;
        refunded: number;
        forfeited: number;
        applied: number;
        adjustments: number;
        heldAtEnd: number;
        invoices: number;
        fees: number;
        vat: number;
        invoiced: number;
        payouts: number;
        paidToSellers: number;
    };
    checks: { label: string; ok: boolean; detail: string }[];
    canExport: boolean;
};

export default function FinanceReport({
    from,
    to,
    figures: f,
    checks,
    canExport,
}: Props) {
    const [range, setRange] = useState({ from, to });
    const rows: [string, string][] = [
        ['Deposits received', randCents(f.received)],
        ['Refunded', randCents(f.refunded)],
        ['Forfeited after default', randCents(f.forfeited)],
        ['Applied to purchase prices', randCents(f.applied)],
        ['Adjustments', randCents(f.adjustments)],
        ['Deposits held at the end of the period', randCents(f.heldAtEnd)],
        [`Platform fees invoiced (${f.invoices} invoices)`, randCents(f.fees)],
        ['VAT on fees', randCents(f.vat)],
        ['Invoiced including VAT', randCents(f.invoiced)],
        [`Paid to sellers (${f.payouts} payouts)`, randCents(f.paidToSellers)],
    ];

    return (
        <>
            <Head title="Financial reports" />
            <div className="flex max-w-4xl flex-1 flex-col gap-5 p-4 sm:p-6">
                <h1 className="font-display text-3xl">Financial reports</h1>
                <div className="flex flex-wrap items-end gap-2">
                    <label className="grid gap-1 text-sm">
                        From
                        <Input
                            type="date"
                            value={range.from}
                            onChange={(e) =>
                                setRange({ ...range, from: e.target.value })
                            }
                        />
                    </label>
                    <label className="grid gap-1 text-sm">
                        To
                        <Input
                            type="date"
                            value={range.to}
                            onChange={(e) =>
                                setRange({ ...range, to: e.target.value })
                            }
                        />
                    </label>
                    <Button
                        onClick={() =>
                            router.get(finance().url, range, {
                                preserveScroll: true,
                            })
                        }
                    >
                        Show
                    </Button>
                </div>
                <dl className="divide-y divide-border rounded-xl border border-border bg-card">
                    {rows.map(([l, v]) => (
                        <div
                            key={l}
                            className="flex justify-between gap-3 px-5 py-2.5 text-sm"
                        >
                            <dt>{l}</dt>
                            <dd className="tabular font-semibold">{v}</dd>
                        </div>
                    ))}
                </dl>
                <ul className="grid gap-2">
                    {checks.map((c) => (
                        <li
                            key={c.label}
                            className="flex items-start gap-2 text-sm"
                        >
                            {c.ok ? (
                                <CheckCircle2
                                    className="size-5 text-status-live"
                                    aria-hidden="true"
                                />
                            ) : (
                                <TriangleAlert
                                    className="size-5 text-destructive"
                                    aria-hidden="true"
                                />
                            )}
                            <span>
                                <strong>{c.label}</strong>
                                {c.ok ? '' : ' — needs attention'}
                                <br />
                                <span className="text-muted-foreground">
                                    {c.detail}
                                </span>
                            </span>
                        </li>
                    ))}
                </ul>
                {canExport && (
                    <div className="flex flex-wrap gap-4 text-sm">
                        {(['ledger', 'invoices', 'payouts'] as const).map(
                            (r) => (
                                <a
                                    key={r}
                                    href={
                                        financeRoutes.csv(
                                            { report: r },
                                            { query: range },
                                        ).url
                                    }
                                    className="font-semibold underline"
                                >
                                    Export {r} (CSV)
                                </a>
                            ),
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

FinanceReport.layout = {
    breadcrumbs: [{ title: 'Financial reports', href: finance() }],
};
