import { Head, Link } from '@inertiajs/react';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/admin/kyc';

type V = {
    ulid: string;
    name: string;
    email: string;
    status: string;
    statusLabel: string;
    documentType: string | null;
    submittedAt: string | null;
    identity: string | null;
};
type Props = {
    tab: string;
    verifications: { data: V[] };
    counts: { review: number; banks: number; screening: number };
    banks: {
        id: number;
        name: string;
        bank: string;
        holder: string;
        last4: string;
        createdAt: string | null;
        verification: string | null;
    }[];
    screeningDue: { ulid: string; name: string; lastScreened: string | null }[];
};

const tabs = [
    ['review', 'Needs review'],
    ['banks', 'Bank accounts'],
    ['screening', 'Screening due'],
    ['verified', 'Verified'],
    ['rejected', 'Rejected'],
] as const;
const providerLabel: Record<string, string> = {
    passed: 'ID matched',
    review: 'ID needs review',
    failed: 'ID not matched',
};

export default function KycIndex({
    tab,
    verifications,
    counts,
    banks,
    screeningDue,
}: Props) {
    const count = (t: string) =>
        t === 'review'
            ? counts.review
            : t === 'banks'
              ? counts.banks
              : t === 'screening'
                ? counts.screening
                : null;

    return (
        <>
            <Head title="KYC review" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <h1 className="font-display text-3xl">KYC review</h1>
                <nav
                    aria-label="Queues"
                    className="flex flex-wrap gap-x-6 border-b border-border text-sm"
                >
                    {tabs.map(([t, label]) => (
                        <Link
                            key={t}
                            href={index({ query: { tab: t } })}
                            aria-current={tab === t ? 'page' : undefined}
                            className={cn(
                                '-mb-px border-b-2 py-2.5',
                                tab === t
                                    ? 'border-primary font-semibold'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {label}
                            {count(t) ? (
                                <span className="tabular ml-1.5 rounded-full bg-status-closing/10 px-2 py-0.5 text-xs font-semibold text-status-closing">
                                    {count(t)}
                                </span>
                            ) : null}
                        </Link>
                    ))}
                </nav>

                {['review', 'verified', 'rejected'].includes(tab) && (
                    <ul className="divide-y divide-border rounded-xl border border-border bg-card">
                        {verifications.data.map((v) => (
                            <li
                                key={v.ulid}
                                className="flex flex-wrap items-center justify-between gap-3 px-5 py-3"
                            >
                                <div>
                                    <Link
                                        href={show(v.ulid)}
                                        className="font-medium underline-offset-4 hover:underline"
                                    >
                                        {v.name}
                                    </Link>
                                    <p className="text-sm text-muted-foreground">
                                        {v.email} ·{' '}
                                        {v.documentType === 'passport'
                                            ? 'Passport'
                                            : 'SA ID'}
                                    </p>
                                </div>
                                <p className="text-sm">
                                    {v.identity && (
                                        <span className="mr-3 font-semibold">
                                            {providerLabel[v.identity]}
                                        </span>
                                    )}
                                    <span className="text-muted-foreground">
                                        {v.statusLabel} ·{' '}
                                        {formatDateTime(v.submittedAt)}
                                    </span>
                                </p>
                            </li>
                        ))}
                        {verifications.data.length === 0 && (
                            <li className="px-5 py-8 text-center text-muted-foreground">
                                Nothing here.
                            </li>
                        )}
                    </ul>
                )}

                {tab === 'banks' && (
                    <ul className="divide-y divide-border rounded-xl border border-border bg-card">
                        {banks.map((b) => (
                            <li
                                key={b.id}
                                className="flex flex-wrap items-center justify-between gap-3 px-5 py-3"
                            >
                                <div>
                                    {b.verification ? (
                                        <Link
                                            href={show(b.verification)}
                                            className="font-medium underline-offset-4 hover:underline"
                                        >
                                            {b.name}
                                        </Link>
                                    ) : (
                                        <span className="font-medium">
                                            {b.name}
                                        </span>
                                    )}
                                    <p className="text-sm text-muted-foreground">
                                        {b.bank} ending {b.last4} · holder “
                                        {b.holder}”
                                    </p>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {formatDateTime(b.createdAt)}
                                </p>
                            </li>
                        ))}
                        {banks.length === 0 && (
                            <li className="px-5 py-8 text-center text-muted-foreground">
                                No bank accounts waiting.
                            </li>
                        )}
                    </ul>
                )}

                {tab === 'screening' && (
                    <>
                        <p className="max-w-3xl text-sm text-muted-foreground">
                            Verified buyers must be screened against sanctions
                            and PEP lists at least every 30 days. Buyers listed
                            here can’t be approved for a listing until screened
                            again.
                        </p>
                        <ul className="divide-y divide-border rounded-xl border border-border bg-card">
                            {screeningDue.map((s) => (
                                <li
                                    key={s.ulid}
                                    className="flex flex-wrap items-center justify-between gap-3 px-5 py-3"
                                >
                                    <Link
                                        href={show(s.ulid)}
                                        className="font-medium underline-offset-4 hover:underline"
                                    >
                                        {s.name}
                                    </Link>
                                    <p className="text-sm text-muted-foreground">
                                        {s.lastScreened
                                            ? `Last screened ${formatDateTime(s.lastScreened)}`
                                            : 'Never screened'}
                                    </p>
                                </li>
                            ))}
                            {screeningDue.length === 0 && (
                                <li className="px-5 py-8 text-center text-muted-foreground">
                                    Everyone is up to date.
                                </li>
                            )}
                        </ul>
                    </>
                )}
            </div>
        </>
    );
}

KycIndex.layout = { breadcrumbs: [{ title: 'KYC review', href: index() }] };
