import { Head, Link } from '@inertiajs/react';
import { randCents } from '@/lib/cents';
import { attendees, checkin, create, edit, index } from '@/routes/admin/events';

type E = {
    ulid: string;
    title: string;
    status: string;
    when: string;
    sold: number;
    capacity: number;
    checkedIn: number;
    price: number;
};

export default function EventsAdmin({ events }: { events: E[] }) {
    return (
        <>
            <Head title="Events" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div className="flex items-end justify-between gap-3">
                    <h1 className="font-display text-3xl">Events</h1>
                    <Link
                        href={create()}
                        className="inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground"
                    >
                        New event
                    </Link>
                </div>
                <ul className="divide-y divide-border rounded-xl border border-border bg-card">
                    {events.map((e) => (
                        <li
                            key={e.ulid}
                            className="flex flex-wrap items-center justify-between gap-3 px-5 py-3"
                        >
                            <div>
                                <Link
                                    href={edit(e.ulid)}
                                    className="font-medium underline-offset-4 hover:underline"
                                >
                                    {e.title}
                                </Link>
                                <p className="text-sm text-muted-foreground">
                                    {e.when} · {e.status} ·{' '}
                                    {e.price === 0
                                        ? 'free'
                                        : randCents(e.price)}{' '}
                                    · {e.sold}/{e.capacity} taken ·{' '}
                                    {e.checkedIn} checked in
                                </p>
                            </div>
                            <div className="flex gap-3 text-sm">
                                <Link
                                    href={checkin(e.ulid)}
                                    className="font-semibold underline"
                                >
                                    Check in
                                </Link>
                                <a
                                    href={attendees(e.ulid).url}
                                    className="underline"
                                >
                                    Attendees (CSV)
                                </a>
                            </div>
                        </li>
                    ))}
                    {events.length === 0 && (
                        <li className="px-5 py-8 text-center text-muted-foreground">
                            No events yet.
                        </li>
                    )}
                </ul>
            </div>
        </>
    );
}

EventsAdmin.layout = { breadcrumbs: [{ title: 'Events', href: index() }] };
