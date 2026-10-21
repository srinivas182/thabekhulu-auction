import { Head } from '@inertiajs/react';
import { CircleAlert, CircleCheck } from 'lucide-react';
import { dashboard } from '@/routes';

type ServiceStatus = { ok: boolean; detail: string };

type Props = {
    health: Record<string, ServiceStatus>;
};

const labels: Record<string, string> = {
    database: 'Database',
    cache: 'Cache',
    queue: 'Queue',
};

export default function AdminDashboard({ health }: Props) {
    const services = Object.entries(health);
    const allHealthy = services.every(([, status]) => status.ok);

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div>
                    <h1 className="font-display text-3xl">Dashboard</h1>
                    <p className="mt-1 text-muted-foreground">
                        Auction KPIs arrive with listings and bidding. System
                        status is shown below.
                    </p>
                </div>

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
