import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Check } from 'lucide-react';
import { cn } from '@/lib/utils';

type Props = {
    name: string;
    attention: { kind: string; text: string; url: string; action: string }[];
    stats: { applications: number; approved: number; quotes: number };
    status: { verification: string; verified: boolean; bank: string };
    auctions: {
        title: string;
        url: string;
        dataRoom: string;
        status: string;
        approved: boolean;
        bidderNumber: string | null;
        closes: string | null;
    }[];
};

const card = 'rounded-[18px] border border-border bg-card';
const nav = [
    ['Dashboard', '/account'],
    ['Verification', '/account/verification'],
    ['Bank account', '/account/bank'],
    ['Companies and trusts', '/account/entities'],
    ['Bidding approvals', '/account/approvals'],
    ['Purchases', '/account/purchases'],
    ['Saved and watching', '/account/saved'],
    ['Quotations', '/account/quotes'],
    ['Alerts', '/account/notifications'],
    ['Profile and security', '/settings/profile'],
] as const;

/** Buyer dashboard from the approved design. */
export default function Dashboard({
    name,
    attention,
    stats,
    status,
    auctions,
}: Props) {
    return (
        <>
            <Head title="My account" />
            <nav
                aria-label="Account"
                className="border-b border-border bg-card"
            >
                <div className="mx-auto flex max-w-[1280px] gap-x-7 overflow-x-auto px-5 text-[15px] whitespace-nowrap sm:px-8">
                    {nav.map(([label, href]) => (
                        <Link
                            key={href}
                            href={href}
                            aria-current={
                                href === '/account' ? 'page' : undefined
                            }
                            className={cn(
                                'py-3.5',
                                href === '/account'
                                    ? 'border-b-2 border-primary font-semibold'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {label}
                        </Link>
                    ))}
                </div>
            </nav>
            <section className="mx-auto flex max-w-[1280px] flex-col gap-10 px-5 py-10 pb-24 sm:px-8">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <h1 className="font-display text-4xl sm:text-5xl">
                        Welcome back, {name}
                    </h1>
                    <Link
                        href="/auctions"
                        className="inline-flex min-h-12 items-center rounded-[10px] bg-primary px-6 font-semibold text-primary-foreground"
                    >
                        Find auctions
                    </Link>
                </div>

                <dl className="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-4">
                    <div className={cn(card, 'p-5')}>
                        <dt className="text-sm text-muted-foreground">
                            Applications
                        </dt>
                        <dd className="tabular mt-1 text-3xl font-bold">
                            {stats.applications}
                        </dd>
                    </div>
                    <div className={cn(card, 'p-5')}>
                        <dt className="text-sm text-muted-foreground">
                            Approved to bid
                        </dt>
                        <dd className="tabular mt-1 text-3xl font-bold">
                            {stats.approved}
                        </dd>
                    </div>
                    <div className={cn(card, 'p-5')}>
                        <dt className="text-sm text-muted-foreground">
                            Open quotations
                        </dt>
                        <dd className="tabular mt-1 text-3xl font-bold">
                            {stats.quotes}
                        </dd>
                    </div>
                    <div className={cn(card, 'p-5')}>
                        <dt className="text-sm text-muted-foreground">
                            Identity
                        </dt>
                        <dd
                            className={cn(
                                'mt-1 inline-flex items-center gap-1.5 text-lg font-semibold',
                                status.verified && 'text-status-live',
                            )}
                        >
                            {status.verified && (
                                <Check className="size-5" aria-hidden="true" />
                            )}
                            {status.verification}
                        </dd>
                    </div>
                </dl>

                {attention.length > 0 && (
                    <section
                        aria-labelledby="attention"
                        className="flex flex-col gap-3"
                    >
                        <h2 id="attention" className="font-display text-3xl">
                            Needs your attention
                        </h2>
                        {attention.map((a) => (
                            <div
                                key={a.kind + a.url}
                                className={cn(
                                    card,
                                    'flex flex-wrap items-center justify-between gap-3 px-6 py-4',
                                )}
                            >
                                <p>{a.text}</p>
                                <Link
                                    href={a.url}
                                    className="inline-flex min-h-11 items-center gap-1.5 rounded-[10px] border border-input px-4 font-semibold"
                                >
                                    {a.action}
                                    <ArrowRight
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </div>
                        ))}
                    </section>
                )}

                <section aria-labelledby="mine" className={card}>
                    <h2 id="mine" className="px-6 pt-5 font-display text-3xl">
                        My auctions
                    </h2>
                    {auctions.length === 0 ? (
                        <p className="px-6 py-6 text-muted-foreground">
                            You haven’t applied to bid yet.{' '}
                            <Link href="/auctions" className="underline">
                                Browse auctions
                            </Link>
                        </p>
                    ) : (
                        <ul className="mt-2 divide-y divide-border">
                            {auctions.map((a) => (
                                <li
                                    key={a.url}
                                    className="flex flex-wrap items-center justify-between gap-3 px-6 py-4"
                                >
                                    <div>
                                        <Link
                                            href={a.url}
                                            className="font-semibold underline-offset-4 hover:underline"
                                        >
                                            {a.title}
                                        </Link>
                                        <p className="text-sm text-muted-foreground">
                                            {a.status}
                                            {a.bidderNumber
                                                ? ` · bidder #${a.bidderNumber}`
                                                : ''}
                                            {a.closes
                                                ? ` · closes ${a.closes}`
                                                : ''}
                                        </p>
                                    </div>
                                    {a.approved && (
                                        <Link
                                            href={a.dataRoom}
                                            className="inline-flex min-h-11 items-center rounded-[10px] border border-input px-4 font-semibold"
                                        >
                                            Documents
                                        </Link>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section
                    aria-labelledby="account-h"
                    className={cn(card, 'grid gap-3 p-6 sm:grid-cols-2')}
                >
                    <h2
                        id="account-h"
                        className="text-lg font-semibold sm:col-span-2"
                    >
                        Your account
                    </h2>
                    <p>
                        <span className="text-muted-foreground">Identity:</span>{' '}
                        {status.verification} ·{' '}
                        <Link
                            href="/account/verification"
                            className="underline"
                        >
                            details
                        </Link>
                    </p>
                    <p>
                        <span className="text-muted-foreground">
                            Refund account:
                        </span>{' '}
                        {status.bank} ·{' '}
                        <Link href="/account/bank" className="underline">
                            manage
                        </Link>
                    </p>
                </section>
            </section>
        </>
    );
}
