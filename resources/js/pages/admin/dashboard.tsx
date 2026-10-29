import { Head, Link } from '@inertiajs/react';
import { ArrowRight, CircleAlert, CircleCheck } from 'lucide-react';
import { randCents } from '@/lib/cents';
import { dashboard } from '@/routes/admin';

type ServiceStatus = { ok: boolean; detail: string };

type Attention = {
    label: string;
    count: number;
    href: string;
    urgent: boolean;
};
type Group = {
    title: string;
    href: string;
    items: { label: string; value: number; cents?: boolean }[];
};

type Props = {
    attention: Attention[];
    groups: Group[];
    health: Record<string, ServiceStatus>;
};

const labels: Record<string, string> = {
    database: 'Database',
    cache: 'Cache',
    queue: 'Queue',
};

export default function AdminDashboard({ attention, groups, health }: Props) {
    const services = Object.entries(health);
    const allHealthy = services.every(([, status]) => status.ok);

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div>
                    <h1 className="font-display text-3xl">Dashboard</h1>
                    <p className="mt-1 text-muted-foreground">
                        What needs attention now, and the key figures.
                    </p>
                </div>

                <section
                    aria-labelledby="attention-h"
                    className="rounded-xl border border-border bg-card p-5"
                >
                    <h2 id="attention-h" className="font-semibold">
                        Needs attention
                    </h2>
                    {attention.length === 0 ? (
                        <p className="mt-2 flex items-center gap-2 text-sm text-status-live">
                            <CircleCheck
                                className="size-4"
                                aria-hidden="true"
                            />
                            Nothing is waiting for you right now.
                        </p>
                    ) : (
                        <ul className="mt-3 divide-y divide-border">
                            {attention.map((a) => (
                                <li key={a.label}>
                                    <Link
                                        href={a.href}
                                        className="flex items-center justify-between gap-3 py-2.5 hover:underline"
                                    >
                                        <span className="flex items-center gap-3">
                                            <span
                                                className={
                                                    a.urgent
                                                        ? 'tabular min-w-10 rounded-full bg-amber-100 px-2.5 py-0.5 text-center font-semibold text-amber-900 dark:bg-amber-950 dark:text-amber-100'
                                                        : 'tabular min-w-10 rounded-full bg-muted px-2.5 py-0.5 text-center font-semibold'
                                                }
                                            >
                                                {a.count}
                                            </span>
                                            {a.label}
                                        </span>
                                        <ArrowRight
                                            className="size-4 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {groups.map((g) => (
                    <section
                        key={g.title}
                        aria-label={g.title}
                        className="grid gap-3"
                    >
                        <div className="flex items-baseline justify-between">
                            <h2 className="font-semibold">{g.title}</h2>
                            <Link href={g.href} className="text-sm underline">
                                Open
                            </Link>
                        </div>
                        <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                            {g.items.map((i) => (
                                <div
                                    key={i.label}
                                    className="rounded-xl border border-border bg-card p-4"
                                >
                                    <dt className="text-sm text-muted-foreground">
                                        {i.label}
                                    </dt>
                                    <dd className="tabular mt-1 text-2xl font-semibold">
                                        {i.cents
                                            ? randCents(i.value)
                                            : String(i.value).replace(
                                                  /\B(?=(\d{3})+(?!\d))/g,
                                                  ' ',
                                              )}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </section>
                ))}

                <section
                    aria-labelledby="system-status"
                    className="rounded-xl border border-border bg-card"
                >
                    <div className="flex items-center justify-between border-b border-border px-5 py-4">
                        <h2 id="system-status" className="font-semibold">
                            System status
                        </h2>
                        <span
                            className={
                                allHealthy
                                    ? 'text-sm font-medium text-status-live'
                                    : 'text-sm font-medium text-destructive'
                            }
                        >
                            {allHealthy
                                ? 'All services running'
                                : 'Some services need attention'}
                        </span>
                    </div>
                    <ul className="divide-y divide-border">
                        {services.map(([name, status]) => (
                            <li
                                key={name}
                                className="flex items-center justify-between px-5 py-3"
                            >
                                <span className="flex items-center gap-3">
                                    {status.ok ? (
                                        <CircleCheck
                                            className="size-5 text-status-live"
                                            aria-hidden="true"
                                        />
                                    ) : (
                                        <CircleAlert
                                            className="size-5 text-destructive"
                                            aria-hidden="true"
                                        />
                                    )}
                                    {labels[name] ?? name}
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    {status.ok ? status.detail : 'Unavailable'}
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>
            </div>
        </>
    );
}

AdminDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
