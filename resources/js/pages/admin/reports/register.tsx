import { Head } from '@inertiajs/react';
import { randCents } from '@/lib/cents';
import { register } from '@/routes/admin/reports';
import registerRoutes from '@/routes/admin/reports/register';

type Row = {
    name: string;
    email: string;
    joined: string | null;
    entities: string;
    verification: string;
    approved_for: number;
    bids: number;
    wins: number;
    suspended_until: string | null;
    screening?: string;
    id_last4?: string | null;
    deposits_held_cents?: number;
};

export default function Register({
    rows,
    can,
}: {
    rows: Row[];
    can: { kyc: boolean; money: boolean; export: boolean };
}) {
    return (
        <>
            <Head title="Investor register" />
            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="font-display text-3xl">
                            Investor register
                        </h1>
                        <p className="text-muted-foreground">
                            {rows.length} buyers. Columns depend on your role.
                        </p>
                    </div>
                    {can.export && (
                        <a
                            href={registerRoutes.csv().url}
                            className="text-sm font-semibold underline"
                        >
                            Export CSV
                        </a>
                    )}
                </div>
                <div className="overflow-x-auto rounded-xl border border-border bg-card">
                    <table className="w-full min-w-[900px] text-sm">
                        <thead className="text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-2 font-medium">Buyer</th>
                                <th className="font-medium">Bidding for</th>
                                <th className="font-medium">Verification</th>
                                {can.kyc && (
                                    <th className="font-medium">
                                        Screening · ID
                                    </th>
                                )}
                                <th className="text-right font-medium">
                                    Approved
                                </th>
                                <th className="text-right font-medium">Bids</th>
                                <th className="text-right font-medium">Wins</th>
                                {can.money && (
                                    <th className="text-right font-medium">
                                        Deposits held
                                    </th>
                                )}
                                <th className="px-4 font-medium">Suspended</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((r) => (
                                <tr
                                    key={r.email}
                                    className="border-t border-border"
                                >
                                    <td className="px-4 py-2">
                                        <span className="font-medium">
                                            {r.name}
                                        </span>
                                        <br />
                                        <span className="text-muted-foreground">
                                            {r.email}
                                        </span>
                                    </td>
                                    <td>{r.entities || 'Self'}</td>
                                    <td>{r.verification}</td>
                                    {can.kyc && (
                                        <td>
                                            {r.screening}
                                            {r.id_last4
                                                ? ` · •••${r.id_last4}`
                                                : ''}
                                        </td>
                                    )}
                                    <td className="tabular text-right">
                                        {r.approved_for}
                                    </td>
                                    <td className="tabular text-right">
                                        {r.bids}
                                    </td>
                                    <td className="tabular text-right">
                                        {r.wins}
                                    </td>
                                    {can.money && (
                                        <td className="tabular text-right">
                                            {randCents(
                                                r.deposits_held_cents ?? 0,
                                            )}
                                        </td>
                                    )}
                                    <td className="px-4">
                                        {r.suspended_until ?? ''}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

Register.layout = {
    breadcrumbs: [{ title: 'Investor register', href: register() }],
};
